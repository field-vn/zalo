<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use FieldVn\Zalo\Core\Errors\ErrorCatalog;
use FieldVn\Zalo\Core\Errors\ErrorInfo;

/** Không lấy được token hợp lệ cho OA. */
class TokenException extends ZaloException
{
    public function source(): string
    {
        return 'token';
    }

    public function explain(): string
    {
        return $this->getMessage();
    }

    public function info(): ErrorInfo
    {
        return new ErrorInfo(
            code: $this->getCode(),
            message: $this->getMessage(),
            description: 'Không lấy được access token hợp lệ cho OA.',
            hint: 'Chạy php artisan zalo:authorize {oa} hoặc Cấp lại quyền trên giao diện.',
            category: 'token',
            source: 'token',
            docsUrl: ErrorCatalog::OA_DOCS,
        );
    }

    public static function missing(string $oa): self
    {
        return new self(
            "OA [{$oa}] chưa có token. Chạy: php artisan zalo:authorize {$oa}"
        );
    }

    public static function refreshFailed(string $oa, string $reason): self
    {
        return new self("Refresh token cho OA [{$oa}] thất bại: {$reason}");
    }

    public static function refreshExpired(string $oa): self
    {
        return new self(
            "Refresh token của OA [{$oa}] đã hết hạn — không thể tự khôi phục. ".
            "Phải cấp quyền lại: php artisan zalo:authorize {$oa}"
        );
    }
}
