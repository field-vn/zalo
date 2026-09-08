<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/** Template ZBS đổi trạng thái (PENDING_REVIEW → ENABLE / REJECT / …). */
class ZaloTemplateStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        public readonly ?ZaloOa $oa,
        public readonly string $templateId,
        public readonly ?string $prevStatus,
        public readonly ?string $newStatus,
        public readonly ?string $reason,
    ) {}
}
