<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Messages;

use InvalidArgumentException;

/**
 * Tin nhắn văn bản gửi tới một người dùng.
 *
 *     TextMessage::to($userId)
 *         ->text('Đơn hàng của bạn đã được xác nhận')
 *         ->button(Button::url('Xem đơn', $link));
 */
final class TextMessage implements Message
{
    private const MAX_LENGTH = 2000;

    private string $text = '';

    /** @var list<Button> */
    private array $buttons = [];

    private function __construct(private readonly string $userId)
    {
        if ($userId === '') {
            throw new InvalidArgumentException('userId không được rỗng.');
        }
    }

    public static function to(string $userId): self
    {
        return new self($userId);
    }

    public function text(string $text): self
    {
        $length = mb_strlen($text);

        if ($length === 0) {
            throw new InvalidArgumentException('Nội dung tin nhắn không được rỗng.');
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Tin nhắn dài %d ký tự, vượt giới hạn %d của Zalo.',
                $length,
                self::MAX_LENGTH,
            ));
        }

        $clone = clone $this;
        $clone->text = $text;

        return $clone;
    }

    public function button(Button $button): self
    {
        $clone = clone $this;
        $clone->buttons[] = $button;

        return $clone;
    }

    /** @param list<Button> $buttons */
    public function buttons(array $buttons): self
    {
        $clone = clone $this;
        $clone->buttons = $buttons;

        return $clone;
    }

    public function toPayload(): array
    {
        if ($this->text === '') {
            throw new InvalidArgumentException('Chưa đặt nội dung — gọi ->text() trước khi gửi.');
        }

        $message = ['text' => $this->text];

        if ($this->buttons !== []) {
            $message['attachment'] = [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $this->text,
                    'buttons' => array_map(
                        static fn (Button $b): array => $b->toPayload(),
                        $this->buttons,
                    ),
                ],
            ];
        }

        return [
            'recipient' => ['user_id' => $this->userId],
            'message' => $message,
        ];
    }
}
