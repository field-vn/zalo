<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Mọi update từ Zalo Bot, kể cả loại package chưa bọc riêng.
 *
 * Bắt event này khi cần đọc thứ mà `ZaloBotMessageReceived` không phơi ra —
 * Bot API còn đang thay đổi nên luôn cần một lối thoát về payload gốc.
 *
 *     public function handle(ZaloBotUpdateReceived $e): void
 *     {
 *         $e->bot->slug;
 *         $e->update->payload;   // JSON thô Zalo gửi
 *     }
 */
class ZaloBotUpdateReceived
{
    use Dispatchable;

    public function __construct(
        public readonly BotUpdate $update,
        public readonly ZaloBot $bot,
    ) {}
}
