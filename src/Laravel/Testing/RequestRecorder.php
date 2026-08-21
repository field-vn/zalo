<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Testing;

use FieldVn\Zalo\Core\Http\Response;
use Illuminate\Support\Collection;

/**
 * Nơi gom mọi request bị chặn và phát ra response giả.
 *
 * Dùng chung cho tất cả channel trong một lần `Zalo::fake()`, để assert được
 * xuyên qua nhiều OA và Bot cùng lúc.
 */
final class RequestRecorder
{
    /** @var list<RecordedRequest> */
    private array $requests = [];

    /** @var list<Response> */
    private array $queue = [];

    /** @var array<string, mixed>|null */
    private ?array $default = null;

    /** @param array<string, mixed> $data */
    public function push(array $data, int $status = 200): self
    {
        $this->queue[] = new Response($status, $data, (string) json_encode($data));

        return $this;
    }

    /**
     * Response trả về khi hàng đợi rỗng.
     *
     * Mặc định là thành công: đa số test chỉ quan tâm "đã gửi cái gì", không
     * muốn phải khai báo response cho từng lời gọi.
     *
     * @param  array<string, mixed>  $data
     */
    public function respondWith(array $data): self
    {
        $this->default = $data;

        return $this;
    }

    public function record(RecordedRequest $request): Response
    {
        $this->requests[] = $request;

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        $data = $this->default ?? ['error' => 0, 'message' => 'Success', 'data' => []];

        return new Response(200, $data, (string) json_encode($data));
    }

    /** @return Collection<int, RecordedRequest> */
    public function requests(): Collection
    {
        return collect($this->requests);
    }

    public function count(): int
    {
        return count($this->requests);
    }

    public function flush(): void
    {
        $this->requests = [];
        $this->queue = [];
    }
}
