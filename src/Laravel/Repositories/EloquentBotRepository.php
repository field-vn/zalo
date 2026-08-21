<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Repositories;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use Illuminate\Support\Collection;

final class EloquentBotRepository implements BotRepository
{
    public function find(string|int $key): ?ZaloBot
    {
        return ZaloBot::query()
            ->when(
                is_numeric($key),
                fn ($q) => $q->whereKey((int) $key),
                fn ($q) => $q->where('slug', (string) $key),
            )
            ->first();
    }

    public function default(): ?ZaloBot
    {
        return ZaloBot::query()->active()->orderBy('id')->first();
    }

    /** @return Collection<int, ZaloBot> */
    public function active(): Collection
    {
        return ZaloBot::query()->active()->orderBy('name')->get();
    }

    /** @return Collection<int, ZaloBot> */
    public function all(): Collection
    {
        return ZaloBot::query()->orderBy('name')->get();
    }
}
