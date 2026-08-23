<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Messages;

use InvalidArgumentException;

/**
 * Lối thoát: gửi bất kỳ payload nào Zalo hỗ trợ mà package chưa bọc.
 *
 * Lý do phải có, và phải có NGAY từ đầu: Zalo OA còn nhiều dạng tin
 * (list/carousel, request_user_info, file, template giao dịch...) mà package
 * chưa xác minh được payload thật. Không có lối thoát này thì người dùng gặp
 * dạng tin lạ là phải fork package hoặc bỏ nó — cả hai đều tệ hơn nhiều so
 * với việc cho họ tự dựng mảng.
 *
 *     RawMessage::to($userId)->message([
 *         'attachment' => [
 *             'type' => 'template',
 *             'payload' => ['template_type' => 'list', 'elements' => [...]],
 *         ],
 *     ]);
 *
 * Hoặc thay cả payload khi hình dạng `recipient` cũng khác:
 *
 *     RawMessage::payload(['recipient' => [...], 'message' => [...]]);
 *
 * Dùng cái này nghĩa là bạn tự chịu trách nhiệm về hình dạng payload —
 * package không validate gì ngoài việc mảng không rỗng. Nếu bạn xác minh
 * được một dạng tin chạy thật, hãy mở issue để nó thành class có kiểu.
 */
final class RawMessage implements Message
{
    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function payload(array $payload): self
    {
        if ($payload === []) {
            throw new InvalidArgumentException('Payload rỗng.');
        }

        return new self($payload);
    }

    public static function to(string $userId): self
    {
        if ($userId === '') {
            throw new InvalidArgumentException('userId không được rỗng.');
        }

        return new self(['recipient' => ['user_id' => $userId]]);
    }

    /** @param array<string, mixed> $message */
    public function message(array $message): self
    {
        if ($message === []) {
            throw new InvalidArgumentException('Nội dung `message` rỗng.');
        }

        $clone = clone $this;
        $clone->payload['message'] = $message;

        return $clone;
    }

    public function toPayload(): array
    {
        if (! isset($this->payload['message'])) {
            throw new InvalidArgumentException(
                'Thiếu khoá `message` — gọi ->message([...]) hoặc dùng RawMessage::payload() với payload đầy đủ.'
            );
        }

        return $this->payload;
    }
}
