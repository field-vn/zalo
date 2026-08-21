<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use FieldVn\Zalo\Laravel\Models\ZaloBot;
use Illuminate\Support\Collection;

/** @see OaRepository — cùng ghi chú về ràng buộc kiểu. */
interface BotRepository
{
    public function find(string|int $key): ?ZaloBot;

    public function default(): ?ZaloBot;

    /** @return Collection<int, ZaloBot> */
    public function active(): Collection;

    /** @return Collection<int, ZaloBot> */
    public function all(): Collection;
}
