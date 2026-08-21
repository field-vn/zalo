<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Người dùng gửi tin nhắn tới OA.
 *
 *     public function handle(ZaloMessageReceived $e): void
 *     {
 *         $e->oa?->slug;    // OA nào nhận
 *         $e->userId;       // ai gửi
 *         $e->text;         // nội dung (null nếu là ảnh/file/sticker)
 *     }
 */
class ZaloMessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        public readonly ?ZaloOa $oa,
        public readonly ?string $userId,
        public readonly ?string $text,
        public readonly ?string $messageId,
    ) {}

    public static function from(WebhookEvent $event, ?ZaloOa $oa): self
    {
        return new self(
            event: $event,
            oa: $oa,
            userId: $event->userId(),
            text: $event->text(),
            messageId: $event->messageId(),
        );
    }

    /** @return list<array<string, mixed>> */
    public function attachments(): array
    {
        return $this->event->attachments();
    }
}
