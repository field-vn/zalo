<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;

final class UserQuotaChecker implements CapabilityChecker
{
    public function key(): string
    {
        return OaCapability::UserQuota->value;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        $userId = (string) ($options['user_id'] ?? '');

        if ($userId === '') {
            return new CapabilityResult(
                key: $this->key(),
                available: false,
                source: 'none',
                hint: 'Thiếu user_id. Dùng OaCapability::userQuota(userId:) hoặc check(..., [\'user_id\' => ...]).',
            );
        }

        $payload = $snapshot->userQuota($userId);
        $promotion = is_array($payload['promotion'] ?? null) ? $payload['promotion'] : [];
        $monthlyRemain = isset($promotion['monthly_remain']) ? (int) $promotion['monthly_remain'] : null;
        $monthlyTotal = isset($promotion['monthly_total']) ? (int) $promotion['monthly_total'] : null;
        $error = isset($payload['error']) ? (int) $payload['error'] : null;

        return new CapabilityResult(
            key: $this->key(),
            available: $error === null && ($monthlyRemain === null || $monthlyRemain > 0),
            value: $monthlyRemain,
            remain: $monthlyRemain,
            limit: $monthlyTotal,
            meta: [
                'user_id' => $userId,
                'last_interaction' => $payload['last_interaction'] ?? null,
                'promotion' => [
                    'daily_remain' => $promotion['daily_remain'] ?? null,
                    'daily_total' => $promotion['daily_total'] ?? null,
                    'monthly_remain' => $monthlyRemain,
                    'monthly_total' => $monthlyTotal,
                ],
                'payload' => $payload,
            ],
            source: 'live',
            hint: $error !== null ? 'Không đọc được quota theo user.' : '',
        );
    }
}
