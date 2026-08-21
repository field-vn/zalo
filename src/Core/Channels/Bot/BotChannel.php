<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\Bot;

use FieldVn\Zalo\Contracts\Channel;
use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Channels\Bot\Resources\MessageResource;
use FieldVn\Zalo\Core\Channels\Bot\Resources\UpdateResource;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Core\Http\PendingRequest;
use FieldVn\Zalo\Core\Http\Response;
use Illuminate\Support\Traits\Macroable;

/**
 * Một Zalo Bot.
 *
 * Khác OA hoàn toàn ở tầng xác thực: token tĩnh nằm ngay trong URL, kiểu
 * Telegram. Không dính Zalo App, không OAuth, không refresh. Đây chính là
 * lý do OA và Bot không thể là hai driver của cùng một interface (ADR-0001).
 */
final class BotChannel implements Channel
{
    use Macroable;

    public function __construct(
        private readonly string $slug,
        private readonly Transport $transport,
        private readonly string $token,
        private readonly string $baseUrl = 'https://bot-api.zapps.me/bot',
    ) {}

    public function key(): string
    {
        return $this->slug;
    }

    public function messages(): MessageResource
    {
        return new MessageResource($this->request());
    }

    public function updates(): UpdateResource
    {
        return new UpdateResource($this->request());
    }

    public function me(): Response
    {
        return $this->request()->get('/getMe')->throwIfFailed();
    }

    public function ping(): bool
    {
        try {
            return $this->me()->successful();
        } catch (ZaloException) {
            return false;
        }
    }

    public function request(): PendingRequest
    {
        // Token nằm trong path chứ không phải header.
        return new PendingRequest(
            $this->transport,
            rtrim($this->baseUrl, '/').'/'.$this->token,
            static fn (): array => [],
        );
    }
}
