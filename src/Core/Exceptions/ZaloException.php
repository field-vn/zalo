<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use FieldVn\Zalo\Core\Errors\ErrorCatalog;
use FieldVn\Zalo\Core\Errors\ErrorInfo;
use RuntimeException;

/**
 * Gốc của mọi exception trong package.
 * Người dùng bắt class này là bắt được tất cả.
 */
class ZaloException extends RuntimeException
{
    public function info(): ErrorInfo
    {
        return ErrorCatalog::lookup($this->getCode(), $this->getMessage(), $this->source());
    }

    public function source(): string
    {
        return 'config';
    }

    /**
     * Câu đủ mã + message Zalo + hint tiếng Việt — dùng cho UI/CLI.
     */
    public function explain(): string
    {
        $info = $this->info();
        $code = $this->getCode();

        $line = $code !== 0
            ? "Zalo từ chối — mã {$code}: {$this->getMessage()}."
            : $this->getMessage();

        if ($info->hint !== '') {
            $line .= ' '.$info->hint;
        }

        return rtrim($line);
    }

    /**
     * Payload ổn định cho bên thứ 3 (JSON API / log / NotifyResult).
     *
     * @return array{
     *     ok: false,
     *     source: string,
     *     code: int,
     *     message: string,
     *     description: string,
     *     hint: string,
     *     category: string,
     *     docs: string,
     *     http_status: int
     * }
     */
    public function toArray(): array
    {
        $info = $this->info();
        $payload = $info->toArray();
        $payload['http_status'] = $this->httpStatus();

        return $payload;
    }

    public function httpStatus(): int
    {
        return ErrorCatalog::httpStatusFor($this->info()->category);
    }
}
