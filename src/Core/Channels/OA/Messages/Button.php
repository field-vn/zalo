<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Messages;

use InvalidArgumentException;

final class Button
{
    private function __construct(
        private readonly string $title,
        private readonly string $type,
        /** @var array<string, mixed> */
        private readonly array $payload,
    ) {
        if ($title === '') {
            throw new InvalidArgumentException('Tiêu đề nút không được rỗng.');
        }
    }

    public static function url(string $title, string $url): self
    {
        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('Zalo yêu cầu URL của nút phải là HTTPS.');
        }

        return new self($title, 'oa.open.url', ['url' => $url]);
    }

    public static function phone(string $title, string $phone): self
    {
        return new self($title, 'oa.open.phone', ['phone_code' => $phone]);
    }

    public static function query(string $title, string $payload): self
    {
        return new self($title, 'oa.query.show', ['payload' => $payload]);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'title'   => $this->title,
            'type'    => $this->type,
            'payload' => $this->payload,
        ];
    }
}
