<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\Bot\Resources;

use FieldVn\Zalo\Core\Http\Response;

final class UpdateResource extends Resource
{
    /** Long polling — dùng khi không muốn dựng webhook công khai. */
    public function poll(int $offset = 0, int $timeout = 30): Response
    {
        return $this->request->get('/getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
        ])->throwIfFailed();
    }

    public function setWebhook(string $url, string $secret = ''): Response
    {
        $payload = ['url' => $url];

        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        return $this->request->post('/setWebhook', $payload)->throwIfFailed();
    }

    public function deleteWebhook(): Response
    {
        return $this->request->post('/deleteWebhook')->throwIfFailed();
    }
}
