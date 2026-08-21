<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Repositories;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;

final class EloquentOaRepository implements OaRepository
{
    public function find(string|int $key): ?ZaloOa
    {
        return ZaloOa::query()
            ->with('token')
            ->when(
                is_numeric($key),
                fn ($q) => $q->whereKey((int) $key),
                fn ($q) => $q->where('slug', (string) $key),
            )
            ->first();
    }

    /**
     * OA mặc định = OA active đầu tiên.
     *
     * Không dùng cột `is_default` riêng: nó sinh ra bài toán đảm bảo duy nhất
     * một bản ghi được đánh dấu, mà lợi ích thì gần như bằng không — dự án
     * nhiều OA thì gọi theo slug rõ ràng vẫn tốt hơn.
     */
    public function default(): ?ZaloOa
    {
        return ZaloOa::query()->with('token')->active()->orderBy('id')->first();
    }

    /** @return Collection<int, ZaloOa> */
    public function active(): Collection
    {
        return ZaloOa::query()->with('token')->active()->orderBy('name')->get();
    }

    /** @return Collection<int, ZaloOa> */
    public function all(): Collection
    {
        return ZaloOa::query()->with('token')->orderBy('name')->get();
    }
}
