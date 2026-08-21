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
            'text'    => $text,
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
}
