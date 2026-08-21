<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

/** Không lấy được token hợp lệ cho OA. */
class TokenException extends ZaloException
{
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
