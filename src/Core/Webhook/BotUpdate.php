<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Webhook;

/**
 * Một update từ Zalo Bot, đọc theo kiểu chịu được thay đổi.
 *
 * Bot API của Zalo còn đang biến động: cùng một khái niệm xuất hiện ở nhiều
 * đường dẫn khác nhau tuỳ endpoint và tuỳ thời điểm. Cố định một đường dẫn
 * duy nhất là cách chắc chắn để hỏng âm thầm — bảng hiện toàn dấu gạch mà
 * không ai biết vì sao.
 *
 * Nên ở đây dò lần lượt các vị trí đã từng quan sát được, và luôn giữ
 * `payload` gốc để dự án tự đọc thứ package chưa bọc.
 */
final class BotUpdate
{
    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function chatId(): ?string
    {
        return $this->firstString([
            'message.chat.id',
            'message.chat_id',
            'chat.id',
            'chat_id',
            'message.from.id',
            'from.id',
        ]);
    }

    public function senderName(): ?string
    {
        return $this->firstString([
            'message.from.display_name',
            'from.display_name',
            'message.chat.title',
            'chat.title',
        ]);
    }

    public function text(): ?string
    {
        return $this->firstString(['message.text', 'text']);
    }

    public function messageId(): ?string
    {
        return $this->firstString(['message.message_id', 'message_id']);
    }

    public function updateId(): ?string
    {
        return $this->firstString(['update_id', 'event_id']);
    }

    /**
     * Tên sự kiện, nếu Zalo có gửi.
     *
     * Chưa quan sát thấy trường cố định nào, nên suy ra từ nội dung: có text
     * thì coi là tin nhắn. Dự án cần chính xác hơn thì đọc `payload`.
     */
    public function eventName(): string
    {
        return $this->firstString(['event_name', 'event'])
            ?? ($this->text() !== null ? 'message.text' : 'unknown');
    }

    public function isMessage(): bool
    {
        return $this->text() !== null || $this->messageId() !== null;
    }

    /** @param list<string> $paths */
    private function firstString(array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($this->payload, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
