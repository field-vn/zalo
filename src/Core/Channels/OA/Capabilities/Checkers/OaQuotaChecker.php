<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class OaQuotaChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::OaQuota->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $assets = $snapshot->oaQuotaAssets();
        $remain = 0;
        $limit = 0;

        foreach ($assets as $asset) {
            $remain += (int) ($asset['remain'] ?? 0);
            $limit += (int) ($asset['total'] ?? $asset['limit'] ?? 0);
        }

        $error = $snapshot->oaQuotaError();
        $ok = $assets !== [] || $error === null;

        return new CapabilityResult(
            key: $this->key(),
            available: $ok && $remain > 0,
            value: $remain,
            remain: $remain,
            limit: $limit,
            meta: [
                'assets' => $assets,
                'error' => $error,
            ],
            source: 'live',
            hint: $ok ? '' : 'Không đọc được hạn mức OA (POST /v3.0/oa/quota/message).',
        );
    }
}
