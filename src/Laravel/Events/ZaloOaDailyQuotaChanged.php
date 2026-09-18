<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/** Hạn mức gửi tin ZBS theo ngày của OA thay đổi. */
class ZaloOaDailyQuotaChanged
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        public readonly ?ZaloOa $oa,
        public readonly ?int $prevValue,
        public readonly ?int $newValue,
    ) {}
}
