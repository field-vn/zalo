<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class AuthorizedAppsChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::AuthorizedApps->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $limit = OaPackageMatrix::entitlements($snapshot->packageName())['authorized_apps'];

        return new CapabilityResult(
            key: $this->key(),
            available: $limit === null || $limit > 0,
            value: null,
            remain: null,
            limit: $limit,
            meta: [
                'used' => null,
                'tier' => OaPackageMatrix::tier($snapshot->packageName()),
            ],
            source: 'matrix',
            hint: 'Zalo không có API đếm số app đang ủy quyền; used luôn null. Toàn diện = không giới hạn.',
        );
    }
}
