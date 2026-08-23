<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\Bot\Resources;

use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
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

    /**
     * Cắm webhook cho bot.
     *
     * `secret_token` là BẮT BUỘC với Zalo (khác Telegram, nơi nó tuỳ chọn):
     * để rỗng thì Zalo trả 400 "The secret_token must not be empty". Chặn
     * ngay ở đây để báo lỗi có ngữ cảnh, thay vì đẩy request hỏng đi rồi
     * nhận về một câu khó hiểu.
     *
     * Giá trị này do BẠN đặt; Zalo gửi trả nguyên văn ở header mỗi lần gọi
     * webhook để bạn xác thực. Nó không liên quan gì tới OA Secret Key.
     *
     * @throws ConfigurationException khi thiếu secret
     */
    public function setWebhook(string $url, string $secret): Response
    {
        if (trim($secret) === '') {
            throw new ConfigurationException(
                'Zalo bắt buộc secret_token khi cắm webhook cho Bot. '
                .'Đặt ZALO_BOT_WEBHOOK_SECRET trong .env — tự sinh bằng: '
                .'php -r "echo bin2hex(random_bytes(24));"'
            );
        }

        return $this->request
            ->post('/setWebhook', ['url' => $url, 'secret_token' => $secret])
            ->throwIfFailed();
    }

    public function deleteWebhook(): Response
    {
        return $this->request->post('/deleteWebhook')->throwIfFailed();
    }
}
