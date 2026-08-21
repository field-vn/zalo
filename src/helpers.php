<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;

/*
 * Chỉ hai helper. Thêm nữa là làm ô nhiễm global namespace của dự án người khác
 * — thứ mà một package public không có quyền làm bừa.
 */

if (! function_exists('zalo_oa')) {
    function zalo_oa(string|int|null $key = null): OAChannel
    {
        return app(Factory::class)->oa($key);
    }
}

if (! function_exists('zalo_bot')) {
    function zalo_bot(string|int|null $key = null): BotChannel
    {
        return app(Factory::class)->bot($key);
    }
}
