<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class ZbsSendChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::ZbsSend->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $templateId = (string) ($options['template_id'] ?? $options['id'] ?? '');

        if ($templateId === '') {
            return new CapabilityResult(
                key: $this->key(),
                available: false,
                source: 'none',
                hint: 'Thiếu template_id. Dùng OaCapability::zbsSend(templateId:) — check không POST tin thật.',
            );
        }

        $denied = $snapshot->zbsQuotaDeniedCode();
        $quota = $snapshot->zbsQuota();
        $remain = isset($quota['remainingQuota']) ? (int) $quota['remainingQuota'] : 0;
        $limit = isset($quota['dailyQuota']) ? (int) $quota['dailyQuota'] : null;
        $item = $denied === null ? $snapshot->zbsTemplate($templateId) : null;
        $status = strtoupper((string) ($item['status'] ?? ''));
        $linked = $denied === null && $snapshot->zbsQuotaError() === null;
        $available = $linked && $status === 'ENABLE' && $remain > 0;

        $hint = '';

        if (! $linked) {
            $hint = 'OA/App chưa liên kết ZBS hoặc không gọi được quota.';
        } elseif ($item === null) {
            $hint = "Không tìm thấy template `{$templateId}`.";
        } elseif ($status !== 'ENABLE') {
            $hint = "Mẫu chưa ENABLE (status={$status}).";
        } elseif ($remain <= 0) {
            $hint = 'Hết hạn mức ZBS trong ngày (remainingQuota = 0).';
        }

        return new CapabilityResult(
            key: $this->key(),
            available: $available,
            value: $available,
            remain: $remain,
            limit: $limit,
            meta: [
                'template_id' => $templateId,
                'status' => $item['status'] ?? null,
                'remain' => $remain,
                'limit' => $limit,
                'error_code' => $denied ?? $snapshot->zbsQuotaError(),
            ],
            source: 'live',
            hint: $hint,
        );
    }
}
