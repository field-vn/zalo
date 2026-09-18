<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;
use FieldVn\Zalo\Core\Errors\ErrorCatalog;

final class ZbsChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::Zbs->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $denied = $snapshot->zbsQuotaDeniedCode();
        $quotaError = $snapshot->zbsQuotaError();
        $available = $quotaError === null;

        $hint = '';

        if ($denied !== null) {
            $hint = ErrorCatalog::lookup($denied)->hint;
        }

        return new CapabilityResult(
            key: $this->key(),
            available: $available,
            value: $available,
            meta: [
                'error_code' => $denied ?? $quotaError,
            ],
            source: 'live',
            hint: $hint,
        );
    }
}
