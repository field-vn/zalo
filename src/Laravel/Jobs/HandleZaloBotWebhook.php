<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Jobs;

use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Support\BotWebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Xử lý update của Bot ở nền.
 *
 * Nhận payload dạng mảng và id của bot chứ không nhận object: job nằm trong
 * hàng đợi có thể chạy sau khi code đã deploy phiên bản mới, và mảng thô thì
 * không vỡ khi class đổi.
 */
class HandleZaloBotWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly array $payload,
        public readonly int $botId,
    ) {}

    public function handle(BotWebhookDispatcher $dispatcher): void
    {
        $bot = ZaloBot::query()->find($this->botId);

        if (! $bot instanceof ZaloBot) {
            // Bot bị xoá giữa lúc job nằm chờ — không có gì để xử lý, và ném
            // lỗi ở đây chỉ tạo ra job thất bại vô nghĩa.
            return;
        }

        $dispatcher->dispatch(BotUpdate::fromPayload($this->payload), $bot);
    }
}
