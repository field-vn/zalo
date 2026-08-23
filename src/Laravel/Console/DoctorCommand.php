<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\OaPresenter;
use FieldVn\Zalo\Support\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Chẩn đoán cấu hình.
 *
 * Dự đoán đây là command giảm issue GitHub nhiều nhất: mọi câu hỏi "sao không
 * chạy" sẽ được trả lời bằng "chạy zalo:doctor rồi dán kết quả vào đây".
 */
class DoctorCommand extends Command
{
    protected $signature = 'zalo:doctor';

    protected $description = 'Chẩn đoán cấu hình Zalo và chỉ ra cách sửa';

    private int $problems = 0;

    private int $warnings = 0;

    public function handle(OaRepository $oas, BotRepository $bots): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Zalo Doctor</>');
        $this->newLine();

        $this->checkApp();
        $this->checkRedirectUri();
        $this->checkTables();
        $this->checkEncryption($oas);
        $this->checkUi();
        $this->checkScheduler();
        $this->checkOas($oas);
        $this->checkBots($bots);

        $this->newLine();

        if ($this->problems > 0) {
            $this->line("  <fg=red;options=bold>{$this->problems} lỗi</> cần sửa"
                .($this->warnings > 0 ? ", <fg=yellow>{$this->warnings} cảnh báo</>" : ''));
            $this->newLine();

            return self::FAILURE;
        }

        $this->warnings > 0
            ? $this->line("  <fg=yellow;options=bold>{$this->warnings} cảnh báo</>, không có lỗi nghiêm trọng.")
            : $this->line('  <fg=green;options=bold>Mọi thứ ổn.</>');

        $this->newLine();

        return self::SUCCESS;
    }

    private function checkApp(): void
    {
        $appId = (string) config('zalo.apps.default.app_id');
        $secret = (string) config('zalo.apps.default.app_secret');

        $appId !== '' && $secret !== ''
            ? $this->ok('ZALO_APP_ID / ZALO_APP_SECRET có trong env')
            : $this->bad(
                'Thiếu ZALO_APP_ID hoặc ZALO_APP_SECRET',
                'Tạo app tại https://developers.zalo.me/apps rồi thêm vào .env',
            );
    }

    /** Lỗi -14003 của Zalo không nói lệch ở đâu, nên phải tự in ra để đối chiếu. */
    private function checkRedirectUri(): void
    {
        $uri = OaPresenter::redirectUri();

        $this->info2("Redirect URI đang dùng: {$uri}");
        $this->info2('Giá trị này phải khớp CHÍNH XÁC với Callback URL khai trong Zalo Developers.');

        if (! str_starts_with($uri, 'https://')) {
            $this->warn2(
                'Redirect URI không phải HTTPS',
                'Zalo thường từ chối (-14003). Dùng domain thật có HTTPS, hoặc tunnel như ngrok/cloudflared.',
            );
        }
    }

    private function checkTables(): void
    {
        $all = Table::all();
        $missing = array_values(array_filter($all, static fn (string $t): bool => ! Schema::hasTable($t)));

        if ($missing === []) {
            $this->ok('Bảng theo prefix `'.Table::prefix().'` đầy đủ ('.count($all).'/'.count($all).')');

            return;
        }

        // Nguyên nhân phổ biến nhất: đổi ZALO_TABLE_PREFIX sau khi đã migrate.
        $this->bad(
            'Thiếu bảng: '.implode(', ', $missing),
            'Chạy `php artisan migrate`. Nếu bạn vừa đổi ZALO_TABLE_PREFIX sau khi '
                .'đã migrate thì bảng cũ vẫn mang tên cũ — đổi lại prefix hoặc rename bảng thủ công.',
        );
    }

    private function checkEncryption(OaRepository $oas): void
    {
        $oa = $oas->all()->first();

        if (! $oa instanceof ZaloOa || $oa->token === null) {
            return;
        }

        try {
            $token = $oa->token->access_token;

            $token !== ''
                ? $this->ok('APP_KEY khớp — giải mã token thành công')
                : $this->warn2('Token rỗng', 'Chạy: php artisan zalo:authorize '.$oa->slug);
        } catch (Throwable) {
            $this->bad(
                'Không giải mã được token — APP_KEY đã bị đổi',
                'Chạy `php artisan zalo:reencrypt`, hoặc authorize lại toàn bộ OA.',
            );
        }
    }

    private function checkUi(): void
    {
        if (! config('zalo.ui.enabled')) {
            $this->info2('UI đang tắt (ZALO_UI_ENABLED=false)');

            return;
        }

        if (ZaloManager::hasAuthGate()) {
            $this->ok('UI bảo vệ bằng Zalo::auth() của ứng dụng');
        } elseif ((string) config('zalo.ui.password') !== '') {
            $this->ok('UI bảo vệ bằng basic auth');
        } else {
            app()->environment('local')
                ? $this->warn2(
                    'UI chưa có credential — chỉ chạy được ở local',
                    'Trước khi lên production: đặt ZALO_UI_USER và ZALO_UI_PASSWORD trong .env',
                )
                : $this->bad(
                    'UI chưa có credential trên môi trường '.app()->environment().' — đang bị chặn hoàn toàn',
                    'Đặt ZALO_UI_USER và ZALO_UI_PASSWORD, hoặc định nghĩa Zalo::auth().',
                );
        }

        if ((array) config('zalo.ui.allowed_ips') === []) {
            $this->warn2(
                'ZALO_UI_ALLOWED_IPS chưa cấu hình — UI mở với mọi IP',
                'Lớp phòng thủ rẻ nhất. Ví dụ: ZALO_UI_ALLOWED_IPS=113.161.0.0/16',
            );
        }
    }

    private function checkScheduler(): void
    {
        config('zalo.scheduler.enabled', true)
            ? $this->ok('Scheduler bật — zalo:token:refresh chạy hàng giờ')
            : $this->warn2(
                'Scheduler tắt — token sẽ KHÔNG tự refresh',
                'Bật ZALO_SCHEDULER=true, hoặc tự gọi zalo:token:refresh --all định kỳ.',
            );

        $this->info2('Nhớ kiểm tra cron: crontab phải có `* * * * * php artisan schedule:run`');
    }

    private function checkOas(OaRepository $oas): void
    {
        $all = $oas->all();

        if ($all->isEmpty()) {
            $this->warn2('Chưa có OA nào', 'Thêm OA trong UI hoặc chạy: php artisan zalo:oa:add');

            return;
        }

        $rotate = (int) config('zalo.scheduler.rotate_before', 14);

        foreach ($all as $oa) {
            /** @var ZaloOa $oa */
            if (! $oa->is_active) {
                $this->warn2("OA `{$oa->slug}` đang tắt");

                continue;
            }

            if ($oa->token === null) {
                $this->bad(
                    "OA `{$oa->slug}` chưa có token",
                    "php artisan zalo:authorize {$oa->slug}",
                );

                continue;
            }

            if ($oa->token->refreshExpired()) {
                $this->bad(
                    "OA `{$oa->slug}` mất kết nối — refresh token đã hết hạn",
                    "Không thể tự khôi phục. Chạy: php artisan zalo:authorize {$oa->slug}",
                );

                continue;
            }

            $days = $oa->token->daysUntilRotation();

            if ($days !== null && $days <= $rotate) {
                $this->warn2(
                    "OA `{$oa->slug}` còn {$days} ngày trước khi phải xoay refresh token",
                    'Scheduler sẽ tự xử lý nếu cron đang chạy.',
                );
            } else {
                $this->ok("OA `{$oa->slug}` kết nối bình thường");
            }
        }
    }

    private function checkBots(BotRepository $bots): void
    {
        $all = $bots->all();

        if ($all->isEmpty()) {
            // Không có bot không phải lỗi — nhiều dự án chỉ dùng OA.
            return;
        }

        foreach ($all as $bot) {
            $bot->is_active
                ? $this->ok("Bot `{$bot->slug}` đang bật")
                : $this->warn2("Bot `{$bot->slug}` đã tắt");
        }

        $this->checkBotWebhookSecret();

        $this->info2('Gửi tin thật: php artisan zalo:bot:send <slug> <chat_id> "test"');
    }

    /**
     * Chỉ kiểm khi thực sự có bot — dự án chỉ dùng OA không cần biến này.
     *
     * Secret của Bot KHÁC secret của OA: OA Secret Key do Zalo cấp, còn cái
     * này do mình tự đặt và Zalo gửi trả nguyên văn ở header. Nhầm hai cái là
     * lỗi hay gặp nhất, nên nói rõ ngay tại đây.
     */
    private function checkBotWebhookSecret(): void
    {
        $secret = (string) config('zalo.bot.webhook_secret', '');

        if ($secret === '') {
            $this->bad(
                'Thiếu ZALO_BOT_WEBHOOK_SECRET — webhook bot bị từ chối toàn bộ (fail-closed)',
                'Thêm vào .env: ZALO_BOT_WEBHOOK_SECRET='.BotSecretVerifier::generate(),
            );

            return;
        }

        if (! BotSecretVerifier::isValidLength($secret)) {
            $this->bad(
                'ZALO_BOT_WEBHOOK_SECRET dài '.strlen($secret).' ký tự — Zalo yêu cầu '
                    .BotSecretVerifier::MIN_LENGTH.'-'.BotSecretVerifier::MAX_LENGTH,
                'Thay bằng: '.BotSecretVerifier::generate(),
            );

            return;
        }

        $this->ok('ZALO_BOT_WEBHOOK_SECRET hợp lệ (khác OA Secret Key — đúng như thiết kế)');
    }

    // ── helper hiển thị ──────────────────────────────────────────────────

    private function ok(string $msg): void
    {
        $this->line('  <fg=green>✓</> '.$msg);
    }

    private function bad(string $msg, string $fix = ''): void
    {
        $this->problems++;
        $this->line('  <fg=red>✗</> '.$msg);

        if ($fix !== '') {
            $this->line('      <fg=gray>→ '.$fix.'</>');
        }
    }

    private function warn2(string $msg, string $fix = ''): void
    {
        $this->warnings++;
        $this->line('  <fg=yellow>⚠</> '.$msg);

        if ($fix !== '') {
            $this->line('      <fg=gray>→ '.$fix.'</>');
        }
    }

    private function info2(string $msg): void
    {
        $this->line('  <fg=gray>·</> '.$msg);
    }
}
