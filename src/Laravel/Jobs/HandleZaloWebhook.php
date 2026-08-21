<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Jobs;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Support\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleZaloWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->dispatch(WebhookEvent::fromPayload($this->payload));
    }
}
