<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Support\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'zalo:install
                            {--force            : Ghi đè file đã tồn tại}
                            {--skip-env-check   : Bỏ qua kiểm tra env (dùng cho CI/docker build)}
                            {--no-migrate       : Không chạy migrate}';

    protected $description = 'Cài đặt package Zalo — kiểm tra env, publish config, migrate';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Zalo Package</> · cài đặt');
        $this->newLine();

        if (! $this->option('skip-env-check') && ! $this->checkEnv()) {
            return self::FAILURE;
        }

        $this->publishConfig();

        if (! $this->option('no-migrate')) {
            $this->migrate();
        }

        $this->checkUiSecurity();
        $this->checkScheduler();
        $this->finish();

        return self::SUCCESS;
    }

    protected function checkEnv(): bool
    {
        $this->step(1, 'Kiểm tra Zalo App trong env');

        $appId = (string) config('zalo.apps.default.app_id');
        $secret = (string) config('zalo.apps.default.app_secret');
        $ok = true;

        if ($appId !== '') {
            $this->ok('ZALO_APP_ID        '.$this->maskId($appId));
        } else {
            $this->bad('ZALO_APP_ID        chưa cấu hình');
            $ok = false;
        }

        if ($secret !== '') {
            $this->ok('ZALO_APP_SECRET    '.$this->mask($secret));
        } else {
            $this->bad('ZALO_APP_SECRET    chưa cấu hình');
            $ok = false;
        }

        $this->ok('ZALO_APP_REDIRECT  '.config('zalo.apps.default.redirect'));

        if (! $ok) {
            $this->newLine();
            $this->line('  <fg=red>Thiếu credential. Dừng lại, chưa migrate gì cả.</>');
            $this->newLine();
            $this->line('  1. Tạo ứng dụng tại <options=underscore>https://developers.zalo.me/apps</>');
            $this->line('  2. Thêm vào .env:');
            $this->newLine();
            $this->line('       <fg=gray>ZALO_APP_ID=your_app_id</>');
            $this->line('       <fg=gray>ZALO_APP_SECRET=your_app_secret</>');
            $this->newLine();
            $this->line('  3. Chạy lại: <fg=cyan>php artisan zalo:install</>');
            $this->newLine();
            $this->line('  <fg=gray>Đang build CI/docker? php artisan zalo:install --skip-env-check</>');
            $this->newLine();

            return false;
        }

        // Trung thực: Zalo không có endpoint xác thực cặp app_id/secret khi chưa
        // gắn OA nào. Xác thực thật xảy ra ở lần authorize OA đầu tiên.
        $this->note('Chỉ kiểm tra được sự tồn tại, chưa xác thực với Zalo.');
        $this->note('Credential sẽ được xác thực ở lần authorize OA đầu tiên.');

        return true;
    }

    protected function publishConfig(): void
    {
        $this->step(2, 'Publish config');

        if (file_exists(config_path('zalo.php')) && ! $this->option('force')) {
            $this->skip('config/zalo.php đã tồn tại (--force để ghi đè)');

            return;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'zalo-config',
            '--force' => true,
        ]);

        $this->ok('config/zalo.php');
    }

    protected function migrate(): void
    {
        $this->step(3, 'Migrate');
        $this->line('  <fg=gray>Prefix bảng: '.Table::prefix().'</>');

        $this->callSilently('migrate', ['--force' => true]);

        $existing = array_filter(Table::all(), static fn (string $t): bool => Schema::hasTable($t));

        if (count($existing) === count(Table::all())) {
            $this->ok(implode('  ', $existing).'   ['.count($existing).' bảng]');
        } else {
            $missing = array_diff(Table::all(), $existing);
            $this->bad('Thiếu bảng: '.implode(', ', $missing));
        }
    }

    protected function checkUiSecurity(): void
    {
        $this->step(4, 'Bảo mật UI');

        $user = (string) config('zalo.ui.user');
        $password = (string) config('zalo.ui.password');
        $ips = (array) config('zalo.ui.allowed_ips');

        $user !== ''
            ? $this->ok('ZALO_UI_USER          '.$user)
            : $this->warn2('ZALO_UI_USER          chưa đặt');

        $password !== ''
            ? $this->ok('ZALO_UI_PASSWORD      '.$this->mask($password))
            : $this->warn2('ZALO_UI_PASSWORD      chưa đặt → UI chỉ chạy được ở local');

        $ips !== []
            ? $this->ok('ZALO_UI_ALLOWED_IPS   '.implode(', ', $ips))
            : $this->warn2('ZALO_UI_ALLOWED_IPS   chưa đặt → mở với mọi IP');

        if ($password === '') {
            $this->newLine();
            $this->line('  <fg=gray>Trước khi lên production, thêm vào .env:</>');
            $this->line('       <fg=gray>ZALO_UI_USER=admin</>');
            $this->line('       <fg=gray>ZALO_UI_PASSWORD=&lt;mật khẩu mạnh&gt;</>');
        }

        $this->newLine();
        $this->line('  <fg=yellow>⚠  Basic Auth gửi credential ở MỌI request — site BẮT BUỘC chạy HTTPS.</>');
    }

    protected function checkScheduler(): void
    {
        $this->step(5, 'Scheduler');

        if (! config('zalo.scheduler.enabled', true)) {
            $this->warn2('ZALO_SCHEDULER=false → token sẽ KHÔNG tự refresh');

            return;
        }

        $this->ok('zalo:token:refresh đã đăng ký (hourly)');
        $this->newLine();
        $this->line('  <fg=yellow>⚠  Cần cron gọi schedule:run trên server, nếu không token sẽ chết:</>');
        $this->newLine();
        $this->line('       <fg=gray>* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1</>');
        $this->newLine();
        $this->line('  <fg=gray>Kiểm tra: php artisan schedule:list</>');
    }

    protected function finish(): void
    {
        $path = trim((string) config('zalo.ui.path'), '/');

        $this->newLine();
        $this->line('  <fg=green;options=bold>Cài đặt hoàn tất.</>');
        $this->newLine();
        $this->line('  →  Mở UI       <options=underscore>'.url($path).'</>');
        $this->line('  →  Kiểm tra    <fg=cyan>php artisan zalo:doctor</>');
        $this->line('  →  Thêm OA     <fg=cyan>php artisan zalo:authorize &lt;slug&gt;</>');
        $this->newLine();
    }

    // ── helper hiển thị ──────────────────────────────────────────────────

    protected function step(int $n, string $title): void
    {
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>[{$n}/5]</> <options=bold>{$title}</>");
    }

    protected function ok(string $msg): void
    {
        $this->line('  <fg=green>✓</> '.$msg);
    }

    protected function bad(string $msg): void
    {
        $this->line('  <fg=red>✗</> '.$msg);
    }

    protected function warn2(string $msg): void
    {
        $this->line('  <fg=yellow>⚠</> '.$msg);
    }

    protected function skip(string $msg): void
    {
        $this->line('  <fg=gray>·</> '.$msg);
    }

    protected function note(string $msg): void
    {
        $this->line('    <fg=gray>'.$msg.'</>');
    }

    protected function mask(string $value): string
    {
        return str_repeat('•', 8).substr($value, -4);
    }

    protected function maskId(string $value): string
    {
        return strlen($value) > 8
            ? substr($value, 0, 4).'…'.substr($value, -4)
            : $value;
    }
}
