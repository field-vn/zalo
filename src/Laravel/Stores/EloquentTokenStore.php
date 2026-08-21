<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Stores;

use DateTimeImmutable;
use FieldVn\Zalo\Contracts\TokenStore;
use FieldVn\Zalo\Core\Auth\TokenPair;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;

final class EloquentTokenStore implements TokenStore
{
    public function __construct(private readonly ZaloOa $oa)
    {
    }

    public function get(): ?TokenPair
    {
        $record = $this->record();

        if ($record === null || $record->access_token === '') {
            return null;
        }

        // expires_at null nghĩa là dữ liệu hỏng — coi như hết hạn để buộc refresh,
        // an toàn hơn nhiều so với mặc định "now" (khiến token trông như còn hạn).
        $expiresAt = $record->expires_at !== null
            ? new DateTimeImmutable($record->expires_at->toDateTimeString())
            : new DateTimeImmutable('-1 second');

        return new TokenPair(
            accessToken:      $record->access_token,
            refreshToken:     $record->refresh_token,
            expiresAt:        $expiresAt,
            refreshExpiresAt: $record->refresh_expires_at !== null
                ? new DateTimeImmutable($record->refresh_expires_at->toDateTimeString())
                : null,
        );
    }

    public function put(TokenPair $tokens): void
    {
        ZaloOaToken::updateOrCreate(
            ['oa_id' => $this->oa->getKey()],
            [
                'access_token'       => $tokens->accessToken,
                'refresh_token'      => $tokens->refreshToken,
                'expires_at'         => $tokens->expiresAt,
                'refresh_expires_at' => $tokens->refreshExpiresAt,
                'last_refreshed_at'  => now(),
                'last_error'         => null,
            ],
        );

        $this->oa->unsetRelation('token');
    }

    public function forget(): void
    {
        $this->record()?->delete();
        $this->oa->unsetRelation('token');
    }

    public function recordFailure(string $message): void
    {
        $record = $this->record();

        if ($record === null) {
            return;
        }

        $record->forceFill([
            'last_error'      => $message,
            'failed_attempts' => $record->failed_attempts + 1,
        ])->save();
    }

    public function clearFailures(): void
    {
        $record = $this->record();

        if ($record !== null && ($record->failed_attempts > 0 || $record->last_error !== null)) {
            $record->forceFill(['failed_attempts' => 0, 'last_error' => null])->save();
        }
    }

    private function record(): ?ZaloOaToken
    {
        return ZaloOaToken::query()->where('oa_id', $this->oa->getKey())->first();
    }
}
