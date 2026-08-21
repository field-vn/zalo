<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;

/**
 * Chuyển trạng thái OA thành thứ hiển thị được.
 *
 * Tách khỏi Blade vì cùng logic này dùng ở hai màn hình, và vì tính toán
 * trong template thì không test được.
 */
final class OaPresenter
{
    public static function maskedAppId(): string
    {
        $appId = (string) config('zalo.apps.default.app_id', '');

        if ($appId === '') {
            return '';
        }

        return strlen($appId) > 8
            ? substr($appId, 0, 4).'…'.substr($appId, -4)
            : $appId;
    }

    /** HTML badge — nội dung do package sinh, không có dữ liệu người dùng. */
    public static function statusBadge(ZaloOa $oa): string
    {
        if ($oa->token === null) {
            return '<span class="zl-badge zl-badge-err">chưa cấp quyền</span>';
        }

        if ($oa->token->refreshExpired()) {
            return '<span class="zl-badge zl-badge-err">mất kết nối</span>';
        }

        if (! $oa->is_active) {
            return '<span class="zl-badge zl-badge-mute">đã tắt</span>';
        }

        $days = $oa->token->daysUntilRotation();

        return ($days !== null && $days <= (int) config('zalo.scheduler.rotate_before', 14))
            ? '<span class="zl-badge zl-badge-warn">sắp phải xoay</span>'
            : '<span class="zl-badge zl-badge-ok">hoạt động</span>';
    }

    /** @return array{access: string, rotate: string} */
    public static function tokenSummary(ZaloOa $oa): array
    {
        if ($oa->token === null) {
            return ['access' => '—', 'rotate' => '—'];
        }

        $expiresAt = $oa->token->expires_at;

        if ($expiresAt === null) {
            $access = '—';
        } else {
            // Tự tính thay vì diffForHumans(): chữ ký của nó khác nhau giữa
            // Carbon 2 (Laravel 10) và Carbon 3 (Laravel 11+).
            $minutes = (int) now()->diffInMinutes($expiresAt, false);
            $access = match (true) {
                $minutes <= 0 => 'đã hết hạn',
                $minutes < 60 => "còn {$minutes} phút",
                default => 'còn '.intdiv($minutes, 60).' giờ',
            };
        }

        $days = $oa->token->daysUntilRotation();

        return [
            'access' => $access,
            'rotate' => $days === null
                ? '—'
                : ($days <= 0 ? 'đã hết hạn' : "xoay sau {$days} ngày"),
        ];
    }

    /**
     * Cảnh báo cần người xử lý — thứ không tự khỏi được.
     *
     * @param  Collection<int, ZaloOa>  $oas
     * @return list<string>
     */
    public static function warnings(Collection $oas): array
    {
        $warnings = [];

        if ((string) config('zalo.apps.default.app_id', '') === '') {
            $warnings[] = 'Chưa cấu hình ZALO_APP_ID / ZALO_APP_SECRET trong .env.';
        }

        if (! config('zalo.scheduler.enabled', true)) {
            $warnings[] = 'Scheduler đang tắt — token sẽ không tự refresh và OA sẽ mất kết nối.';
        }

        $rotate = (int) config('zalo.scheduler.rotate_before', 14);

        foreach ($oas as $oa) {
            if ($oa->token === null) {
                $warnings[] = "OA `{$oa->slug}` chưa được cấp quyền.";

                continue;
            }

            if ($oa->token->refreshExpired()) {
                $warnings[] = "OA `{$oa->slug}` đã mất kết nối — phải cấp quyền lại thủ công.";

                continue;
            }

            $days = $oa->token->daysUntilRotation();

            if ($days !== null && $days <= $rotate) {
                $warnings[] = "OA `{$oa->slug}` còn {$days} ngày trước khi phải xoay refresh token.";
            }
        }

        return $warnings;
    }
}
