<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;
use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Laravel\Jobs\HandleZaloBotWebhook;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Support\BotWebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nhận webhook từ Zalo Bot.
 *
 * KHÔNG dùng chung với WebhookController của OA, vì hai bên xác thực theo hai
 * cơ chế khác hẳn nhau (xem BotSecretVerifier). Nhét cả hai vào một controller
 * sẽ đẻ ra một hàm verify() rẽ nhánh theo kênh — chỗ dễ sai nhất trong toàn
 * bộ package, vì sai là mở toang cửa.
 *
 * Mỗi bot một URL riêng: payload của Zalo không kèm định danh bot nào, nên
 * đường dẫn là cách duy nhất để biết update thuộc về bot nào.
 */
class BotWebhookController
{
    public function __invoke(Request $request, ZaloBot $bot, BotWebhookDispatcher $dispatcher): JsonResponse
    {
        if (! $this->verify($request, $bot)) {
            return response()->json(['error' => 'invalid secret token'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?: [];

        if (config('zalo.webhook.queue', true)) {
            $job = HandleZaloBotWebhook::dispatch($payload, (int) $bot->getKey());

            if (($queue = config('zalo.webhook.queue_name')) !== null) {
                $job->onQueue((string) $queue);
            }

            return response()->json(['ok' => true]);
        }

        // Nuốt exception của listener: Zalo hiểu non-200 là thất bại và gửi
        // lại cùng một update, dẫn tới xử lý trùng.
        try {
            $dispatcher->dispatch(BotUpdate::fromPayload($payload), $bot);
        } catch (Throwable $e) {
            Log::error('Xử lý webhook Zalo Bot thất bại', [
                'bot' => $bot->slug,
                'exception' => $e,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function verify(Request $request, ZaloBot $bot): bool
    {
        $secret = (string) config('zalo.bot.webhook_secret', '');

        if (! BotSecretVerifier::isValidLength($secret)) {
            // Fail-closed. Chưa cấu hình secret thì không phân biệt được
            // webhook thật với request giả mạo.
            Log::warning('Webhook Bot bị từ chối: ZALO_BOT_WEBHOOK_SECRET thiếu hoặc sai độ dài (cần 8-256 ký tự).', [
                'bot' => $bot->slug,
            ]);

            return false;
        }

        return (new BotSecretVerifier($secret))
            ->verify($request->header(BotSecretVerifier::HEADER));
    }
}
