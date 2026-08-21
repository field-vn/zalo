<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use Illuminate\Console\Command;

class BotListCommand extends Command
{
    protected $signature = 'zalo:bot:list {--active : Chỉ hiện bot đang hoạt động}';

    protected $description = 'Liệt kê Zalo Bot';

    public function handle(BotRepository $bots): int
    {
        $records = $this->option('active') ? $bots->active() : $bots->all();

        if ($records->isEmpty()) {
            $this->newLine();
            $this->components->warn('Chưa có bot nào.');
            $this->line('  <fg=gray>Thêm bằng: php artisan zalo:bot:add</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Slug', 'Tên', 'Username', 'Token', 'Trạng thái'],
            $records->map(fn (ZaloBot $bot): array => [
                $bot->slug,
                mb_strimwidth($bot->name, 0, 22, '…'),
                $bot->username !== null ? '@'.$bot->username : '—',
                // Token không bao giờ hiện đầy đủ, kể cả trong terminal —
                // output hay bị copy vào issue công khai.
                $bot->maskedToken(),
                $bot->is_active ? '<fg=green>hoạt động</>' : '<fg=gray>đã tắt</>',
            ])->all(),
        );
        $this->newLine();

        return self::SUCCESS;
    }
}
