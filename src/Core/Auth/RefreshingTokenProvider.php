<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Auth;

use DateTimeImmutable;
use FieldVn\Zalo\Contracts\TokenStore;
use FieldVn\Zalo\Core\Exceptions\TokenException;

/**
 * Cung cấp access_token luôn còn hiệu lực.
 *
 * Trái tim của nhánh OA. Ba việc, theo đúng thứ tự ưu tiên:
 *
 *  1. refresh_token đã hết hạn  → ném lỗi ngay, không thể tự cứu
 *  2. refresh_token sắp hết hạn → refresh CƯỠNG BỨC để xoay vòng
 *  3. access_token sắp hết hạn  → refresh bình thường
 *
 * Bước 2 là thứ người ta hay quên: refresh_token sống ~3 tháng và xoay vòng
 * mỗi lần dùng, nên app im lặng quá lâu sẽ mất kết nối vĩnh viễn.
 */
final class RefreshingTokenProvider
{
    public function __construct(
        private readonly string $oaSlug,
        private readonly OAuthClient $oauth,
        private readonly TokenStore $store,
        private readonly int $refreshBeforeMinutes = 15,
        private readonly int $rotateBeforeDays = 14,
    ) {
    }

    public function accessToken(): string
    {
        return $this->tokens()->accessToken;
    }

    public function tokens(?DateTimeImmutable $now = null): TokenPair
    {
        $now     = $now ?? new DateTimeImmutable();
        $current = $this->store->get();

        if ($current === null) {
            throw TokenException::missing($this->oaSlug);
        }

        if ($current->refreshExpired($now)) {
            throw TokenException::refreshExpired($this->oaSlug);
        }

        $needsRefresh = $current->expiresWithin($this->refreshBeforeMinutes, $now)
            || $current->needsRotation($this->rotateBeforeDays, $now);

        return $needsRefresh ? $this->refresh($current) : $current;
    }

    /** Refresh bất kể còn hạn hay không — dùng cho `zalo:token:refresh --force`. */
    public function forceRefresh(): TokenPair
    {
        $current = $this->store->get();

        if ($current === null) {
            throw TokenException::missing($this->oaSlug);
        }

        return $this->refresh($current);
    }

    private function refresh(TokenPair $current): TokenPair
    {
        try {
            $fresh = $this->oauth->refresh($current->refreshToken);
        } catch (TokenException $e) {
            $this->store->recordFailure($e->getMessage());

            throw TokenException::refreshFailed($this->oaSlug, $e->getMessage());
        }

        // BẮT BUỘC: refresh_token cũ đã chết, phải ghi cái mới xuống ngay.
        $this->store->put($fresh);
        $this->store->clearFailures();

        return $fresh;
    }
}
