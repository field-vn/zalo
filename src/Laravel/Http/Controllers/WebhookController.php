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
            // Trả 200 nhưng KHÔNG xử lý.
            //
            // Zalo gửi một POST kiểm tra kết nối khi bạn khai webhook URL, và
            // chỉ chấp nhận URL nào trả về 200. Request đó không phải sự kiện
            // thật nên không có chữ ký hợp lệ — trả 401 ở đây làm webhook
            // KHÔNG BAO GIỜ thiết lập được.
            //
            // Fail-closed nằm ở chỗ không dispatch event, không ghi DB. Mã
            // trạng thái chỉ nói cho bên gọi biết, không phải lớp bảo vệ.
            Log::warning('Webhook Zalo: chữ ký không hợp lệ, đã bỏ qua.', [
                'event' => $payload['event_name'] ?? null,
                'app_id' => $payload['app_id'] ?? null,
                'co_chu_ky' => $request->hasHeader('X-ZEvent-Signature'),
            ]);

            return response()->json(['ok' => true, 'processed' => false]);
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
            Log::warning('Webhook Zalo: chưa cấu hình ZALO_WEBHOOK_SECRET nên không xác thực được.');

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
