<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;
use FieldVn\Zalo\Tests\Support\FakeTransport;
use Illuminate\Support\Facades\URL;

/*
| Trang chi tiết là nơi developer làm gần hết việc cài đặt: sửa, cắm webhook,
| lấy chat_id, gửi thử. Test ở đây luôn đi qua route() chứ không ghép URL tay
| — vòng trước đã có bài học về chuyện đó.
*/

beforeEach(function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
    config()->set('zalo.bot.webhook_secret', 'secret-du-dai-cho-zalo-32-ky-tu');
});

function auth2(): array
{
    return ['Authorization' => 'Basic '.base64_encode('admin:secret')];
}

function fakeNet(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

function aBot(string $slug = 'cskh-bot'): ZaloBot
{
    return ZaloBot::create(['name' => 'Bot CSKH', 'slug' => $slug, 'token' => '123:abc']);
}

function anOa(string $slug = 'cskh', bool $withToken = true): ZaloOa
{
    $oa = ZaloOa::create(['name' => 'CSKH', 'slug' => $slug, 'oa_id' => 'oa-'.$slug, 'is_active' => true]);

    if ($withToken) {
        ZaloOaToken::create([
            'oa_id' => $oa->id,
            'access_token' => 'a',
            'refresh_token' => 'r',
            'expires_at' => now()->addHour(),
            'refresh_expires_at' => now()->addDays(80),
        ]);
    }

    return $oa->refresh();
}

/*
| Bảo mật trước.
*/

it('CHẶN trang chi tiết khi chưa đăng nhập', function (): void {
    $bot = aBot();
    $oa = anOa();

    test()->get(route('zalo.bots.show', $bot))->assertStatus(401);
    test()->get(route('zalo.oas.show', $oa))->assertStatus(401);
});

it('KHÔNG BAO GIỜ hiện secret webhook ra màn hình', function (): void {
    // Màn hình này hay bị chụp lại lúc gỡ lỗi.
    config()->set('zalo.bot.webhook_secret', 'sieu-bi-mat-khong-duoc-lo-ra');
    $bot = aBot();

    test()->withHeaders(auth2())->get(route('zalo.bots.show', $bot))
        ->assertOk()
        ->assertDontSee('sieu-bi-mat-khong-duoc-lo-ra');
});

it('KHÔNG hiện token bot đầy đủ trên trang chi tiết', function (): void {
    $bot = ZaloBot::create(['name' => 'B', 'slug' => 'b', 'token' => '123456:cuc-ky-bi-mat']);

    test()->withHeaders(auth2())->get(route('zalo.bots.show', $bot))
        ->assertOk()
        ->assertDontSee('cuc-ky-bi-mat');
});

/*
| Sửa.
*/

it('sửa được tên bot mà không cần nhập lại token', function (): void {
    // Bắt nhập lại token mỗi lần đổi tên là cách chắc chắn khiến người ta
    // dán nhầm token của bot khác.
    $bot = aBot();

    test()->withHeaders(auth2())
        ->put(route('zalo.bots.update', $bot), ['name' => 'Tên mới'])
        ->assertRedirect();

    $bot->refresh();

    expect($bot->name)->toBe('Tên mới')
        ->and($bot->token)->toBe('123:abc');
});

it('HOÀN LẠI token cũ khi token mới không dùng được', function (): void {
    // Lưu một token hỏng nghĩa là bot chết im lặng cho tới lần gửi tin sau,
    // có thể nhiều ngày sau đó.
    fakeNet()->push(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->put(route('zalo.bots.update', $bot), ['name' => 'Bot CSKH', 'token' => 'token-hong'])
        ->assertSessionHas('zalo.error');

    expect($bot->refresh()->token)->toBe('123:abc');
});

it('lưu token mới và cập nhật username khi token dùng được', function (): void {
    fakeNet()->push(['ok' => true, 'result' => ['account_name' => 'bot.moi']]);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->put(route('zalo.bots.update', $bot), ['name' => 'Bot CSKH', 'token' => '999:xyz'])
        ->assertSessionHas('zalo.success');

    $bot->refresh();

    expect($bot->token)->toBe('999:xyz')
        ->and($bot->username)->toBe('bot.moi');
});

it('CẢNH BÁO khi đổi OA ID trong lúc đang giữ token', function (): void {
    // Token cũ gắn với OA cũ; im lặng ở đây đẻ ra lỗi rất khó truy.
    $oa = anOa();

    test()->withHeaders(auth2())
        ->put(route('zalo.oas.update', $oa), ['name' => 'CSKH', 'oa_id' => 'oa-khac'])
        ->assertSessionHas('zalo.error');

    expect($oa->refresh()->oa_id)->toBe('oa-khac');
});

it('đổi tên OA mà không đổi ID thì không cảnh báo gì', function (): void {
    $oa = anOa();

    test()->withHeaders(auth2())
        ->put(route('zalo.oas.update', $oa), ['name' => 'Tên mới', 'oa_id' => $oa->oa_id, 'tags' => 'a, b'])
        ->assertSessionHas('zalo.success');

    $oa->refresh();

    expect($oa->name)->toBe('Tên mới')
        ->and($oa->tags)->toBe(['a', 'b']);
});

it('từ chối OA ID đã thuộc về OA khác', function (): void {
    anOa('mot');
    $hai = anOa('hai');

    test()->withHeaders(auth2())
        ->put(route('zalo.oas.update', $hai), ['name' => 'x', 'oa_id' => 'oa-mot'])
        ->assertSessionHasErrors('oa_id');
});

/*
| Webhook của bot.
*/

it('CHẶN cắm webhook qua HTTP, không gọi mạng', function (): void {
    // Secret đi nguyên văn trong header X-Bot-Api-Secret-Token, nên HTTP là
    // để lộ nó. Zalo cũng từ chối, nhưng chặn sớm thì đỡ một request và cho
    // được thông báo nói rõ lý do.
    $fake = fakeNet();
    $bot = aBot();

    test()->withHeaders(auth2())
        ->post(route('zalo.bots.webhook.set', $bot))
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, 'HTTPS'));

    expect($fake->requests)->toBeEmpty();
});

it('cắm webhook gửi đúng URL riêng của bot kèm secret', function (): void {
    URL::forceScheme('https');

    $fake = fakeNet()->push(['ok' => true, 'result' => true]);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->post(route('zalo.bots.webhook.set', $bot))
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['data'])->toBe([
        'url' => route('zalo.webhook.bot', ['bot' => 'cskh-bot']),
        'secret_token' => 'secret-du-dai-cho-zalo-32-ky-tu',
    ]);
});

it('KHÔNG cắm webhook khi secret chưa hợp lệ', function (): void {
    URL::forceScheme('https');
    config()->set('zalo.bot.webhook_secret', 'ngan');
    $fake = fakeNet();
    $bot = aBot();

    test()->withHeaders(auth2())
        ->post(route('zalo.bots.webhook.set', $bot))
        ->assertSessionHas('zalo.error');

    expect($fake->requests)->toBeEmpty();
});

it('gỡ webhook báo bằng thông điệp CẢNH BÁO chứ không phải thành công', function (): void {
    // Gỡ webhook làm bot ngừng nhận tin — đó không phải tin vui.
    fakeNet()->push(['ok' => true, 'result' => true]);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->delete(route('zalo.bots.webhook.delete', $bot))
        ->assertSessionHas('zalo.error');
});

/*
| Hội thoại và gửi thử.
*/

it('hiện chat_id đã ghi nhận', function (): void {
    $bot = aBot();
    ZaloBotChat::create([
        'bot_id' => $bot->id,
        'chat_id' => 'chat-777',
        'display_name' => 'Nguyễn Văn A',
        'message_count' => 3,
        'last_message_at' => now(),
    ]);

    test()->withHeaders(auth2())->get(route('zalo.bots.show', $bot))
        ->assertOk()
        ->assertSee('chat-777')
        ->assertSee('Nguyễn Văn A');
});

it('hướng dẫn cụ thể khi chưa có hội thoại nào', function (): void {
    $bot = aBot();

    test()->withHeaders(auth2())->get(route('zalo.bots.show', $bot))
        ->assertOk()
        ->assertSee('Chưa ai nhắn cho bot');
});

it('gửi tin thử qua bot đi tới đúng endpoint', function (): void {
    $fake = fakeNet()->push(['ok' => true, 'result' => ['message_id' => 'm-1']]);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->post(route('zalo.bots.send', $bot), ['chat_id' => 'c-1', 'text' => 'chào'])
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['url'])->toEndWith('/sendMessage')
        ->and($fake->lastRequest()['data'])->toBe(['chat_id' => 'c-1', 'text' => 'chào']);
});

it('có URL ảnh thì gửi photo, nội dung thành chú thích', function (): void {
    $fake = fakeNet()->push(['ok' => true, 'result' => ['message_id' => 'm-1']]);
    $bot = aBot();

    test()->withHeaders(auth2())->post(route('zalo.bots.send', $bot), [
        'chat_id' => 'c-1',
        'text' => 'chú thích',
        'photo' => 'https://a.test/x.jpg',
    ])->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['url'])->toEndWith('/sendPhoto')
        ->and($fake->lastRequest()['data']['caption'])->toBe('chú thích');
});

it('hiện MÃ LỖI khi Zalo từ chối, không chỉ câu chữ', function (): void {
    // Tài liệu Zalo tra theo mã; thiếu mã là người dùng không tra được.
    fakeNet()->push(['ok' => false, 'error_code' => 400, 'description' => 'chat not found']);
    $bot = aBot();

    test()->withHeaders(auth2())
        ->post(route('zalo.bots.send', $bot), ['chat_id' => 'sai', 'text' => 'x'])
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, '400'));
});

it('gửi tin thử qua OA dùng endpoint tin tư vấn', function (): void {
    $fake = fakeNet()->push(['error' => 0, 'data' => []]);
    $oa = anOa();

    test()->withHeaders(auth2())
        ->post(route('zalo.oas.send', $oa), ['user_id' => 'u-1', 'text' => 'chào'])
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['url'])->toEndWith('/v3.0/oa/message/cs');
});

it('GIẢI THÍCH cửa sổ 48 giờ khi Zalo trả mã -230', function (): void {
    // "User is not in whitelist" không nói được cho ai biết phải làm gì.
    fakeNet()->push(['error' => -230, 'message' => 'User is not in whitelist']);
    $oa = anOa();

    test()->withHeaders(auth2())
        ->post(route('zalo.oas.send', $oa), ['user_id' => 'u-1', 'text' => 'chào'])
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, '48 giờ'));
});

it('gửi kèm attachment_id thì dùng media template', function (): void {
    $fake = fakeNet()->push(['error' => 0, 'data' => []]);
    $oa = anOa();

    test()->withHeaders(auth2())->post(route('zalo.oas.send', $oa), [
        'user_id' => 'u-1',
        'text' => 'Ảnh sản phẩm',
        'attachment_id' => 'att-9',
    ])->assertSessionHas('zalo.success');

    $payload = $fake->lastRequest()['data']['message']['attachment']['payload'];

    expect($payload['template_type'])->toBe('media')
        ->and($payload['elements'][0]['attachment_id'])->toBe('att-9');
});
