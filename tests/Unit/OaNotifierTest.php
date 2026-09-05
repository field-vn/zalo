<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Auth\TokenPair;
use FieldVn\Zalo\Core\Channels\OA\NotifyResult;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Channels\OA\OaNotifier;
use FieldVn\Zalo\Core\Channels\OA\ZaloOutboundMessage;
use FieldVn\Zalo\Core\Channels\OA\ZaloRecipient;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;
use FieldVn\Zalo\Tests\Support\FakeTransport;

function notifierFakeTransport(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);
    app(Factory::class)->forgetResolved();

    return $fake;
}

function notifierOa(array $attributes = []): ZaloOa
{
    return ZaloOa::create(array_merge([
        'name' => 'CSKH',
        'slug' => 'cskh',
        'oa_id' => 'oa-1',
        'is_active' => true,
    ], $attributes));
}

function putNotifierToken(ZaloOa $oa, DateTimeImmutable $expiresAt): void
{
    (new EloquentTokenStore($oa))->put(new TokenPair(
        'access-token',
        'refresh-token',
        $expiresAt,
        new DateTimeImmutable('+90 days'),
    ));
}

function resolveNotifierChannel(string $slug = 'cskh'): OAChannel
{
    return app(Factory::class)->oa($slug);
}

/** @return list<array{method:string, url:string, data:array<string,mixed>, headers:array<string,string>}> */
function requestsMatching(FakeTransport $fake, string $needle): array
{
    return array_values(array_filter(
        $fake->requests,
        fn (array $req): bool => str_contains($req['url'], $needle),
    ));
}

it('user id + token remaining 48h + text → CS, không gọi ZBS', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => 0, 'data' => ['message_id' => 'cs-1']]);

    $oa = notifierOa();
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));
    $channel = resolveNotifierChannel();

    $result = $channel->notifier()->send(
        new ZaloRecipient(zaloUserId: 'user-1'),
        new ZaloOutboundMessage(text: 'Xin chào'),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_OA_CS)
        ->and($result->messageId)->toBe('cs-1')
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(1)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(0);
});

it('user id + token remaining 30 phút + phone + template → ZBS, không CS', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => 0, 'data' => ['msg_id' => 'zbs-1']]);

    $oa = notifierOa(['slug' => 'cskh-stale', 'oa_id' => 'oa-stale']);
    putNotifierToken($oa, new DateTimeImmutable('+30 minutes'));
    $channel = resolveNotifierChannel('cskh-stale');

    $result = $channel->notifier()->send(
        new ZaloRecipient(zaloUserId: 'user-1', phone: '0987654321'),
        new ZaloOutboundMessage(
            text: 'sẽ không gửi CS',
            templateId: 'tpl-1',
            templateData: ['otp' => '123456'],
        ),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_ZBS)
        ->and($result->messageId)->toBe('zbs-1')
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(0)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(1);
});

it('user id + token tươi + contact is_following=false + phone → ZBS', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => 0, 'data' => ['msg_id' => 'zbs-unfollow']]);

    $oa = notifierOa(['slug' => 'cskh-unfollow', 'oa_id' => 'oa-unfollow']);
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));

    ZaloContact::create([
        'oa_id' => $oa->getKey(),
        'zalo_user_id' => 'user-1',
        'is_following' => false,
        'first_seen_at' => now()->subDays(2),
        'last_interaction_at' => now()->subHour(),
    ]);

    $channel = resolveNotifierChannel('cskh-unfollow');

    $result = $channel->notifier()->send(
        new ZaloRecipient(zaloUserId: 'user-1', phone: '0987654321'),
        new ZaloOutboundMessage(
            text: 'không CS vì unfollow',
            templateId: 'tpl-1',
            templateData: ['name' => 'Sam'],
        ),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_ZBS)
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(0)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(1);
});

it('user id + token tươi + CS throw → failed oa_cs, ZBS 0 lần', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => -230, 'message' => 'User has not followed this OA']);

    $oa = notifierOa(['slug' => 'cskh-cs-fail', 'oa_id' => 'oa-cs-fail']);
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));
    $channel = resolveNotifierChannel('cskh-cs-fail');

    $result = $channel->notifier()->send(
        new ZaloRecipient(zaloUserId: 'user-1', phone: '0987654321'),
        new ZaloOutboundMessage(
            text: 'thử CS',
            templateId: 'tpl-1',
            templateData: ['otp' => '999'],
        ),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_OA_CS)
        ->and($result->reason)->toContain('User has not followed')
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(1)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(0);
});

it('chỉ phone, không user id → ZBS', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => 0, 'data' => ['msg_id' => 'zbs-phone']]);

    $oa = notifierOa(['slug' => 'cskh-phone', 'oa_id' => 'oa-phone']);
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));
    $channel = resolveNotifierChannel('cskh-phone');

    $result = $channel->notifier()->send(
        new ZaloRecipient(phone: '0987654321'),
        new ZaloOutboundMessage(
            templateId: 'tpl-1',
            templateData: ['otp' => '111'],
        ),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_ZBS)
        ->and($result->messageId)->toBe('zbs-phone')
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(0)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(1);
});

it('rỗng cả hai → skipped recipient_empty', function (): void {
    notifierFakeTransport();
    $oa = notifierOa(['slug' => 'cskh-empty', 'oa_id' => 'oa-empty']);
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));
    $channel = resolveNotifierChannel('cskh-empty');

    $result = $channel->notifier()->send(
        new ZaloRecipient,
        new ZaloOutboundMessage(text: 'không ai nhận'),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_NONE)
        ->and($result->reason)->toBe('recipient_empty')
        ->and($channel->notifier())->toBeInstanceOf(OaNotifier::class);
});

it('following nhưng last_interaction_at quá cs_window_days → ZBS, không CS', function (): void {
    $fake = notifierFakeTransport()
        ->push(['error' => 0, 'data' => ['msg_id' => 'zbs-window']]);

    $oa = notifierOa(['slug' => 'cskh-window', 'oa_id' => 'oa-window']);
    putNotifierToken($oa, new DateTimeImmutable('+48 hours'));

    ZaloContact::create([
        'oa_id' => $oa->getKey(),
        'zalo_user_id' => 'user-1',
        'is_following' => true,
        'first_seen_at' => now()->subDays(30),
        'last_interaction_at' => now()->subDays(8),
    ]);

    $channel = resolveNotifierChannel('cskh-window');

    $result = $channel->notifier()->send(
        new ZaloRecipient(zaloUserId: 'user-1', phone: '0987654321'),
        new ZaloOutboundMessage(
            text: 'ngoài cửa sổ CS',
            templateId: 'tpl-1',
            templateData: ['otp' => '4242'],
        ),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->channel)->toBe(NotifyResult::CHANNEL_ZBS)
        ->and($result->messageId)->toBe('zbs-window')
        ->and(requestsMatching($fake, '/v3.0/oa/message/cs'))->toHaveCount(0)
        ->and(requestsMatching($fake, '/message/template'))->toHaveCount(1);
});
