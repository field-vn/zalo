<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use Illuminate\Support\Collection;

interface BotRepository
{
    public function find(string|int $key): ?object;

    public function default(): ?object;

    /** @return Collection<int, object> */
    public function active(): Collection;

    /** @return Collection<int, object> */
    public function all(): Collection;
}
