<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use Illuminate\Support\Collection;

/**
 * Nguồn danh sách OA. Mặc định là Eloquent, nhưng dự án dùng nguồn khác
 * (config thuần, API nội bộ, DB của tenant) có thể bind implementation riêng.
 */
interface OaRepository
{
    public function find(string|int $key): ?object;

    public function default(): ?object;

    /** @return Collection<int, object> */
    public function active(): Collection;

    /** @return Collection<int, object> */
    public function all(): Collection;
}
