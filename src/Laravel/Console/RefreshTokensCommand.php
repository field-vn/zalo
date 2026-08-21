<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Events\ZaloOaDisconnected;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Refresh token — chạy hàng giờ bởi scheduler, hoặc gọi tay.
 *
 * Việc quan trọng nhất KHÔNG phải refresh access_token sắp hết hạn, mà là
 * refresh CƯỠNG BỨC những OA có refresh_token sắp hết hạn — kể cả khi OA đó
 * đang rảnh. refresh_token sống ~3 tháng và xoay vòng mỗi lần dùng, nên app
 * im lặng quá lâu sẽ mất kết nối vĩnh viễn, không cách nào tự cứu.
 */
class RefreshTokensCommand extends Command
{
    protected $signature = 'zalo:token:refresh
                            {oa?     : Slug hoặc id của OA}
                            {--all   : Toàn bộ OA đang active}
                            {--force : Refresh kể cả khi token còn hạn dài}';

    protected $description = 'Refresh access token và xoay refresh token cho OA';

    public function handle(ZaloManager $zalo, OaRepository $oas): int
    {
        $targets = $this->targets($oas);

        if ($targets->isEmpty()) {
            $this->components->warn('Không có OA nào để refresh.');

            return self::SUCCESS;
        }

        $rotateBefore = (int) config('zalo.scheduler.rotate_before', 14);
        $maxFailures  = (int) config('zalo.scheduler.max_failures', 3);
        $refreshed    = 0;
        $skipped      = 0;
        $failed       = 0;

        foreach ($targets as $oa) {
            /** @var ZaloOa $oa */
            $result = $this->refreshOne($zalo, $oa, $rotateBefore, $maxFailures);

            match ($result) {
                'refreshed' => $refreshed++,
                'skipped'   => $skipped++,
                default     => $failed++,
            };
        }

        $this->newLine();
        $this->line("  <fg=green>{$refreshed} refresh</>  <fg=gray>{$skipped} bỏ qua</>"
            .($failed > 0 ? "  <fg=red>{$failed} lỗi</>" : ''));
        $this->newLine();

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return Collection<int, ZaloOa> */
    private function targets(OaRepository $oas): Collection
    {
        $slug = $this->argument('oa');

        if ($slug !== null) {
            $oa = $oas->find((string) $slug);

            if (! $oa instanceof ZaloOa) {
                $this->components->error("Không tìm thấy OA [{$slug}].");

                return collect();
            }

            return collect([$oa]);
        }

        if (! $this->option('all')) {
            $this->components->error('Chỉ định OA hoặc dùng --all.');

            return collect();
        }

        /** @var Collection<int, ZaloOa> */
        return $oas->active();
    }

    private function refreshOne(ZaloManager $zalo, ZaloOa $oa, int $rotateBefore, int $maxFailures): string
    {
        $token = $oa->token;

        if ($token === null) {
            $this->line("  <fg=red>✗</> {$oa->slug} — chưa có token, chạy: php artisan zalo:authorize {$oa->slug}");

            return 'failed';
        }

        if ($token->refreshExpired()) {
            $this->line("  <fg=red>✗</> {$oa->slug} — refresh token đã hết hạn, phải authorize lại");
            $this->disconnect($oa, 'refresh token đã hết hạn');

            return 'failed';
        }

        $needsRotation = ($token->daysUntilRotation() ?? 999) <= $rotateBefore;
        $expiringSoon  = $token->expires_at?->isBefore(
            now()->addMinutes((int) config('zalo.scheduler.refresh_before', 15))
        ) ?? true;

        if (! $this->option('force') && ! $needsRotation && ! $expiringSoon) {
            $this->line("  <fg=gray>·</> {$oa->slug} — còn hạn, bỏ qua");

            return 'skipped';
        }

        try {
            $zalo->tokenProviderFor($oa)->forceRefresh();

            $reason = $needsRotation ? ' <fg=gray>(xoay refresh token)</>' : '';
            $this->line("  <fg=green>✓</> {$oa->slug}{$reason}");

            return 'refreshed';
        } catch (ZaloException $e) {
            $this->line("  <fg=red>✗</> {$oa->slug} — {$e->getMessage()}");

            $oa->refresh();

            if (($oa->token?->failed_attempts ?? 0) >= $maxFailures) {
                $this->disconnect($oa, "refresh thất bại {$maxFailures} lần liên tiếp");
            }

            return 'failed';
        }
    }

    private function disconnect(ZaloOa $oa, string $reason): void
    {
        if (! $oa->is_active) {
            return;
        }

        $oa->forceFill(['is_active' => false])->save();

        // Bắn event để dự án tự gửi cảnh báo qua Slack/email — package không
        // đoán thay người dùng muốn được báo bằng cách nào.
        event(new ZaloOaDisconnected($oa, $reason));

        $this->line("      <fg=yellow>→ đã tắt OA `{$oa->slug}`: {$reason}</>");
    }
}
