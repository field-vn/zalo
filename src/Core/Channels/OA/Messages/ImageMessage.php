<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Messages;

use InvalidArgumentException;

/**
 * Gửi ảnh tới một người dùng qua OA.
 *
 *     $id = $oa->uploads()->image('/duong/dan/anh.jpg');
 *
 *     ImageMessage::to($userId)
 *         ->attachment($id)
 *         ->caption('Ảnh sản phẩm');
 *
 * Khác Bot: Bot nhận thẳng URL ảnh, OA bắt upload trước lấy attachment_id.
 * Lớp này chấp nhận cả URL vì có tài liệu nhắc tới, nhưng attachment_id mới
 * là đường đã được xác nhận — xem docs/zalo/02-test-thuc-te.md.
 */
final class ImageMessage implements Message
{
    private const MAX_CAPTION = 2000;

    private ?string $attachmentId = null;

    private ?string $url = null;

    private string $caption = '';

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

    /** attachment_id lấy từ $oa->uploads()->image(). */
    public function attachment(string $attachmentId): self
    {
        if ($attachmentId === '') {
            throw new InvalidArgumentException('attachment_id không được rỗng.');
        }

        $clone = clone $this;
        $clone->attachmentId = $attachmentId;
        $clone->url = null;

        return $clone;
    }

    public function url(string $url): self
    {
        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('Zalo yêu cầu URL ảnh phải là HTTPS.');
        }

        $clone = clone $this;
        $clone->url = $url;
        $clone->attachmentId = null;

        return $clone;
    }

    public function caption(string $caption): self
    {
        if (mb_strlen($caption) > self::MAX_CAPTION) {
            throw new InvalidArgumentException(sprintf(
                'Chú thích dài %d ký tự, vượt giới hạn %d.',
                mb_strlen($caption),
                self::MAX_CAPTION,
            ));
        }

        $clone = clone $this;
        $clone->caption = $caption;

        return $clone;
    }

    public function toPayload(): array
    {
        if ($this->attachmentId === null && $this->url === null) {
            throw new InvalidArgumentException(
                'Chưa có ảnh — gọi ->attachment($id) sau khi upload, hoặc ->url($https).'
            );
        }

        $element = ['media_type' => 'image'];

        if ($this->attachmentId !== null) {
            $element['attachment_id'] = $this->attachmentId;
        } else {
            $element['url'] = (string) $this->url;
        }

        $payload = [
            'template_type' => 'media',
            'elements' => [$element],
        ];

        // Chú thích là tuỳ chọn: gửi khoá rỗng có thể bị Zalo từ chối, nên
        // chỉ thêm khi thực sự có.
        if ($this->caption !== '') {
            $payload['text'] = $this->caption;
        }

        return [
            'recipient' => ['user_id' => $this->userId],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => $payload,
                ],
            ],
        ];
    }
}
