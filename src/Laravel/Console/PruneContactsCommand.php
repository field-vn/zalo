<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use Illuminate\Console\Command;

/**
 * Xoá cứng contact đã unfollow và lâu không tương tác.
 *
 * Giữ row unfollow một thời gian để notifier còn biết lịch sử; sau
 * `contact_prune_days` ngày thì dọn để bảng không phình vô hạn.
 */
class PruneContactsCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:contacts:prune
        {--days= : Số ngày kể từ last_interaction_at (mặc định config zalo.notifier.contact_prune_days)}';

    protected $description = 'Xoá contact đã bỏ quan tâm và lâu không tương tác';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption === null || $daysOption === ''
            ? (int) config('zalo.notifier.contact_prune_days', 180)
            : (int) $daysOption;

        if ($days < 1) {
            $this->components->error('`--days` phải >= 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $deleted = ZaloContact::query()
            ->where('is_following', false)
            ->where('last_interaction_at', '<', $cutoff)
            ->delete();

        $this->components->info("Đã xoá {$deleted} contact (unfollow, tương tác trước {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
