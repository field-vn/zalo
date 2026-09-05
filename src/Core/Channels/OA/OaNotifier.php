<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Core\Http\Response;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Facades\Log;

/**
 * Chọn kênh rồi gửi: ưu tiên OA CS theo user id, fallback ZBS theo SĐT.
 *
 * Thứ tự quyết định (dừng ở nhánh đầu khớp) nằm ở `send()` — xem PHPDoc từng
 * bước và config `zalo.notifier.*`.
 */
final class OaNotifier
{
    public function __construct(
        private readonly OAChannel $oa,
    ) {}

    public function send(ZaloRecipient $to, ZaloOutboundMessage $message): NotifyResult
    {
        $channel = NotifyResult::CHANNEL_NONE;

        try {
            $userId = $this->nonEmpty($to->zaloUserId);
            $phone = $this->nonEmpty($to->phone);

            // 1. Không có người nhận nào.
            if ($userId === null && $phone === null) {
                return NotifyResult::skipped('recipient_empty');
            }

            $status = $this->oa->tokenStatus();
            $staleBelow = (int) config('zalo.notifier.stale_below_minutes', 60);
            $freshBuffer = (int) config('zalo.notifier.fresh_buffer_minutes', 1440);

            // Log cận hạn — không đổi kênh.
            if (
                $status->remainingMinutes > $staleBelow
                && $status->remainingMinutes <= $freshBuffer
            ) {
                Log::info('Zalo OA token gần hết hạn', [
                    'oa' => $this->oa->key(),
                    'remaining_minutes' => $status->remainingMinutes,
                ]);
            }

            // 2. Token stale / missing → chỉ ZBS (nếu đủ điều kiện).
            if ($status->remainingMinutes < $staleBelow) {
                if ($phone !== null && $this->canSendZbs($message)) {
                    $channel = NotifyResult::CHANNEL_ZBS;

                    return $this->sendZbs($phone, $message);
                }

                return NotifyResult::skipped('token_stale');
            }

            // 3. Có user id + token không stale → thử CS (trừ khi contact chặn).
            if ($userId !== null) {
                if ($this->contactBlocksCs($userId)) {
                    // nhảy bước 4
                } else {
                    $text = $this->nonEmpty($message->text);

                    if ($text !== null) {
                        $channel = NotifyResult::CHANNEL_OA_CS;
                        $response = $this->oa->messages()->text($userId, $text);

                        return NotifyResult::sent(
                            NotifyResult::CHANNEL_OA_CS,
                            $this->messageIdFrom($response),
                        );
                    }
                }
            }

            // 4. Fallback ZBS.
            if ($phone !== null && $this->canSendZbs($message)) {
                $channel = NotifyResult::CHANNEL_ZBS;

                return $this->sendZbs($phone, $message);
            }

            return NotifyResult::skipped('zbs_unavailable');
        } catch (ZaloException|ConfigurationException $e) {
            // 5. Lỗi API / cấu hình — giữ channel đang thử (CS fail không fallback ZBS).
            return NotifyResult::failed(
                $channel === NotifyResult::CHANNEL_NONE ? NotifyResult::CHANNEL_NONE : $channel,
                $e->getMessage(),
            );
        }
    }

    private function sendZbs(string $phone, ZaloOutboundMessage $message): NotifyResult
    {
        $response = $this->oa->zbs()->send(
            $phone,
            (string) $message->templateId,
            $message->templateData,
        );

        return NotifyResult::sent(
            NotifyResult::CHANNEL_ZBS,
            $this->messageIdFrom($response),
        );
    }

    private function canSendZbs(ZaloOutboundMessage $message): bool
    {
        return $this->nonEmpty($message->templateId) !== null
            && $message->templateData !== [];
    }

    /**
     * Contact đã unfollow hoặc ngoài cửa sổ CS → không gọi OA CS.
     * Không có row contact → vẫn thử CS (app identity link vẫn có thể gửi được).
     */
    private function contactBlocksCs(string $zaloUserId): bool
    {
        $oa = ZaloOa::query()->where('slug', $this->oa->key())->first();

        if ($oa === null) {
            return false;
        }

        $contact = ZaloContact::query()
            ->where('oa_id', $oa->getKey())
            ->where('zalo_user_id', $zaloUserId)
            ->first();

        if ($contact === null) {
            return false;
        }

        if ($contact->is_following === false) {
            return true;
        }

        $windowDays = (int) config('zalo.notifier.cs_window_days', 7);

        return $contact->last_interaction_at->lt(now()->subDays($windowDays));
    }

    private function messageIdFrom(Response $response): mixed
    {
        $payload = $response->payload();

        if (! is_array($payload)) {
            return null;
        }

        return $payload['message_id'] ?? $payload['msg_id'] ?? null;
    }

    private function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
