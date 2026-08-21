<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;

/**
 * API công khai của package — typehint interface này, không phải class cụ thể.
 */
interface Factory
{
    /** @param  string|int|null  $key  slug hoặc id; null = OA mặc định */
    public function oa(string|int|null $key = null): OAChannel;

    /** @param  string|int|null  $key  slug hoặc id; null = Bot mặc định */
    public function bot(string|int|null $key = null): BotChannel;

    /**
     * Các OA đang hoạt động, đã dựng sẵn thành channel.
     *
     * @param  (callable(ZaloOa): bool)|null  $filter
     * @return Collection<int, OAChannel>
     */
    public function oas(?callable $filter = null): Collection;

    /**
     * Bản ghi OA thô — dùng cho dropdown, báo cáo, không phải để gọi API.
     *
     * @return Collection<int, ZaloOa>
     */
    public function availableOas(): Collection;

    /** @return Collection<int, ZaloBot> */
    public function availableBots(): Collection;
}
