<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Auth\TokenPair;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;
use FieldVn\Zalo\Laravel\Support\TokenStatusCache;
use Illuminate\Support\Facades\Cache;

function makeTokenStatusOa(array $attributes = []): ZaloOa
{
    return ZaloOa::create(array_merge([
        'name' => 'CSKH Shop',
        'slug' => 'cskh-token-status',
        'oa_id' => '9876543210',
        'is_active' => true,
    ], $attributes));
}

it('caches token status and invalidates on put', function (): void {
    $oa = makeTokenStatusOa();
    $store = new EloquentTokenStore($oa);
    $cacheKey = 'zalo.token_status.'.$oa->getKey();

    $firstExpires = new DateTimeImmutable('+2 hours');
    $store->put(new TokenPair('access-1', 'refresh-1', $firstExpires, new DateTimeImmutable('+90 days')));

    $channel = app(Factory::class)->oa($oa->slug);
    $first = $channel->tokenStatus();

    expect($first->present)->toBeTrue()
        ->and(Cache::has($cacheKey))->toBeTrue();

    // Đổi expires trên DB mà không đi qua store → cache phải giữ giá trị cũ.
    ZaloOaToken::query()->where('oa_id', $oa->getKey())->update([
        'expires_at' => new DateTimeImmutable('+10 hours'),
    ]);

    $cached = $channel->tokenStatus();

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($cached->expiresAt?->getTimestamp())->toBe($first->expiresAt?->getTimestamp());

    $secondExpires = new DateTimeImmutable('+5 hours');
    $store->put(new TokenPair('access-2', 'refresh-2', $secondExpires, new DateTimeImmutable('+90 days')));

    expect(Cache::has($cacheKey))->toBeFalse();

    $fresh = $channel->tokenStatus();

    expect($fresh->present)->toBeTrue()
        ->and($fresh->expiresAt?->getTimestamp())->toBe($secondExpires->getTimestamp())
        ->and(Cache::has($cacheKey))->toBeTrue();
});

it('skips cache when ttl is zero', function (): void {
    config()->set('zalo.notifier.token_status_cache_ttl', 0);

    $oa = makeTokenStatusOa(['slug' => 'cskh-ttl-zero', 'oa_id' => '111']);
    $store = new EloquentTokenStore($oa);
    $cacheKey = 'zalo.token_status.'.$oa->getKey();

    $store->put(new TokenPair(
        'access',
        'refresh',
        new DateTimeImmutable('+90 minutes'),
        new DateTimeImmutable('+90 days'),
    ));

    $status = app(TokenStatusCache::class)->remember($oa);

    expect($status->present)->toBeTrue()
        ->and(Cache::has($cacheKey))->toBeFalse();
});

it('returns missing when OA slug is gone', function (): void {
    $oa = makeTokenStatusOa(['slug' => 'cskh-gone', 'oa_id' => '222']);
    $channel = app(Factory::class)->oa($oa->slug);

    $oa->delete();

    expect($channel->tokenStatus()->present)->toBeFalse()
        ->and($channel->tokenStatus()->isExpired())->toBeTrue();
});

it('forget removes cached status', function (): void {
    $oa = makeTokenStatusOa(['slug' => 'cskh-forget', 'oa_id' => '333']);
    $cache = app(TokenStatusCache::class);
    $cacheKey = 'zalo.token_status.'.$oa->getKey();

    (new EloquentTokenStore($oa))->put(new TokenPair(
        'access',
        'refresh',
        new DateTimeImmutable('+1 hour'),
        new DateTimeImmutable('+90 days'),
    ));

    $cache->remember($oa);
    expect(Cache::has($cacheKey))->toBeTrue();

    $cache->forget((int) $oa->getKey());
    expect(Cache::has($cacheKey))->toBeFalse();
});
