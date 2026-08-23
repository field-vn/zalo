<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Http;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Exceptions\TransportException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

final class GuzzleTransport implements Transport
{
    private ClientInterface $client;

    /** @param array{times?:int, sleep?:int, on?: list<int>} $retry */
    public function __construct(
        ?ClientInterface $client = null,
        private readonly array $retry = ['times' => 3, 'sleep' => 200, 'on' => [429, 500, 502, 503, 504]],
        float $timeout = 10.0,
        float $connectTimeout = 5.0,
    ) {
        $this->client = $client ?? new Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'http_errors' => false,
        ]);
    }

    public function get(string $url, array $query = [], array $headers = []): Response
    {
        return $this->send('GET', $url, ['query' => $query, 'headers' => $headers]);
    }

    public function post(string $url, array $payload = [], array $headers = []): Response
    {
        return $this->send('POST', $url, [
            'json' => $payload,
            'headers' => $headers + ['Content-Type' => 'application/json'],
        ]);
    }

    public function postForm(string $url, array $form = [], array $headers = []): Response
    {
        return $this->send('POST', $url, [
            'form_params' => $form,
            'headers' => $headers,
        ]);
    }

    public function postMultipart(string $url, array $files, array $form = [], array $headers = []): Response
    {
        $parts = [];
        $handles = [];

        foreach ($files as $field => $path) {
            if (! is_readable($path)) {
                throw new TransportException("Không đọc được file để upload: {$path}");
            }

            $handle = fopen($path, 'r');

            if ($handle === false) {
                throw new TransportException("Không mở được file để upload: {$path}");
            }

            $handles[] = $handle;
            $parts[] = ['name' => $field, 'contents' => $handle, 'filename' => basename($path)];
        }

        foreach ($form as $field => $value) {
            $parts[] = ['name' => $field, 'contents' => (string) $value];
        }

        try {
            // Không tự đặt Content-Type: Guzzle phải tự sinh boundary.
            return $this->send('POST', $url, ['multipart' => $parts, 'headers' => $headers]);
        } finally {
            // finally chứ không phải sau return: request ném exception thì
            // handle vẫn phải đóng, nếu không sẽ rò file descriptor.
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }

    /** @param array<string, mixed> $options */
    private function send(string $method, string $url, array $options): Response
    {
        $times = max(1, (int) ($this->retry['times'] ?? 3));
        $sleepMs = (int) ($this->retry['sleep'] ?? 200);
        $retryOn = $this->retry['on'] ?? [];
        $lastError = null;

        for ($attempt = 1; $attempt <= $times; $attempt++) {
            try {
                /** @var ResponseInterface $psr */
                $psr = $this->client->request($method, $url, $options);
                $response = $this->toResponse($psr);

                // Chỉ retry lỗi tạm thời. Lỗi nghiệp vụ (error != 0) trả về ngay
                // để caller quyết định — retry chỉ làm chậm và tốn quota.
                if (! in_array($response->status, $retryOn, true) || $attempt === $times) {
                    return $response;
                }
            } catch (ConnectException|RequestException $e) {
                $lastError = $e;

                if ($attempt === $times) {
                    break;
                }
            } catch (TransferException $e) {
                throw new TransportException('Lỗi kết nối tới Zalo: '.$e->getMessage(), $e);
            }

            // Backoff nhân đôi: 200ms → 400ms → 800ms
            usleep($sleepMs * 1000 * (2 ** ($attempt - 1)));
        }

        throw new TransportException(
            'Không gọi được Zalo API sau '.$times.' lần thử: '
                .($lastError?->getMessage() ?? 'không rõ nguyên nhân'),
            $lastError,
        );
    }

    private function toResponse(ResponseInterface $psr): Response
    {
        $raw = (string) $psr->getBody();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true) ?: [];

        return new Response($psr->getStatusCode(), $decoded, $raw);
    }
}
