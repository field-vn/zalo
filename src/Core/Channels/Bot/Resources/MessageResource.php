<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\Bot\Resources;

use FieldVn\Zalo\Core\Http\Response;

final class MessageResource extends Resource
{
    public function send(string $chatId, string $text): Response
    {
        return $this->request->post('/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ])->throwIfFailed();
    }

    public function sendPhoto(string $chatId, string $photoUrl, string $caption = ''): Response
    {
        $payload = ['chat_id' => $chatId, 'photo' => $photoUrl];

        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        return $this->request->post('/sendPhoto', $payload)->throwIfFailed();
    }

    /** Sticker id lấy ở https://stickers.zaloapp.com/ */
    public function sendSticker(string $chatId, string $stickerId): Response
    {
        return $this->request->post('/sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $stickerId,
        ])->throwIfFailed();
    }

    /**
     * Hiện trạng thái "đang soạn tin" phía người dùng.
     *
     * Dùng khi phải xử lý lâu (gọi AI, tra DB chậm) — không có nó thì người
     * dùng tưởng bot chết. Trạng thái tự tắt sau vài giây hoặc khi có tin mới.
     */
    public function sendChatAction(string $chatId, string $action = 'typing'): Response
    {
        return $this->request->post('/sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ])->throwIfFailed();
    }
}
