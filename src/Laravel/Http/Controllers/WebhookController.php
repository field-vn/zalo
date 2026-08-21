<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Core\Webhook\SignatureVerifier;
use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Jobs\HandleZaloWebhook;
use FieldVn\Zalo\Laravel\Support\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nhận webhook từ Zalo.
 *
 * Hai nguyên tắc:
 *
 *  1. Chữ ký là lớp bảo vệ DUY NHẤT và đủ. Route này không đi qua auth của UI
 *     — người gọi là Zalo, không phải người dùng.
 *
 *  2. Luôn trả 200 nhanh nhất có thể. Zalo có timeout và sẽ gửi lại nếu không
 *     nhận được phản hồi kịp — lỗi trong listener của dự án mà làm trả 500 thì
 *     Zalo gửi lại, dẫn tới xử lý trùng.
 */
class WebhookController
{
    public function __invoke(Request $request, WebhookDispatcher $dispatcher): JsonResponse
    {
        $raw = $request->getContent();

        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true) ?: [];

        if (! $this->verify($request, $raw, $payload)) {
            // 401 để Zalo (và bạn) biết là chữ ký sai chứ không phải app lỗi.
            return response()->json(['error' => 'invalid signature'], 401);
        }

        if (config('zalo.webhook.queue', true)) {
            $job = HandleZaloWebhook::dispatch($payload);

            if (($queue = config('zalo.webhook.queue_name')) !== null) {
                $job->onQueue((string) $queue);
            }

            return response()->json(['ok' => true]);
        }

        // Xử lý đồng bộ: nuốt exception để không biến lỗi của listener thành
        // 500, vì Zalo sẽ hiểu là thất bại và gửi lại cùng một sự kiện.
        try {
            $dispatcher->dispatch(WebhookEvent::fromPayload($payload));
        } catch (Throwable $e) {
            Log::error('Xử lý webhook Zalo thất bại', [
                'event' => $payload['event_name'] ?? null,
                'exception' => $e,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function verify(Request $request, string $raw, array $payload): bool
    {
        $appKey = $this->appKeyFor((string) ($payload['app_id'] ?? ''));

        /** @var array<string, string>|null $app */
        $app = config('zalo.apps.'.$appKey);

        if ($app === null || empty($app['webhook_secret'])) {
            Log::warning('Webhook Zalo bị từ chối: chưa cấu hình ZALO_WEBHOOK_SECRET.');

            // Fail-closed. Chưa cấu hình secret thì không có cách nào phân biệt
            // webhook thật với request giả mạo — từ chối là lựa chọn duy nhất.
            return false;
        }

        $verifier = new SignatureVerifier(
            appId: (string) $app['app_id'],
            secret: (string) $app['webhook_secret'],
            tolerance: (int) config('zalo.webhook.tolerance', 300),
        );

        return $verifier->verify(
            rawBody: $raw,
            timestamp: (string) ($payload['timestamp'] ?? ''),
            signature: $request->header('X-ZEvent-Signature'),
        );
    }

    /** Tìm app nào trong config khớp với app_id trong payload. */
    private function appKeyFor(string $appId): string
    {
        /** @var array<string, array<string, string>> $apps */
        $apps = (array) config('zalo.apps', []);

        foreach ($apps as $key => $app) {
            if (($app['app_id'] ?? null) === $appId && $appId !== '') {
                return $key;
            }
        }

        return (string) config('zalo.default_app', 'default');
    }
}
