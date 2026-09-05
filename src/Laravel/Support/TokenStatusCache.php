<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;
use Illuminate\Support\Facades\Cache;

/**
 * Cache ngắn hạn trạng thái access token OA.
 *
 * Key theo id nội bộ `zl_oas.id` (không phải oa_id Zalo). TTL = 0 thì đọc thẳng store.
 */
final class TokenStatusCache
{
    public function remember(ZaloOa $oa): TokenStatus
    {
        $ttl = (int) config('zalo.notifier.token_status_cache_ttl', 90);

        if ($ttl <= 0) {
            return $this->resolve($oa);
        }

        return Cache::remember(
            $this->key((int) $oa->getKey()),
            $ttl,
            fn (): TokenStatus => $this->resolve($oa),
        );
    }

    public function forget(int $oaId): void
    {
        Cache::forget($this->key($oaId));
    }

    public function forgetFor(ZaloOa $oa): void
    {
        $this->forget((int) $oa->getKey());
    }

    private function resolve(ZaloOa $oa): TokenStatus
    {
        $pair = (new EloquentTokenStore($oa))->get();

        return $pair === null
            ? TokenStatus::missing()
            : TokenStatus::fromPair($pair);
    }

    private function key(int $oaId): string
    {
        return 'zalo.token_status.'.$oaId;
    }
}
