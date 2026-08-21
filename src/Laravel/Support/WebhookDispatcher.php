<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerAdded;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerRemoved;
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;
use FieldVn\Zalo\Laravel\Events\ZaloWebhookReceived;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloWebhookLog;
use Throwable;

/**
 * Biến một sự kiện webhook thành các event của Laravel.
 *
 * Dùng chung cho cả xử lý đồng bộ lẫn job trên queue.
 */
final class WebhookDispatcher
{
    public function dispatch(WebhookEvent $event): void
    {
        $oa = $this->resolveOa($event);
        $log = $this->log($event, $oa);

        try {
            // Event chung luôn bắn trước — người dùng bắt được cả những
            // event_name mà package chưa bọc riêng.
            ZaloWebhookReceived::dispatch($event, $oa);

            if ($event->isMessage()) {
                event(ZaloMessageReceived::from($event, $oa));
            } elseif ($event->isFollow()) {
                ZaloFollowerAdded::dispatch($event, $oa, $event->userId());
            } elseif ($event->isUnfollow()) {
                ZaloFollowerRemoved::dispatch($event, $oa, $event->userId());
            }

            $log?->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            $log?->forceFill([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ])->save();

            throw $e;
        }
    }

    /** OA có thể chưa được thêm vào hệ thống — không phải lỗi, chỉ là chưa quản lý. */
    private function resolveOa(WebhookEvent $event): ?ZaloOa
    {
        if ($event->oaId === '') {
            return null;
        }

        return ZaloOa::query()->where('oa_id', $event->oaId)->first();
    }

    private function log(WebhookEvent $event, ?ZaloOa $oa): ?ZaloWebhookLog
    {
        if (! config('zalo.webhook.log', false)) {
            return null;
        }

        return ZaloWebhookLog::create([
            'oa_id' => $event->oaId ?: null,
            'event_name' => $event->name,
            'message_id' => $event->messageId(),
            'payload' => $event->toArray(),
            'created_at' => now(),
        ]);
    }
}
