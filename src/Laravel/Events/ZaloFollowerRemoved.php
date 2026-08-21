<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/** Người dùng bỏ quan tâm OA. */
class ZaloFollowerRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        public readonly ?ZaloOa $oa,
        public readonly ?string $userId,
    ) {}
}
