<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/** Người dùng bấm quan tâm OA. */
class ZaloFollowerAdded
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        public readonly ?ZaloOa $oa,
        public readonly ?string $userId,
    ) {}
}
