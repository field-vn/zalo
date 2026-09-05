<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use DateTimeImmutable;
use FieldVn\Zalo\Core\Auth\TokenPair;

/**
 * Trạng thái "còn hạn" của access token OA — đủ để quyết định OA CS hay ZBS
 * mà không phải đọc lại bảng token mỗi lần gửi.
 */
final class TokenStatus
{
    public function __construct(
        public readonly bool $present,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $refreshExpiresAt,
        public readonly int $remainingMinutes,
    ) {}

    public static function missing(): self
    {
        return new self(false, null, null, -1);
    }

    public static function fromPair(TokenPair $pair, ?DateTimeImmutable $now = null): self
    {
        $now ??= new DateTimeImmutable;
        $seconds = $pair->expiresAt->getTimestamp() - $now->getTimestamp();
        $minutes = (int) floor($seconds / 60);

        return new self(
            present: true,
            expiresAt: $pair->expiresAt,
            refreshExpiresAt: $pair->refreshExpiresAt,
            remainingMinutes: $minutes,
        );
    }

    public function isFresh(int $bufferMinutes = 0): bool
    {
        return $this->present && $this->remainingMinutes > $bufferMinutes;
    }

    public function isExpired(): bool
    {
        return ! $this->present || $this->remainingMinutes <= 0;
    }
}
