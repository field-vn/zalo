<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Auth;

use FieldVn\Zalo\Contracts\TokenStore;

/**
 * Lưu token trong bộ nhớ, mất khi kết thúc request.
 *
 * Dùng cho test và cho những kịch bản ngắn hạn (script chạy một lần). KHÔNG
 * dùng cho ứng dụng thật: refresh_token xoay vòng mỗi lần refresh, mất nó là
 * mất kết nối vĩnh viễn với OA.
 */
final class ArrayTokenStore implements TokenStore
{
    private ?TokenPair $tokens;

    private int $failures = 0;

    private ?string $lastError = null;

    public function __construct(?TokenPair $tokens = null)
    {
        $this->tokens = $tokens;
    }

    public function get(): ?TokenPair
    {
        return $this->tokens;
    }

    public function put(TokenPair $tokens): void
    {
        $this->tokens = $tokens;
    }

    public function forget(): void
    {
        $this->tokens = null;
    }

    public function recordFailure(string $message): void
    {
        $this->failures++;
        $this->lastError = $message;
    }

    public function clearFailures(): void
    {
        $this->failures = 0;
        $this->lastError = null;
    }

    public function failures(): int
    {
        return $this->failures;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
