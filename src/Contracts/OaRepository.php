<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;

/**
 * Nguồn danh sách OA. Mặc định là Eloquent, nhưng dự án dùng nguồn khác
 * (config thuần, API nội bộ, DB của tenant) có thể bind implementation riêng —
 * chỉ cần trả về instance ZaloOa, không bắt buộc phải đọc từ DB.
 *
 * Lưu ý: đây là contract hướng Laravel (trả về Eloquent model), khác với
 * Transport/TokenStore vốn thuần PHP. Ràng buộc kiểu cụ thể ở đây đáng giá
 * hơn là để `object` — nó giúp IDE và PHPStan hoạt động xuyên suốt chuỗi gọi.
 */
interface OaRepository
{
    public function find(string|int $key): ?ZaloOa;

    public function default(): ?ZaloOa;

    /** @return Collection<int, ZaloOa> */
    public function active(): Collection;

    /** @return Collection<int, ZaloOa> */
    public function all(): Collection;
}
