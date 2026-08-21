<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Testing;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Http\Response;

/**
 * Transport chặn mọi lời gọi mạng và ghi lại.
 *
 * Mỗi channel có một instance riêng gắn nhãn slug của nó, nhưng tất cả cùng
 * ghi vào một RequestRecorder — nhờ vậy assert được xuyên qua nhiều OA.
 */
final class RecordingTransport implements Transport
{
    public function __construct(
        private readonly RequestRecorder $recorder,
        /** Ví dụ: "oa:cskh" */
        private readonly string $channel,
    ) {}

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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function record(string $method, string $url, array $payload, array $headers): Response
    {
        return $this->recorder->record(
            new RecordedRequest($this->channel, $method, $url, $payload, $headers)
        );
    }
}
