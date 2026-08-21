<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Events\ZaloFollowerAdded;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerRemoved;
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;
use FieldVn\Zalo\Laravel\Events\ZaloWebhookReceived;
use FieldVn\Zalo\Laravel\Jobs\HandleZaloWebhook;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config()->set('zalo.apps.default.app_id', 'app-1');
    config()->set('zalo.apps.default.webhook_secret', 'wh-secret');
    config()->set('zalo.webhook.queue', false);
});

/** @param array<string, mixed> $payload */
function postWebhook(array $payload, ?string $signature = null, ?string $body = null): TestResponse
{
    $raw = $body ?? (string) json_encode($payload);
    $ts = (string) ($payload['timestamp'] ?? (time() * 1000));

    $signature ??= 'mac='.hash('sha256', 'app-1'.$raw.$ts.'wh-secret');

    return test()->call(
        'POST',
        '/zalo/webhook',
        [],
        [],
        [],
        ['HTTP_X_ZEVENT_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $raw,
    );
}

/** Không sự kiện Zalo nào được bắn — bỏ qua event nội bộ của Laravel. */
function assertNoZaloEvents(): void
{
    Event::assertNotDispatched(ZaloWebhookReceived::class);
    Event::assertNotDispatched(ZaloMessageReceived::class);
    Event::assertNotDispatched(ZaloFollowerAdded::class);
    Event::assertNotDispatched(ZaloFollowerRemoved::class);
}

/** @return array<string, mixed> */
function messagePayload(string $text = 'Xin chào'): array
{
    return [
        'app_id' => 'app-1',
        'event_name' => 'user_send_text',
        'timestamp' => (string) (time() * 1000),
        'sender' => ['id' => 'user-123'],
        'recipient' => ['id' => 'oa-999'],
        'message' => ['text' => $text, 'msg_id' => 'msg-1'],
    ];
}

it('nhận webhook có chữ ký hợp lệ', function (): void {
    Event::fake();

    postWebhook(messagePayload())->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(ZaloWebhookReceived::class);
    Event::assertDispatched(ZaloMessageReceived::class);
});

it('từ chối 401 khi chữ ký sai', function (): void {
    Event::fake();

    postWebhook(messagePayload(), 'mac=chu-ky-gia-mao')->assertStatus(401);

    assertNoZaloEvents();
});

it('từ chối 401 khi thiếu header chữ ký', function (): void {
    Event::fake();

    $payload = messagePayload();
    $raw = (string) json_encode($payload);

    $this->call('POST', '/zalo/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)
        ->assertStatus(401);

    assertNoZaloEvents();
});

it('FAIL-CLOSED khi chưa cấu hình webhook secret', function (): void {
    config()->set('zalo.apps.default.webhook_secret', null);
    Event::fake();

    postWebhook(messagePayload())->assertStatus(401);

    assertNoZaloEvents();
});

it('gắn đúng OA vào event khi OA đã được quản lý', function (): void {
    Event::fake();

    ZaloOa::create(['name' => 'CSKH', 'slug' => 'cskh', 'oa_id' => 'oa-999']);

    postWebhook(messagePayload())->assertOk();

    Event::assertDispatched(
        ZaloMessageReceived::class,
        fn (ZaloMessageReceived $e): bool => $e->oa?->slug === 'cskh'
            && $e->userId === 'user-123'
            && $e->text === 'Xin chào'
            && $e->messageId === 'msg-1',
    );
});

it('vẫn xử lý webhook từ OA chưa được thêm vào hệ thống', function (): void {
    // Không phải lỗi — chỉ là OA đó chưa được quản lý. Nuốt im lặng thì
    // người dùng không bao giờ biết mình cấu hình webhook nhầm OA.
    Event::fake();

    postWebhook(messagePayload())->assertOk();

    Event::assertDispatched(
        ZaloMessageReceived::class,
        fn (ZaloMessageReceived $e): bool => $e->oa === null,
    );
});

it('bắn ZaloFollowerAdded cho sự kiện follow', function (): void {
    Event::fake();

    postWebhook([
        'app_id' => 'app-1',
        'oa_id' => 'oa-999',
        'event_name' => 'follow',
        'timestamp' => (string) (time() * 1000),
        'follower' => ['id' => 'user-456'],
    ])->assertOk();

    Event::assertDispatched(
        ZaloFollowerAdded::class,
        fn (ZaloFollowerAdded $e): bool => $e->userId === 'user-456',
    );
    Event::assertNotDispatched(ZaloMessageReceived::class);
});

it('bắn ZaloFollowerRemoved cho sự kiện unfollow', function (): void {
    Event::fake();

    postWebhook([
        'app_id' => 'app-1',
        'oa_id' => 'oa-999',
        'event_name' => 'unfollow',
        'timestamp' => (string) (time() * 1000),
        'follower' => ['id' => 'user-456'],
    ])->assertOk();

    Event::assertDispatched(ZaloFollowerRemoved::class);
});

it('vẫn bắn event chung cho event_name package chưa bọc riêng', function (): void {
    // Zalo còn thêm loại sự kiện mới; người dùng không được phải chờ package
    // cập nhật mới bắt được chúng.
    Event::fake();

    postWebhook([
        'app_id' => 'app-1',
        'oa_id' => 'oa-999',
        'event_name' => 'mot_su_kien_hoan_toan_moi',
        'timestamp' => (string) (time() * 1000),
    ])->assertOk();

    Event::assertDispatched(
        ZaloWebhookReceived::class,
        fn (ZaloWebhookReceived $e): bool => $e->event->name === 'mot_su_kien_hoan_toan_moi',
    );
    Event::assertNotDispatched(ZaloMessageReceived::class);
});

it('đẩy sang queue khi bật webhook.queue', function (): void {
    config()->set('zalo.webhook.queue', true);
    Queue::fake();

    postWebhook(messagePayload())->assertOk();

    Queue::assertPushed(HandleZaloWebhook::class);
});

it('vẫn trả 200 khi listener của dự án ném exception', function (): void {
    // Trả 500 sẽ khiến Zalo gửi lại cùng một sự kiện → xử lý trùng.
    // Lỗi của dự án không được biến thành lỗi giao thức.
    Event::listen(ZaloWebhookReceived::class, function (): void {
        throw new RuntimeException('listener hỏng');
    });

    postWebhook(messagePayload())->assertOk();
});

it('route webhook KHÔNG bị chặn bởi basic auth của UI', function (): void {
    // Zalo không đăng nhập được — nếu route này nằm sau auth thì webhook chết.
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
    Event::fake();

    postWebhook(messagePayload())->assertOk();
});
