<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Testing;

/**
 * Một lời gọi API đã bị chặn lại trong lúc test.
 *
 * Có sẵn helper cho những thứ người ta assert nhiều nhất, để test đọc lên
 * giống mô tả nghiệp vụ chứ không phải mò trong mảng payload.
 */
final class RecordedRequest
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function __construct(
        /** Ví dụ: "oa:cskh" hoặc "bot:support" */
        public readonly string $channel,
        public readonly string $method,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers = [],
    ) {}

    public function isOa(): bool
    {
        return str_starts_with($this->channel, 'oa:');
    }

    public function isBot(): bool
    {
        return str_starts_with($this->channel, 'bot:');
    }

    /** Slug của OA hoặc Bot đã gửi request này. */
    public function slug(): string
    {
        return substr($this->channel, (int) strpos($this->channel, ':') + 1);
    }

    public function isMessage(): bool
    {
        return str_contains($this->url, '/message')
            || str_contains($this->url, '/sendMessage')
            || str_contains($this->url, '/sendPhoto');
    }

    /** Người nhận — OA dùng recipient.user_id, Bot dùng chat_id. */
    public function userId(): ?string
    {
        $id = $this->payload['recipient']['user_id']
            ?? $this->payload['chat_id']
            ?? $this->payload['user_id']
            ?? null;

        return $id === null ? null : (string) $id;
    }

    public function text(): ?string
    {
        $text = $this->payload['message']['text']
            ?? $this->payload['message']['attachment']['payload']['text']
            ?? $this->payload['text']
            ?? null;

        return $text === null ? null : (string) $text;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    public function __toString(): string
    {
        return sprintf('%s %s %s', $this->channel, $this->method, $this->url);
    }
}
