<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Auth;

use DateTimeImmutable;

/**
 * Cặp token của một OA.
 *
 * refresh_token XOAY VÒNG: mỗi lần refresh, Zalo trả về refresh_token MỚI và
 * cái cũ chết ngay. Bắt buộc phải ghi lại giá trị mới, nếu không lần refresh
 * kế tiếp sẽ hỏng và OA mất kết nối vĩnh viễn.
 */
final class TokenPair
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $refreshExpiresAt = null,
    ) {}

    /**
     * Dựng từ response của oauth.zaloapp.com.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data, ?DateTimeImmutable $now = null): self
    {
        $now = $now ?? new DateTimeImmutable;

        // Zalo trả expires_in dạng chuỗi giây.
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        return new self(
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: (string) ($data['refresh_token'] ?? ''),
            expiresAt: $now->modify("+{$expiresIn} seconds"),
            // Zalo không trả hạn của refresh_token; theo tài liệu là ~3 tháng.
            refreshExpiresAt: $now->modify('+90 days'),
        );
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return ($now ?? new DateTimeImmutable) >= $this->expiresAt;
    }

    /** Sắp hết hạn — nên refresh trước khi dùng. */
    public function expiresWithin(int $minutes, ?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable;

        return $now->modify("+{$minutes} minutes") >= $this->expiresAt;
    }

    /**
     * refresh_token sắp hết hạn — phải refresh CƯỠNG BỨC để xoay vòng,
     * kể cả khi access_token còn dùng được.
     */
    public function needsRotation(int $days, ?DateTimeImmutable $now = null): bool
    {
        if ($this->refreshExpiresAt === null) {
            return false;
        }

        $now = $now ?? new DateTimeImmutable;

        return $now->modify("+{$days} days") >= $this->refreshExpiresAt;
    }

    public function refreshExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->refreshExpiresAt !== null
            && ($now ?? new DateTimeImmutable) >= $this->refreshExpiresAt;
    }
}
