<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class OaPackageChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::OaPackage->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $info = $snapshot->oaInfo();
        $ok = $snapshot->oaInfoResponse()->successful();

        return new CapabilityResult(
            key: $this->key(),
            available: $ok,
            value: $snapshot->packageName(),
            meta: $info,
            source: 'live',
            hint: $ok ? '' : ($snapshot->oaInfoResponse()->errorMessage() ?: 'Không lấy được thông tin OA.'),
        );
    }
}
