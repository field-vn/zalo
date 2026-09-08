<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class CsOutside48hChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::CsOutside48h->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $assets = $snapshot->oaQuotaAssets();
        $slice = null;

        foreach ($assets as $asset) {
            $product = strtolower((string) ($asset['product_type'] ?? ''));
            $quota = strtolower((string) ($asset['quota_type'] ?? ''));

            if ($product === 'cs' && ($quota === 'sub_quota' || $quota === '')) {
                $slice = $asset;
                break;
            }
        }

        if ($slice !== null) {
            $remain = (int) ($slice['remain'] ?? 0);
            $limit = (int) ($slice['total'] ?? $slice['limit'] ?? 0);

            return new CapabilityResult(
                key: $this->key(),
                available: $remain > 0,
                value: $remain,
                remain: $remain,
                limit: $limit,
                meta: [
                    'product_type' => $slice['product_type'] ?? 'cs',
                    'quota_type' => $slice['quota_type'] ?? 'sub_quota',
                    'valid_through' => $slice['valid_through'] ?? null,
                    'asset' => $slice,
                ],
                source: 'live',
            );
        }

        $error = $snapshot->oaQuotaError();
        $limit = (int) OaPackageMatrix::entitlements($snapshot->packageName())['cs_outside_48h'];
        $fromMatrix = $error === -224 || $assets === [];

        return new CapabilityResult(
            key: $this->key(),
            available: false,
            value: 0,
            remain: 0,
            limit: $fromMatrix ? $limit : 0,
            meta: [
                'product_type' => 'cs',
                'quota_type' => 'sub_quota',
                'valid_through' => null,
                'error' => $error,
            ],
            source: $fromMatrix ? 'matrix' : 'live',
            hint: $error === -224
                ? 'OA không có hạn mức CS ngoài 48h (mã -224). Số limit lấy từ gói.'
                : 'Không có asset cs/sub_quota trong quota OA.',
        );
    }
}
