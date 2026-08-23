<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Laravel\Events\ZaloBotMessageReceived;
use FieldVn\Zalo\Laravel\Events\ZaloBotUpdateReceived;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use FieldVn\Zalo\Laravel\Models\ZaloWebhookLog;
use Throwable;

/**
 * Biến một update của Bot thành event Laravel, và ghi lại chat_id.
 *
 * Tách khỏi controller để job trên queue dùng lại được y hệt — đối xứng với
 * WebhookDispatcher của OA.
 */
final class BotWebhookDispatcher
{
    public function dispatch(BotUpdate $update, ZaloBot $bot): void
    {
        // Lưu chat TRƯỚC khi bắn event: listener của dự án hỏng cũng không
        // được làm mất chat_id, vì đó là thứ không lấy lại được.
        $chat = ZaloBotChat::record($bot, $update);

        $log = $this->log($update, $bot);

        try {
            ZaloBotUpdateReceived::dispatch($update, $bot);

            if ($update->isMessage()) {
                event(ZaloBotMessageReceived::from($update, $bot, $chat));
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

    private function log(BotUpdate $update, ZaloBot $bot): ?ZaloWebhookLog
    {
        if (! config('zalo.webhook.log', false)) {
            return null;
        }

        return ZaloWebhookLog::create([
            // Cột dùng chung với webhook OA; với bot thì ghi slug để phân biệt.
            // Cắt về 64 ký tự cho khớp độ rộng cột — slug bot cho phép tới 100,
            // và một lần ghi log tràn cột sẽ làm hỏng cả request webhook.
            'oa_id' => substr('bot:'.$bot->slug, 0, 64),
            'event_name' => $update->eventName(),
            'message_id' => $update->messageId(),
            'payload' => $update->payload,
            'created_at' => now(),
        ]);
    }
}
