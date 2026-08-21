<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Console\Command;

class OaListCommand extends Command
{
    protected $signature = 'zalo:oa:list {--active : Chỉ hiện OA đang hoạt động}';

    protected $description = 'Liệt kê Official Account và trạng thái token';

    public function handle(OaRepository $oas): int
    {
        $records = $this->option('active') ? $oas->active() : $oas->all();

        if ($records->isEmpty()) {
            $this->newLine();
            $this->components->warn('Chưa có OA nào.');
            $this->line('  <fg=gray>Thêm bằng: php artisan zalo:oa:add</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Slug', 'Tên', 'OA ID', 'App', 'Tag', 'Trạng thái'],
            $records->map(fn (ZaloOa $oa): array => [
                $oa->slug,
                mb_strimwidth($oa->name, 0, 22, '…'),
                $oa->oa_id,
                $oa->app_key,
                implode(', ', $oa->tags ?? []) ?: '—',
                $this->status($oa),
            ])->all(),
        );
        $this->newLine();

        return self::SUCCESS;
    }

    private function status(ZaloOa $oa): string
    {
        if ($oa->token === null) {
            return '<fg=red>chưa cấp quyền</>';
        }

        if ($oa->token->refreshExpired()) {
            return '<fg=red>mất kết nối</>';
        }

        if (! $oa->is_active) {
            return '<fg=gray>đã tắt</>';
        }

        $days = $oa->token->daysUntilRotation();

        return ($days !== null && $days <= (int) config('zalo.scheduler.rotate_before', 14))
            ? "<fg=yellow>xoay sau {$days} ngày</>"
            : '<fg=green>hoạt động</>';
    }
}
