<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class RateLimitChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::RateLimit->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $response = $snapshot->oaInfoResponse();
        $headerLimit = $this->intHeader($response->header('X-RateLimit-Limit'));
        $headerRemain = $this->intHeader($response->header('X-RateLimit-Remain'));
        $oaLimit = (int) OaPackageMatrix::entitlements($snapshot->packageName())['rate_limit'];
        $remain = $headerRemain ?? $oaLimit;
        $limit = $headerLimit ?? $oaLimit;

        return new CapabilityResult(
            key: $this->key(),
            available: $remain > 0,
            value: $remain,
            remain: $remain,
            limit: $limit,
            meta: [
                'oa_limit' => $oaLimit,
                'app_limit' => $headerLimit,
                'app_remain' => $headerRemain,
            ],
            source: $headerLimit !== null || $headerRemain !== null ? 'live' : 'matrix',
        );
    }

    private function intHeader(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
