<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Channels\OA\Messages\ImageMessage;
use FieldVn\Zalo\Core\Channels\OA\Messages\Message;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;
use FieldVn\Zalo\Core\Http\Response;

final class MessageResource extends Resource
{
    /**
     * Gửi tin Tư vấn.
     *
     * HAI MỐC THỜI GIAN KHÁC NHAU, rất hay bị nhầm thành một:
     *
     *   48 giờ kể từ tương tác cuối  → gửi được và MIỄN PHÍ
     *   7 ngày  kể từ tương tác cuối → vẫn gửi được qua OpenAPI nhưng TÍNH PHÍ
     *   sau 7 ngày                   → OpenAPI từ chối
     *
     * Nghĩa là 48 giờ KHÔNG phải giới hạn gửi, mà là giới hạn miễn phí. Code
     * gọi hàm này ngoài khung 48 giờ vẫn chạy trơn tru và vẫn phát sinh tiền.
     *
     * "Tương tác" rộng hơn "nhắn tin": còn gồm gọi thoại, bình luận bài viết,
     * bấm menu hoặc CTA, tương tác chatbot, bấm widget.
     *
     * (OA Manager cho tới 365 ngày, nhưng đó là công cụ web của Zalo, không
     * phải OpenAPI.)
     */
    public function send(Message $message): Response
    {
        return $this->request
            ->post('/v3.0/oa/message/cs', $message->toPayload())
            ->throwIfFailed();
    }

    /** Đường tắt cho ca phổ biến nhất. */
    public function text(string $userId, string $text): Response
    {
        return $this->send(TextMessage::to($userId)->text($text));
    }

    /**
     * Đường tắt gửi ảnh. `$attachmentId` lấy từ $oa->uploads()->image().
     *
     * Nhận cả URL https:// cho tiện, nhưng attachment_id là đường chắc chắn
     * hơn — Zalo thiết kế OA quanh việc upload trước.
     */
    public function image(string $userId, string $attachmentIdOrUrl, string $caption = ''): Response
    {
        $message = str_starts_with($attachmentIdOrUrl, 'https://')
            ? ImageMessage::to($userId)->url($attachmentIdOrUrl)
            : ImageMessage::to($userId)->attachment($attachmentIdOrUrl);

        return $this->send($caption === '' ? $message : $message->caption($caption));
    }

    /** Tin nhắn giao dịch — cần OA đã được duyệt loại tin này. */
    public function transaction(Message $message): Response
    {
        return $this->request
            ->post('/v3.0/oa/message/transaction', $message->toPayload())
            ->throwIfFailed();
    }

    /** Tin nhắn truyền thông broadcast. */
    public function promotion(Message $message): Response
    {
        return $this->request
            ->post('/v3.0/oa/message/promotion', $message->toPayload())
            ->throwIfFailed();
    }
}
