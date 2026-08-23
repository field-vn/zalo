<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| URL của Bot API là thứ dễ sai và khó phát hiện nhất: sai một dấu `/` thì
| Zalo vẫn trả HTTP 200, chỉ khác body — nên không có test nào chạm vào URL
| là lỗi sống sót qua cả bộ test.
|
| Chuẩn (giống Telegram): https://bot-api.zapps.me/bot<token>/<method>
*/

function botWith(FakeTransport $t, string $token = 'TOKEN123'): BotChannel
{
    return new BotChannel(
        slug: 'test-bot',
        transport: $t,
        token: $token,
        baseUrl: 'https://bot-api.zapps.me/bot',
    );
}

it('token DÍNH LIỀN chữ bot, không có dấu gạch chéo ở giữa', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => true, 'result' => ['username' => 'x']]);

    botWith($t)->me();

    expect($t->lastRequest()['url'])->toBe('https://bot-api.zapps.me/botTOKEN123/getMe');
});

it('baseUrl thừa dấu / cuối vẫn ra URL đúng', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => true, 'result' => []]);

    (new BotChannel(
        slug: 'test-bot',
        transport: $t,
        token: 'TOKEN123',
        baseUrl: 'https://bot-api.zapps.me/bot/',
    ))->me();

    expect($t->lastRequest()['url'])->toBe('https://bot-api.zapps.me/botTOKEN123/getMe');
});

it('sendMessage đi đúng endpoint kèm chat_id và text', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => true, 'result' => ['message_id' => 7]]);

    botWith($t)->messages()->send('chat-1', 'xin chào');

    $req = $t->lastRequest();

    expect($req['url'])->toBe('https://bot-api.zapps.me/botTOKEN123/sendMessage')
        ->and($req['method'])->toBe('POST')
        ->and($req['data'])->toBe(['chat_id' => 'chat-1', 'text' => 'xin chào']);
});

it('getUpdates đi đúng endpoint kèm offset và timeout', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => true, 'result' => []]);

    botWith($t)->updates()->poll(5, 30);

    $req = $t->lastRequest();

    expect($req['url'])->toBe('https://bot-api.zapps.me/botTOKEN123/getUpdates')
        ->and($req['data'])->toBe(['offset' => 5, 'timeout' => 30]);
});

it('setWebhook LUÔN gửi secret_token', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => true, 'result' => true]);

    botWith($t)->updates()->setWebhook('https://x.test/zalo/webhook', 'bi-mat-du-dai-32-ky-tu-abcdef');

    $req = $t->lastRequest();

    expect($req['url'])->toBe('https://bot-api.zapps.me/botTOKEN123/setWebhook')
        ->and($req['data'])->toBe([
            'url' => 'https://x.test/zalo/webhook',
            'secret_token' => 'bi-mat-du-dai-32-ky-tu-abcdef',
        ]);
});

it('setWebhook chặn secret rỗng hoặc quá ngắn, không gọi mạng', function (string $secret): void {
    // Zalo trả "The secret_token must not be empty" / từ chối độ dài — cả hai
    // thông báo đều không nói phải đặt biến env nào, nên chặn sớm tại chỗ.
    $t = new FakeTransport;

    expect(fn () => botWith($t)->updates()->setWebhook('https://x.test/hook', $secret))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
})->with([
    'rỗng' => '',
    'toàn khoảng trắng' => '        ',
    'ngắn hơn 8' => 'abc123',
    'dài hơn 256' => [str_repeat('a', 257)],
]);

it('ping() trả false khi Zalo báo ok=false chứ không ném ra ngoài', function (): void {
    $t = new FakeTransport;
    $t->push(['ok' => false, 'description' => 'Not Found', 'error_code' => 404]);

    expect(botWith($t)->ping())->toBeFalse();
});
