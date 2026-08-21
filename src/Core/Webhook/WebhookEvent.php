<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Webhook;

/**
 * Một sự kiện webhook đã chuẩn hoá.
 *
 * Zalo đặt id của OA ở chỗ khác nhau tuỳ loại sự kiện — `recipient.id` với
 * tin nhắn, `oa_id` với follow/unfollow. Class này gom lại một chỗ để code
 * nghiệp vụ không phải nhớ từng ngoại lệ.
 */
final class WebhookEvent
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public readonly string $name,
        public readonly string $appId,
        public readonly string $oaId,
        public readonly string $timestamp,
        public readonly array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            name: (string) ($payload['event_name'] ?? 'unknown'),
            appId: (string) ($payload['app_id'] ?? ''),
            oaId: self::extractOaId($payload),
            timestamp: (string) ($payload['timestamp'] ?? ''),
            payload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function extractOaId(array $payload): string
    {
        if (isset($payload['oa_id'])) {
            return (string) $payload['oa_id'];
        }

        // Tin nhắn từ người dùng: OA là bên nhận.
        if (isset($payload['recipient']['id'])) {
            return (string) $payload['recipient']['id'];
        }

        // Tin nhắn do OA gửi (echo): OA là bên gửi.
        if (isset($payload['sender']['id']) && str_starts_with((string) ($payload['event_name'] ?? ''), 'oa_')) {
            return (string) $payload['sender']['id'];
        }

        return '';
    }

    public function isFromUser(): bool
    {
        return str_starts_with($this->name, 'user_');
    }

    public function isMessage(): bool
    {
        return str_starts_with($this->name, 'user_send_')
            || str_starts_with($this->name, 'oa_send_');
    }

    public function isFollow(): bool
    {
        return $this->name === 'follow';
    }

    public function isUnfollow(): bool
    {
        return $this->name === 'unfollow';
    }

    /** Id người dùng Zalo liên quan tới sự kiện. */
    public function userId(): ?string
    {
        return match (true) {
            isset($this->payload['follower']['id']) => (string) $this->payload['follower']['id'],
            isset($this->payload['sender']['id']) => (string) $this->payload['sender']['id'],
            isset($this->payload['user_id_by_app']) => (string) $this->payload['user_id_by_app'],
            default => null,
        };
    }

    public function messageId(): ?string
    {
        $id = $this->payload['message']['msg_id'] ?? null;

        return $id === null ? null : (string) $id;
    }

    public function text(): ?string
    {
        $text = $this->payload['message']['text'] ?? null;

        return $text === null ? null : (string) $text;
    }

    /** @return list<array<string, mixed>> */
    public function attachments(): array
    {
        /** @var list<array<string, mixed>> */
        return (array) ($this->payload['message']['attachments'] ?? []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
