<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;
use FieldVn\Zalo\Laravel\Events\ZaloBotMessageReceived;
use FieldVn\Zalo\Laravel\Events\ZaloBotUpdateReceived;
use FieldVn\Zalo\Laravel\Jobs\HandleZaloBotWebhook;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

const BOT_SECRET = 'secret-du-dai-cho-zalo-32-ky-tu';

beforeEach(function (): void {
    config()->set('zalo.bot.webhook_secret', BOT_SECRET);
    config()->set('zalo.webhook.queue', false);
});

function makeBot(string $slug = 'cskh-bot'): ZaloBot
{
    return ZaloBot::create([
        'name' => 'Bot CSKH',
        'slug' => $slug,
        'token' => '123:abc',
    ]);
}

/** @param array<string, mixed> $payload */
function postBotWebhook(ZaloBot $bot, array $payload = [], ?string $secret = BOT_SECRET): \Illuminate\Testing\TestResponse
{
    $headers = $secret === null ? [] : [BotSecretVerifier::HEADER => $secret];

    return test()->postJson("/zalo/webhook/bot/{$bot->slug}", $payload, $headers);
}

/** @return array<string, mixed> */
function botMessagePayload(string $chatId = 'chat-1', string $text = 'xin chào'): array
{
    return [
        'update_id' => '99',
        'message' => [
            'message_id' => 'm-1',
            'text' => $text,
            'chat' => ['id' => $chatId],
            'from' => ['id' => 'u-1', 'display_name' => 'Nguyễn Văn A'],
        ],
    ];
}

/*
| Nhóm quan trọng nhất: fail-closed.
|
| Fail-closed ở đây nghĩa là KHÔNG XỬ LÝ, không phải trả mã lỗi. Nền tảng chỉ
| chấp nhận webhook URL nào trả 200, nên trả 401 khiến webhook không bao giờ
| thiết lập được. Điều cần khoá lại là: không bắn event, không ghi DB.
*/

it('KHÔNG XỬ LÝ khi thiếu header secret', function (): void {
    $bot = makeBot();
    Event::fake();

    postBotWebhook($bot, botMessagePayload(), secret: null)
        ->assertOk()
        ->assertJson(['processed' => false]);

    Event::assertNotDispatched(ZaloBotUpdateReceived::class);
    expect(ZaloBotChat::query()->count())->toBe(0);
});

it('KHÔNG XỬ LÝ khi secret sai', function (): void {
    $bot = makeBot();
    Event::fake();

    postBotWebhook($bot, botMessagePayload(), secret: 'secret-sai-nhung-du-do-dai-32')
        ->assertOk()
        ->assertJson(['processed' => false]);

    Event::assertNotDispatched(ZaloBotUpdateReceived::class);
});

it('KHÔNG XỬ LÝ khi ứng dụng chưa cấu hình secret', function (): void {
    // Chưa cấu hình thì không phân biệt được webhook thật với request giả.
    config()->set('zalo.bot.webhook_secret', null);
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload(), secret: 'bat-ky-thu-gi-du-dai-32-ky-tu')
        ->assertOk()
        ->assertJson(['processed' => false]);

    expect(ZaloBotChat::query()->count())->toBe(0);
});

it('KHÔNG XỬ LÝ khi secret cấu hình quá ngắn', function (): void {
    // Secret ngắn dò được, nên coi như chưa cấu hình.
    config()->set('zalo.bot.webhook_secret', 'ngan');
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload(), secret: 'ngan')
        ->assertOk()
        ->assertJson(['processed' => false]);

    expect(ZaloBotChat::query()->count())->toBe(0);
});

/*
| Luồng thành công.
*/

it('nhận webhook hợp lệ và bắn event', function (): void {
    $bot = makeBot();
    Event::fake([ZaloBotUpdateReceived::class, ZaloBotMessageReceived::class]);

    postBotWebhook($bot, botMessagePayload())->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(ZaloBotUpdateReceived::class);
    Event::assertDispatched(
        ZaloBotMessageReceived::class,
        fn (ZaloBotMessageReceived $e): bool => $e->chatId === 'chat-1'
            && $e->text === 'xin chào'
            && $e->bot->slug === 'cskh-bot',
    );
});

it('LƯU chat_id — đây là lý do tồn tại của cả slice này', function (): void {
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload('chat-777'))->assertOk();

    $chat = ZaloBotChat::query()->first();

    expect($chat)->not->toBeNull()
        ->and($chat->chat_id)->toBe('chat-777')
        ->and($chat->display_name)->toBe('Nguyễn Văn A')
        ->and($chat->last_message)->toBe('xin chào')
        ->and($chat->message_count)->toBe(1);
});

it('cùng một người nhắn nhiều lần chỉ tạo MỘT dòng', function (): void {
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload('chat-1', 'lần một'))->assertOk();
    postBotWebhook($bot, botMessagePayload('chat-1', 'lần hai'))->assertOk();

    expect(ZaloBotChat::query()->count())->toBe(1);

    $chat = ZaloBotChat::query()->first();

    expect($chat->message_count)->toBe(2)
        ->and($chat->last_message)->toBe('lần hai');
});

it('KHÔNG mất tên đã biết khi update sau không kèm tên', function (): void {
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload())->assertOk();
    postBotWebhook($bot, ['message' => ['text' => 'trống tên', 'chat' => ['id' => 'chat-1']]])->assertOk();

    expect(ZaloBotChat::query()->first()->display_name)->toBe('Nguyễn Văn A');
});

it('phân biệt được bot nào nhận, nhờ URL riêng từng bot', function (): void {
    // Payload của Zalo không kèm định danh bot — nếu dùng chung một URL thì
    // không có cách nào biết update thuộc bot nào.
    $a = makeBot('bot-a');
    $b = makeBot('bot-b');

    postBotWebhook($a, botMessagePayload('chat-a'))->assertOk();
    postBotWebhook($b, botMessagePayload('chat-b'))->assertOk();

    expect(ZaloBotChat::query()->where('bot_id', $a->id)->value('chat_id'))->toBe('chat-a')
        ->and(ZaloBotChat::query()->where('bot_id', $b->id)->value('chat_id'))->toBe('chat-b');
});

it('404 khi slug bot không tồn tại', function (): void {
    test()->postJson('/zalo/webhook/bot/khong-co', botMessagePayload(), [
        BotSecretVerifier::HEADER => BOT_SECRET,
    ])->assertNotFound();
});

it('đẩy vào queue khi bật, không xử lý ngay', function (): void {
    config()->set('zalo.webhook.queue', true);
    Queue::fake();
    $bot = makeBot();

    postBotWebhook($bot, botMessagePayload())->assertOk();

    Queue::assertPushed(
        HandleZaloBotWebhook::class,
        fn (HandleZaloBotWebhook $job): bool => $job->botId === $bot->id,
    );
});

it('update không có chat_id vẫn trả 200, không tạo dòng rác', function (): void {
    // Không phải mọi update đều gắn với hội thoại. Trả lỗi ở đây khiến Zalo
    // gửi lại mãi không thôi.
    $bot = makeBot();

    postBotWebhook($bot, ['update_id' => '1', 'some_event' => true])->assertOk();

    expect(ZaloBotChat::query()->count())->toBe(0);
});
