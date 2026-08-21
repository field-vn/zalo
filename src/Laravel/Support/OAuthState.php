<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * State chống CSRF cho luồng OAuth.
 *
 * Lưu trong cache chứ không phải session, vì luồng authorize chạy được ở cả
 * hai nơi: trình duyệt (có session) và CLI (không có). Một khoá ngẫu nhiên
 * 40 ký tự dùng một lần vẫn đủ an toàn — kẻ tấn công không đoán được, và
 * state bị xoá ngay khi dùng nên không replay được.
 */
final class OAuthState
{
    private const PREFIX = 'zalo:oauth:state:';

    private const TTL_MINUTES = 10;

    /** Sinh state mới gắn với một OA. */
    public static function issue(int $oaId): string
    {
        $state = Str::random(40);

        Cache::put(self::PREFIX.$state, $oaId, now()->addMinutes(self::TTL_MINUTES));

        return $state;
    }

    /**
     * Xác minh và tiêu huỷ state. Trả về id của OA, hoặc null nếu state
     * không hợp lệ / đã dùng / đã hết hạn.
     */
    public static function consume(string $state): ?int
    {
        if ($state === '') {
            return null;
        }

        $key   = self::PREFIX.$state;
        $oaId  = Cache::get($key);

        // Dùng một lần: xoá ngay để không replay được.
        Cache::forget($key);

        return is_int($oaId) ? $oaId : null;
    }

    public static function ttlMinutes(): int
    {
        return self::TTL_MINUTES;
    }
}
