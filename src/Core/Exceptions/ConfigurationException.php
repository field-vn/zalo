<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

/**
 * Cấu hình sai. Thông báo luôn kèm cách sửa cụ thể — người đọc exception
 * này thường đang bối rối, đừng bắt họ đi tra tài liệu.
 */
class ConfigurationException extends ZaloException
{
    public static function oaNotFound(string|int|null $key): self
    {
        $key = $key === null ? '(mặc định)' : (string) $key;

        return new self(
            "Không tìm thấy OA [{$key}]. Xem danh sách: php artisan zalo:oa:list"
        );
    }

    public static function oaInactive(string $slug): self
    {
        return new self(
            "OA [{$slug}] đang bị tắt. Bật lại trong UI hoặc chạy: php artisan zalo:authorize {$slug}"
        );
    }

    public static function botNotFound(string|int|null $key): self
    {
        $key = $key === null ? '(mặc định)' : (string) $key;

        return new self(
            "Không tìm thấy Bot [{$key}]. Xem danh sách: php artisan zalo:bot:list"
        );
    }

    public static function appNotConfigured(string $appKey): self
    {
        return new self(
            "Zalo App [{$appKey}] chưa được cấu hình. ".
            'Thêm ZALO_APP_ID và ZALO_APP_SECRET vào .env, hoặc khai báo key này trong config/zalo.php.'
        );
    }

    public static function appCredentialsMissing(): self
    {
        return new self(
            'Thiếu ZALO_APP_ID hoặc ZALO_APP_SECRET trong .env. '.
            'Tạo ứng dụng tại https://developers.zalo.me/apps rồi chạy: php artisan zalo:install'
        );
    }
}
