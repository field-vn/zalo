<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

/**
 * Hạn mức gói khi Zalo không có API (bảng giá OA).
 *
 * Tên cũ Nâng cao / Premium / Dùng thử = Tăng trưởng (FAQ: Open API / ZBS).
 */
final class OaPackageMatrix
{
    public const TIER_BASIC = 'basic';

    public const TIER_STANDARD = 'standard';

    public const TIER_GROWTH = 'growth';

    public const TIER_COMPREHENSIVE = 'comprehensive';

    public const TIER_UNKNOWN = 'unknown';

    public static function tier(?string $packageName): string
    {
        $name = mb_strtolower(trim((string) $packageName));

        if ($name === '' || str_contains($name, 'cơ bản') || str_contains($name, 'co ban') || $name === 'basic') {
            return $name === '' ? self::TIER_UNKNOWN : self::TIER_BASIC;
        }

        if (str_contains($name, 'tiêu chuẩn') || str_contains($name, 'tieu chuan') || $name === 'standard') {
            return self::TIER_STANDARD;
        }

        if (str_contains($name, 'toàn diện') || str_contains($name, 'toan dien') || str_contains($name, 'comprehensive')) {
            return self::TIER_COMPREHENSIVE;
        }

        if (
            str_contains($name, 'tăng trưởng')
            || str_contains($name, 'tang truong')
            || str_contains($name, 'nâng cao')
            || str_contains($name, 'nang cao')
            || str_contains($name, 'premium')
            || str_contains($name, 'dùng thử')
            || str_contains($name, 'dung thu')
            || $name === 'growth'
        ) {
            return self::TIER_GROWTH;
        }

        return self::TIER_UNKNOWN;
    }

    /**
     * @return array{
     *     open_api: bool,
     *     zbs: bool,
     *     campaign_tool: bool,
     *     cs_outside_48h: int,
     *     rate_limit: int,
     *     authorized_apps: int|null
     * }
     */
    public static function entitlements(?string $packageName): array
    {
        return match (self::tier($packageName)) {
            self::TIER_COMPREHENSIVE => [
                'open_api' => true,
                'zbs' => true,
                'campaign_tool' => true,
                'cs_outside_48h' => 2000,
                'rate_limit' => 2000,
                'authorized_apps' => null,
            ],
            self::TIER_GROWTH => [
                'open_api' => true,
                'zbs' => true,
                'campaign_tool' => true,
                'cs_outside_48h' => 500,
                'rate_limit' => 100,
                'authorized_apps' => 3,
            ],
            self::TIER_STANDARD => [
                'open_api' => false,
                'zbs' => false,
                'campaign_tool' => false,
                'cs_outside_48h' => 0,
                'rate_limit' => 0,
                'authorized_apps' => 1,
            ],
            default => [
                'open_api' => false,
                'zbs' => false,
                'campaign_tool' => false,
                'cs_outside_48h' => 0,
                'rate_limit' => 0,
                'authorized_apps' => 1,
            ],
        };
    }

    public static function allowsOpenApi(?string $packageName): bool
    {
        return self::entitlements($packageName)['open_api'];
    }
}
