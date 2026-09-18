<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class ZbsTemplateChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::ZbsTemplate->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $id = (string) ($options['id'] ?? $options['template_id'] ?? '');

        if ($id === '') {
            return new CapabilityResult(
                key: $this->key(),
                available: false,
                source: 'none',
                hint: 'Thiếu template id. Dùng OaCapability::zbsTemplate(id:).',
            );
        }

        if ($snapshot->zbsTemplatesError() !== null) {
            return new CapabilityResult(
                key: $this->key(),
                available: false,
                meta: ['error_code' => $snapshot->zbsTemplatesError(), 'id' => $id],
                source: 'live',
                hint: 'Không liệt kê được template ZBS.',
            );
        }

        $item = $snapshot->zbsTemplate($id);

        if ($item === null) {
            return new CapabilityResult(
                key: $this->key(),
                available: false,
                meta: ['id' => $id],
                source: 'live',
                hint: "Không tìm thấy template `{$id}`.",
            );
        }

        $status = strtoupper((string) ($item['status'] ?? ''));
        $meta = [
            'id' => $id,
            'status' => $item['status'] ?? null,
            'templateName' => $item['templateName'] ?? $item['template_name'] ?? null,
            'listParams' => $item['listParams'] ?? $item['list_params'] ?? [],
            'quota' => $item['quota'] ?? null,
        ];

        if (! empty($options['sample_data']) || ! empty($options['with_sample'])) {
            $meta['sample_data'] = $snapshot->zbsSampleData($id);
        }

        return new CapabilityResult(
            key: $this->key(),
            available: $status === 'ENABLE',
            value: $status,
            meta: $meta,
            source: 'live',
            hint: $status === 'ENABLE' ? '' : "Mẫu `{$id}` chưa ENABLE (status={$status}).",
        );
    }
}
