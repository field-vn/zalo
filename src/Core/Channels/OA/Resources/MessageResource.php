<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Channels\OA\Messages\Message;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;
use FieldVn\Zalo\Core\Http\Response;

final class MessageResource extends Resource
{
    /** Gửi tin tư vấn (trong 48h kể từ tin cuối của người dùng). */
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
