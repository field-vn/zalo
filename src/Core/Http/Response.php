<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Http;

use ArrayAccess;
use FieldVn\Zalo\Core\Exceptions\ApiException;

/**
 * Response đã chuẩn hoá.
 *
 * Điểm quan trọng: Zalo trả HTTP 200 kèm `error != 0` cho phần lớn lỗi
 * nghiệp vụ, nên successful() phải xét cả body chứ không chỉ status code.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Response implements ArrayAccess
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly int $status,
        public readonly array $data = [],
        public readonly string $raw = '',
    ) {}

    public function successful(): bool
    {
        if ($this->status < 200 || $this->status >= 300) {
            return false;
        }

        // Hai kênh dùng hai quy ước khác nhau và KHÔNG thể gộp:
        //   OA  -> {"error": 0, "message": "Success", "data": {...}}
        //   Bot -> {"ok": true, "result": {...}}
        // Chỉ xét `error` thì lỗi của Bot (ok=false, không có `error`) lọt
        // qua thành công — sendMessage hỏng mà code vẫn tưởng đã gửi.
        if (array_key_exists('ok', $this->data)) {
            return (bool) $this->data['ok'];
        }

        return $this->errorCode() === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function errorCode(): int
    {
        return (int) ($this->data['error'] ?? $this->data['error_code'] ?? 0);
    }

    public function errorMessage(): string
    {
        return (string) (
            $this->data['message']
            ?? $this->data['description']
            ?? $this->data['error_name']
            ?? ''
        );
    }

    /**
     * Nội dung nghiệp vụ: OA bọc trong `data`, Bot bọc trong `result`.
     *
     * Thiếu nhánh `result` thì mọi thứ đọc từ Bot đều rỗng — ví dụ username
     * của getMe không bao giờ lưu được vì code đi tìm $payload['username']
     * trong khi nó nằm ở $body['result']['username'].
     */
    public function payload(): mixed
    {
        return $this->data['data'] ?? $this->data['result'] ?? $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @throws ApiException */
    public function throwIfFailed(): self
    {
        if ($this->failed()) {
            throw ApiException::fromResponse($this);
        }

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return data_get($this->data, $offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return data_get($this->data, $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Response là immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Response là immutable.');
    }
}
