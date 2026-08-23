<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Http;

use FieldVn\Zalo\Contracts\Transport;

/**
 * Ngữ cảnh gọi API mà Resource dùng chung: base URL + header xác thực.
 *
 * Header được resolve lười (closure) chứ không truyền sẵn giá trị, để mỗi
 * request lấy access_token mới nhất — token có thể vừa được refresh giữa chừng.
 */
final class PendingRequest
{
    /** @param  callable(): array<string, string>  $headers */
    public function __construct(
        private readonly Transport $transport,
        private readonly string $baseUrl,
        private $headers,
    ) {}

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): Response
    {
        return $this->transport->get($this->url($path), $query, $this->headers());
    }

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload = []): Response
    {
        return $this->transport->post($this->url($path), $payload, $this->headers());
    }

    /**
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $form
     */
    public function postMultipart(string $path, array $files, array $form = []): Response
    {
        return $this->transport->postMultipart($this->url($path), $files, $form, $this->headers());
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ($this->headers)();
    }
}
