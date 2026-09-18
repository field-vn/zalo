<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class CampaignToolChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::CampaignTool->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $allowed = (bool) OaPackageMatrix::entitlements($snapshot->packageName())['campaign_tool'];
        $verified = (bool) ($snapshot->oaInfo()['is_verified'] ?? false);

        return new CapabilityResult(
            key: $this->key(),
            available: $allowed,
            value: $allowed,
            meta: [
                'is_verified' => $verified,
                'tier' => OaPackageMatrix::tier($snapshot->packageName()),
            ],
            source: 'matrix',
            hint: 'Campaign tool chỉ có trên OA Manager, không có Open API. Suy từ gói.',
        );
    }
}
