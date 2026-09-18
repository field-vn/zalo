<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;
use FieldVn\Zalo\Core\Errors\ErrorCatalog;

final class ZbsTemplatesChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::ZbsTemplates->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $error = $snapshot->zbsTemplatesError();
        $items = $snapshot->zbsTemplates();
        $counts = [
            'total' => count($items),
            'enable' => 0,
            'pending' => 0,
            'reject' => 0,
            'disable' => 0,
        ];

        $summaries = [];

        foreach ($items as $item) {
            $status = strtoupper((string) ($item['status'] ?? ''));
            $bucket = match ($status) {
                'ENABLE' => 'enable',
                'PENDING_REVIEW', 'PENDING' => 'pending',
                'REJECT' => 'reject',
                'DISABLE' => 'disable',
                default => null,
            };

            if ($bucket !== null) {
                $counts[$bucket]++;
            }

            $summaries[] = [
                'id' => $item['templateId'] ?? $item['template_id'] ?? null,
                'name' => $item['templateName'] ?? $item['template_name'] ?? null,
                'status' => $item['status'] ?? null,
                'quality' => $item['quality'] ?? $item['qualityRating'] ?? null,
            ];
        }

        return new CapabilityResult(
            key: $this->key(),
            available: $error === null,
            value: $counts['enable'],
            remain: $counts['enable'],
            limit: $counts['total'],
            meta: $counts + ['items' => $summaries, 'error_code' => $error],
            source: 'live',
            hint: $error !== null ? ErrorCatalog::lookup($error)->hint : '',
        );
    }
}
