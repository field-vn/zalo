<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Tests\Support;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Http\Response;

/**
 * Transport giả để test mà không chạm mạng.
 *
 * Ghi lại mọi request nên có thể assert cả payload gửi đi, không chỉ kết quả
 * trả về — quan trọng với luồng OAuth, nơi sai một tham số là hỏng cả luồng.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{method:string, url:string, data:array<string,mixed>, headers:array<string,string>}> */
    public array $requests = [];

    /** @var list<Response> */
    private array $queue = [];

    /** @param array<string, mixed> $data */
    public function push(array $data, int $status = 200): self
    {
        $this->queue[] = new Response($status, $data, (string) json_encode($data));

        return $this;
    }

    public function get(string $url, array $query = [], array $headers = []): Response
    {
        return $this->record('GET', $url, $query, $headers);
    }

    public function post(string $url, array $payload = [], array $headers = []): Response
    {
        return $this->record('POST', $url, $payload, $headers);
    }

    public function postForm(string $url, array $form = [], array $headers = []): Response
    {
        return $this->record('FORM', $url, $form, $headers);
    }

    public function postMultipart(string $url, array $files, array $form = [], array $headers = []): Response
    {
        return $this->record('MULTIPART', $url, $form + ['__files' => $files], $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function record(string $method, string $url, array $data, array $headers): Response
    {
        $this->requests[] = compact('method', 'url', 'data', 'headers');

        return array_shift($this->queue) ?? new Response(200, []);
    }

    /** @return array{method:string, url:string, data:array<string,mixed>, headers:array<string,string>}|null */
    public function lastRequest(): ?array
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    /** @return array{method:string, url:string, data:array<string,mixed>, headers:array<string,string>}|null */
    public function firstRequest(): ?array
    {
        return $this->requests[0] ?? null;
    }
}
