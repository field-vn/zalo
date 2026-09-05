<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerAdded;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerRemoved;
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\WebhookDispatcher;
use FieldVn\Zalo\Support\Table;

function makeContactOa(array $attributes = []): ZaloOa
{
    return ZaloOa::create(array_merge([
        'name' => 'CSKH Contact',
        'slug' => 'cskh-contact',
        'oa_id' => 'oa-contact-1',
        'is_active' => true,
    ], $attributes));
}

/** @param array<string, mixed> $overrides */
function followPayload(array $overrides = []): array
{
    return array_merge([
        'app_id' => 'app-1',
        'event_name' => 'follow',
        'timestamp' => (string) (time() * 1000),
        'oa_id' => 'oa-contact-1',
        'follower' => ['id' => 'user-follow-1'],
    ], $overrides);
}

/** @param array<string, mixed> $overrides */
function unfollowPayload(array $overrides = []): array
{
    return array_merge([
        'app_id' => 'app-1',
        'event_name' => 'unfollow',
        'timestamp' => (string) (time() * 1000),
        'oa_id' => 'oa-contact-1',
        'follower' => ['id' => 'user-follow-1'],
    ], $overrides);
}

/** @param array<string, mixed> $overrides */
function userMessagePayload(array $overrides = []): array
{
    return array_merge([
        'app_id' => 'app-1',
        'event_name' => 'user_send_text',
        'timestamp' => (string) (time() * 1000),
        'sender' => ['id' => 'user-msg-1'],
        'recipient' => ['id' => 'oa-contact-1'],
        'message' => ['text' => 'Xin chào', 'msg_id' => 'msg-1'],
    ], $overrides);
}

it('ghi contact khi nhận ZaloFollowerAdded', function (): void {
    $oa = makeContactOa();
    $event = WebhookEvent::fromPayload(followPayload());

    ZaloFollowerAdded::dispatch($event, $oa, $event->userId());

    $contact = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-follow-1')
        ->first();

    expect($contact)->not->toBeNull()
        ->and($contact->is_following)->toBeTrue()
        ->and(Table::name(Table::CONTACTS))->toBe('zl_contacts');
});

it('unfollow đặt is_following=false và giữ row', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-unfollow', 'oa_id' => 'oa-unfollow']);
    $follow = WebhookEvent::fromPayload(followPayload([
        'oa_id' => 'oa-unfollow',
        'follower' => ['id' => 'user-unfollow-1'],
    ]));

    ZaloFollowerAdded::dispatch($follow, $oa, $follow->userId());

    $unfollow = WebhookEvent::fromPayload(unfollowPayload([
        'oa_id' => 'oa-unfollow',
        'follower' => ['id' => 'user-unfollow-1'],
    ]));

    ZaloFollowerRemoved::dispatch($unfollow, $oa, $unfollow->userId());

    $contact = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-unfollow-1')
        ->first();

    expect($contact)->not->toBeNull()
        ->and($contact->is_following)->toBeFalse();
});

it('unfollow khi chưa có row thì không tạo mới', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-unfollow-missing', 'oa_id' => 'oa-unf-miss']);
    $unfollow = WebhookEvent::fromPayload(unfollowPayload([
        'oa_id' => 'oa-unf-miss',
        'follower' => ['id' => 'ghost-user'],
    ]));

    ZaloFollowerRemoved::dispatch($unfollow, $oa, $unfollow->userId());

    expect(
        ZaloContact::query()
            ->where('oa_id', $oa->getKey())
            ->where('zalo_user_id', 'ghost-user')
            ->exists()
    )->toBeFalse();
});

it('tin nhắn từ user cập nhật last_interaction_at', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-msg', 'oa_id' => 'oa-msg']);
    $event = WebhookEvent::fromPayload(userMessagePayload([
        'recipient' => ['id' => 'oa-msg'],
        'sender' => ['id' => 'user-msg-1'],
    ]));

    ZaloMessageReceived::dispatch($event, $oa, $event->userId(), $event->text(), $event->messageId());

    $contact = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-msg-1')
        ->first();

    expect($contact)->not->toBeNull()
        ->and($contact->last_interaction_at)->not->toBeNull();
});

it('bỏ qua khi thiếu userId', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-noid', 'oa_id' => 'oa-noid']);
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'follow',
        'timestamp' => (string) (time() * 1000),
        'oa_id' => 'oa-noid',
        // không có follower / sender
    ]);

    ZaloFollowerAdded::dispatch($event, $oa, null);

    expect(ZaloContact::query()->where('oa_id', $oa->getKey())->count())->toBe(0);
});

it('bỏ qua khi OA null', function (): void {
    $event = WebhookEvent::fromPayload(followPayload());

    ZaloFollowerAdded::dispatch($event, null, $event->userId());

    expect(ZaloContact::query()->count())->toBe(0);
});

it('user_received_message chỉ touch last_interaction_at, không đổi is_following', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-recv', 'oa_id' => 'oa-recv']);
    $follow = WebhookEvent::fromPayload(followPayload([
        'oa_id' => 'oa-recv',
        'follower' => ['id' => 'user-recv-1'],
    ]));
    ZaloFollowerAdded::dispatch($follow, $oa, $follow->userId());

    $unfollow = WebhookEvent::fromPayload(unfollowPayload([
        'oa_id' => 'oa-recv',
        'follower' => ['id' => 'user-recv-1'],
    ]));
    ZaloFollowerRemoved::dispatch($unfollow, $oa, $unfollow->userId());

    $before = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-recv-1')
        ->firstOrFail();

    expect($before->is_following)->toBeFalse();

    $oldInteraction = $before->last_interaction_at->copy()->subMinute();
    $before->forceFill(['last_interaction_at' => $oldInteraction])->save();

    // Real Zalo shape: OA→user delivery receipt (sender=OA, recipient=user).
    $received = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_received_message',
        'timestamp' => (string) (time() * 1000),
        'oa_id' => 'oa-recv',
        'sender' => ['id' => 'oa-recv'],
        'recipient' => ['id' => 'user-recv-1'],
        'user_id_by_app' => 'user-recv-1',
        'message' => ['msg_id' => 'msg-r1'],
    ]);

    expect($received->oaId)->toBe('oa-recv')
        ->and($received->userId())->toBe('user-recv-1');

    app(WebhookDispatcher::class)->dispatch($received);

    $after = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-recv-1')
        ->firstOrFail();

    expect($after->is_following)->toBeFalse()
        ->and($after->last_interaction_at->greaterThan($oldInteraction))->toBeTrue()
        ->and(
            ZaloContact::query()
                ->where('oa_id', $oa->getKey())
                ->where('zalo_user_id', 'oa-recv')
                ->exists()
        )->toBeFalse();
});

it('user_received_message khớp recipient.id dù user_id_by_app khác', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-recv-oa-id', 'oa_id' => 'oa-recv-oa']);
    $follow = WebhookEvent::fromPayload(followPayload([
        'oa_id' => 'oa-recv-oa',
        'follower' => ['id' => 'user-200'],
    ]));
    ZaloFollowerAdded::dispatch($follow, $oa, $follow->userId());

    $before = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-200')
        ->firstOrFail();

    $oldInteraction = $before->last_interaction_at->copy()->subMinute();
    $before->forceFill(['last_interaction_at' => $oldInteraction])->save();

    $received = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'user_received_message',
        'timestamp' => (string) (time() * 1000),
        'oa_id' => 'oa-recv-oa',
        'sender' => ['id' => 'oa-recv-oa'],
        'recipient' => ['id' => 'user-200'],
        'user_id_by_app' => 'app-scoped-999',
        'message' => ['msg_id' => 'msg-r-oa'],
    ]);

    expect($received->userId())->toBe('user-200');

    app(WebhookDispatcher::class)->dispatch($received);

    $after = ZaloContact::query()
        ->where('oa_id', $oa->getKey())
        ->where('zalo_user_id', 'user-200')
        ->firstOrFail();

    expect($after->last_interaction_at->greaterThan($oldInteraction))->toBeTrue()
        ->and(
            ZaloContact::query()
                ->where('oa_id', $oa->getKey())
                ->where('zalo_user_id', 'app-scoped-999')
                ->exists()
        )->toBeFalse()
        ->and(
            ZaloContact::query()->where('oa_id', $oa->getKey())->count()
        )->toBe(1);
});

it('oa_send_* không tạo contact qua handleMessage', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-oa-send', 'oa_id' => 'oa-send']);
    $event = WebhookEvent::fromPayload([
        'app_id' => 'app-1',
        'event_name' => 'oa_send_text',
        'timestamp' => (string) (time() * 1000),
        'sender' => ['id' => 'oa-send'],
        'recipient' => ['id' => 'user-oa-target'],
        'message' => ['text' => 'OA gửi', 'msg_id' => 'msg-oa'],
    ]);

    ZaloMessageReceived::dispatch($event, $oa, 'user-oa-target', $event->text(), $event->messageId());

    expect(
        ZaloContact::query()
            ->where('oa_id', $oa->getKey())
            ->exists()
    )->toBeFalse();
});

it('prune xoá cứng contact unfollow quá hạn', function (): void {
    $oa = makeContactOa(['slug' => 'cskh-prune', 'oa_id' => 'oa-prune']);

    $stale = ZaloContact::query()->create([
        'oa_id' => $oa->getKey(),
        'zalo_user_id' => 'stale-user',
        'is_following' => false,
        'first_seen_at' => now()->subDays(200),
        'last_interaction_at' => now()->subDays(200),
    ]);

    $freshUnfollow = ZaloContact::query()->create([
        'oa_id' => $oa->getKey(),
        'zalo_user_id' => 'fresh-unfollow',
        'is_following' => false,
        'first_seen_at' => now()->subDays(10),
        'last_interaction_at' => now()->subDays(10),
    ]);

    $following = ZaloContact::query()->create([
        'oa_id' => $oa->getKey(),
        'zalo_user_id' => 'still-following',
        'is_following' => true,
        'first_seen_at' => now()->subDays(200),
        'last_interaction_at' => now()->subDays(200),
    ]);

    $this->artisan('zalo:contacts:prune', ['--days' => 180])->assertSuccessful();

    expect(ZaloContact::query()->find($stale->id))->toBeNull()
        ->and(ZaloContact::query()->find($freshUnfollow->id))->not->toBeNull()
        ->and(ZaloContact::query()->find($following->id))->not->toBeNull();
});
