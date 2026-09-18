<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;
use FieldVn\Zalo\Core\Errors\ErrorCatalog;

final class ZbsQuotaChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::ZbsQuota->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $error = $snapshot->zbsQuotaError();
        $payload = $snapshot->zbsQuota();
        $remain = isset($payload['remainingQuota']) ? (int) $payload['remainingQuota'] : null;
        $limit = isset($payload['dailyQuota']) ? (int) $payload['dailyQuota'] : null;

        return new CapabilityResult(
            key: $this->key(),
            available: $error === null && ($remain === null || $remain > 0),
            value: $remain,
            remain: $remain,
            limit: $limit,
            meta: [
                'dailyQuota' => $payload['dailyQuota'] ?? null,
                'remainingQuota' => $payload['remainingQuota'] ?? null,
                'dailyQuotaPromotion' => $payload['dailyQuotaPromotion'] ?? null,
                'remainingQuotaPromotion' => $payload['remainingQuotaPromotion'] ?? null,
                'monthlyPromotionQuota' => $payload['monthlyPromotionQuota'] ?? null,
                'remainingMonthlyPromotionQuota' => $payload['remainingMonthlyPromotionQuota'] ?? null,
                'estimatedNextMonthPromotionQuota' => $payload['estimatedNextMonthPromotionQuota'] ?? null,
                'error_code' => $error,
            ],
            source: 'live',
            hint: $error !== null ? ErrorCatalog::lookup($error)->hint : '',
        );
    }
}
