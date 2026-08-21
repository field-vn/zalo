<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Support\Table;
use Illuminate\Console\Command;

/**
 * `php artisan zalo` — bảng điều khiển trong terminal.
 *
 * Cố tình KHÔNG phải trang help: `php artisan list zalo` đã liệt kê command
 * miễn phí rồi. Thứ người ta cần khi gõ mò là TRẠNG THÁI THẬT.
 */
class StatusCommand extends Command
{
    protected $signature = 'zalo';

    protected $description = 'Trạng thái Zalo: OA, Bot, sức khoẻ token';

    public function handle(OaRepository $oas, BotRepository $bots): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Zalo Package</>');
        $this->newLine();

        $this->overview();
        $this->oaTable($oas);
        $this->botTable($bots);
        $this->commonCommands();

        return self::SUCCESS;
    }

    private function overview(): void
    {
        $appId = (string) config('zalo.apps.default.app_id');

        $this->row('App', $appId !== ''
            ? '<fg=green>✓</> env · '.substr($appId, 0, 4).'…'.substr($appId, -4)
            : '<fg=red>✗</> chưa cấu hình');

        $this->row('Prefix bảng', Table::prefix());

        $guard = ZaloManager::hasAuthGate()
            ? 'Zalo::auth()'
            : ((string) config('zalo.ui.password') !== ''
                ? 'basic auth'
                : '<fg=yellow>chưa cấu hình ⚠</>');

        $this->row('UI', config('zalo.ui.enabled')
            ? url((string) config('zalo.ui.path')).'   <fg=gray>('.$guard.')</>'
            : '<fg=gray>đang tắt</>');

        $this->row('Scheduler', config('zalo.scheduler.enabled', true)
            ? '<fg=green>✓</> hourly'
            : '<fg=yellow>tắt</>');

        $this->newLine();
    }

    private function oaTable(OaRepository $oas): void
    {
        $all = $oas->all();

        $this->section('OFFICIAL ACCOUNTS', $all->where('is_active', true)->count().' active');

        if ($all->isEmpty()) {
            $this->line('  <fg=gray>Chưa có OA nào. Thêm bằng: php artisan zalo:oa:add</>');
            $this->newLine();

            return;
        }

        foreach ($all as $oa) {
            /** @var ZaloOa $oa */
            $this->line(sprintf(
                '  %s %-14s %-18s %s',
                $this->oaIcon($oa),
                $oa->slug,
                mb_strimwidth($oa->name, 0, 18, '…'),
                $this->oaDetail($oa),
            ));
        }

        $this->newLine();
    }

    private function oaIcon(ZaloOa $oa): string
    {
        if (! $oa->is_active) {
            return '<fg=gray>·</>';
        }

        if ($oa->token === null || $oa->token->refreshExpired()) {
            return '<fg=red>✗</>';
        }

        $rotate = (int) config('zalo.scheduler.rotate_before', 14);

        return ($oa->token->daysUntilRotation() ?? 999) <= $rotate
            ? '<fg=yellow>⚠</>'
            : '<fg=green>✓</>';
    }

    private function oaDetail(ZaloOa $oa): string
    {
        if (! $oa->is_active) {
            return '<fg=gray>đã tắt</>';
        }

        if ($oa->token === null) {
            return '<fg=red>CHƯA CẤP QUYỀN</>';
        }

        if ($oa->token->refreshExpired()) {
            return '<fg=red>MẤT KẾT NỐI · phải authorize lại</>';
        }

        // Tự format thay vì dùng diffForHumans(): chữ ký của nó khác nhau giữa
        // Carbon 2 (Laravel 10) và Carbon 3 (Laravel 11+), package phải chạy
        // được trên cả hai.
        $expiresAt = $oa->token->expires_at;

        if ($expiresAt === null) {
            $expires = '?';
        } else {
            $minutes = (int) now()->diffInMinutes($expiresAt, false);
            $expires = match (true) {
                $minutes <= 0 => 'đã hết hạn',
                $minutes < 60 => $minutes.' phút',
                default => intdiv($minutes, 60).' giờ',
            };
        }

        $rotate = $oa->token->daysUntilRotation();

        return sprintf(
            'token còn %-12s xoay sau %s ngày',
            $expires,
            $rotate !== null ? (string) $rotate : '?',
        );
    }

    private function botTable(BotRepository $bots): void
    {
        $all = $bots->all();

        $this->section('BOTS', $all->where('is_active', true)->count().' active');

        if ($all->isEmpty()) {
            $this->line('  <fg=gray>Chưa có Bot nào.</>');
            $this->newLine();

            return;
        }

        foreach ($all as $bot) {
            /** @var ZaloBot $bot */
            $this->line(sprintf(
                '  %s %-14s %s',
                $bot->is_active ? '<fg=green>✓</>' : '<fg=gray>·</>',
                $bot->slug,
                $bot->username ? '@'.$bot->username : '<fg=gray>—</>',
            ));
        }

        $this->newLine();
    }

    private function commonCommands(): void
    {
        $this->line('  <options=bold>THƯỜNG DÙNG</>');
        $this->line('    <fg=cyan>zalo:token:refresh --all</>   refresh toàn bộ token ngay');
        $this->line('    <fg=cyan>zalo:doctor</>                chẩn đoán cấu hình');
        $this->line('    <fg=gray>php artisan list zalo</>      xem toàn bộ command');
        $this->newLine();
    }

    private function row(string $label, string $value): void
    {
        $this->line(sprintf('  <fg=gray>%-12s</> %s', $label, $value));
    }

    private function section(string $title, string $meta): void
    {
        $this->line(sprintf('  <options=bold>%-44s</> <fg=gray>%s</>', $title, $meta));
        $this->line('  <fg=gray>'.str_repeat('─', 61).'</>');
    }
}
