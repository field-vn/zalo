<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Webhook\WebhookEvent;

it('user_received_message: OA từ oa_id, user từ recipient.id (không lấy user_id_by_app)', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_received_message',
        'timestamp' => '1',
        'oa_id' => 'oa-100',
        'sender' => ['id' => 'oa-100'],
        'recipient' => ['id' => 'user-200'],
        'user_id_by_app' => 'user-by-app-200',
        'message' => ['msg_id' => 'msg-1'],
    ]);

    expect($event->oaId)->toBe('oa-100')
        ->and($event->userId())->toBe('user-200');
});

it('user_received_message không có oa_id: OA = sender.id, user = recipient.id', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_received_message',
        'timestamp' => '1',
        'sender' => ['id' => 'oa-100'],
        'recipient' => ['id' => 'user-200'],
        'message' => ['msg_id' => 'msg-1'],
    ]);

    expect($event->oaId)->toBe('oa-100')
        ->and($event->userId())->toBe('user-200');
});

it('user_seen_message: OA từ oa_id / sender, user từ recipient (không lấy sender)', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_seen_message',
        'timestamp' => '1',
        'oa_id' => 'oa-seen',
        'sender' => ['id' => 'oa-seen'],
        'recipient' => ['id' => 'user-seen'],
        'message' => ['msg_id' => 'msg-seen'],
    ]);

    expect($event->oaId)->toBe('oa-seen')
        ->and($event->userId())->toBe('user-seen');
});

it('user_send_text giữ sender=user, recipient=OA', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_send_text',
        'timestamp' => '1',
        'sender' => ['id' => 'user-send'],
        'recipient' => ['id' => 'oa-send-target'],
        'message' => ['text' => 'hi', 'msg_id' => 'msg-s'],
    ]);

    expect($event->oaId)->toBe('oa-send-target')
        ->and($event->userId())->toBe('user-send');
});

it('follow dùng oa_id và follower.id', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'follow',
        'timestamp' => '1',
        'oa_id' => 'oa-follow',
        'follower' => ['id' => 'user-follow'],
    ]);

    expect($event->oaId)->toBe('oa-follow')
        ->and($event->userId())->toBe('user-follow');
});

it('oa_send_text lấy OA từ sender.id', function (): void {
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'oa_send_text',
        'timestamp' => '1',
        'sender' => ['id' => 'oa-echo'],
        'recipient' => ['id' => 'user-echo'],
        'message' => ['text' => 'echo', 'msg_id' => 'msg-e'],
    ]);

    expect($event->oaId)->toBe('oa-echo');
});
