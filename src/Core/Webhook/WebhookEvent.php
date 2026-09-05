<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Webhook;

/**
 * Một sự kiện webhook đã chuẩn hoá.
 *
 * Zalo đặt id của OA / user ở chỗ khác nhau tuỳ loại sự kiện:
 * - `user_send_*`: sender=user, recipient=OA
 * - `user_received_message` / `user_seen_message`: sender=OA, recipient=user
 *   (thường kèm `oa_id`, `user_id_by_app`)
 * - follow/unfollow: `oa_id` + `follower.id`
 * - `oa_*`: sender=OA
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
        $eventName = (string) ($payload['event_name'] ?? '');

        // Biên nhận giao tin OA→user: OA là bên gửi (hoặc trường oa_id).
        if (self::isDeliveryReceipt($eventName)) {
            if (isset($payload['oa_id'])) {
                return (string) $payload['oa_id'];
            }

            if (isset($payload['sender']['id'])) {
                return (string) $payload['sender']['id'];
            }

            return '';
        }

        if (isset($payload['oa_id'])) {
            return (string) $payload['oa_id'];
        }

        // Tin nhắn do OA gửi (echo): OA là bên gửi — trước recipient (user).
        if (isset($payload['sender']['id']) && str_starts_with($eventName, 'oa_')) {
            return (string) $payload['sender']['id'];
        }

        // Tin nhắn từ người dùng: OA là bên nhận.
        if (isset($payload['recipient']['id'])) {
            return (string) $payload['recipient']['id'];
        }

        return '';
    }

    private static function isDeliveryReceipt(string $eventName): bool
    {
        return $eventName === 'user_received_message'
            || $eventName === 'user_seen_message';
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
        // Biên nhận giao tin: user là bên nhận (không phải sender=OA).
        // Prefer recipient.id (OA user id, same space as follower.id / sender.id)
        // over user_id_by_app (app-scoped id that does not match zl_contacts.zalo_user_id).
        if (self::isDeliveryReceipt($this->name)) {
            if (isset($this->payload['recipient']['id'])) {
                return (string) $this->payload['recipient']['id'];
            }

            if (isset($this->payload['user_id_by_app'])) {
                return (string) $this->payload['user_id_by_app'];
            }

            return null;
        }

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
