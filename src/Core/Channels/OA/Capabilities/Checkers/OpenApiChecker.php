<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class OpenApiChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::OpenApi->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $response = $snapshot->oaInfoResponse();
        $live = $response->successful();
        $fromPackage = OaPackageMatrix::allowsOpenApi($snapshot->packageName());
        $hint = '';

        if (! $live) {
            $message = $response->errorMessage();
            $hint = $message !== ''
                ? 'getoa thất bại: '.$message
                : 'Gói OA không có Open API (Cơ bản/Tiêu chuẩn). Nâng lên Tăng trưởng hoặc Toàn diện.';
        }

        return new CapabilityResult(
            key: $this->key(),
            available: $live,
            value: $live,
            meta: $snapshot->oaInfo() + [
                'tier' => OaPackageMatrix::tier($snapshot->packageName()),
                'matrix_open_api' => $fromPackage,
            ],
            source: $live ? 'live' : 'matrix',
            hint: $hint,
        );
    }
}
