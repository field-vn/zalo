<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

/**
 * Tra trạng thái giao tin của một tin ZBS đã gửi.
 *
 * `zalo:zbs:send` trả về `msg_id` khi Zalo NHẬN tin — không phải khi người
 * dùng NHẬN ĐƯỢC tin. Hai chuyện khác nhau, và khi tin không tới thì đây là
 * chỗ duy nhất nói được nó tắc ở đâu.
 */
class ZbsStatusCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:zbs:status
        {message : msg_id trả về lúc gửi}
        {--oa= : Slug của OA. Bỏ trống thì dùng OA mặc định}';

    protected $description = 'Tra trạng thái giao tin của một tin ZBS đã gửi';

    public function handle(Factory $zalo): int
    {
        try {
            $oa = $zalo->oa($this->stringOption('oa') ?: null);
            $response = $oa->zbs()->status($this->stringArgument('message'));
        } catch (ApiException $e) {
            $this->components->error("Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");

            return self::FAILURE;
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->payload();

        // Zalo trả `status` là số, `message` là mô tả tiếng Anh. Số mới là thứ
        // đáng tin — mô tả có thể đổi câu chữ giữa các phiên bản.
        $status = isset($data['status']) ? (int) $data['status'] : null;

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>msg_id</>', $this->stringArgument('message'));
        $this->components->twoColumnDetail('<fg=gray>Trạng thái</>', match ($status) {
            -1 => '<fg=red>-1 — tin không tồn tại</>',
            0 => '<fg=yellow>0 — Zalo đã nhận, CHƯA giao tới thiết bị</>',
            1 => '<fg=green>1 — đã giao tới thiết bị người dùng</>',
            default => (string) ($status ?? '—'),
        });

        if (isset($data['delivery_time']) && $data['delivery_time'] !== '') {
            $this->components->twoColumnDetail(
                '<fg=gray>Giao lúc</>',
                $this->readableTime((string) $data['delivery_time']),
            );
        }

        if (isset($data['message'])) {
            $this->components->twoColumnDetail('<fg=gray>Zalo mô tả</>', (string) $data['message']);
        }

        $this->newLine();
        $this->line('  <fg=gray>'.match ($status) {
            -1 => 'msg_id sai, hoặc thuộc OA khác với OA đang dùng.',
            0 => 'Thường gặp khi số nhận chưa có tài khoản Zalo, tài khoản đã tắt '
                .'nhận tin từ OA, hoặc máy chưa mở Zalo. Chờ vài phút rồi tra lại.',
            1 => 'Tin đã tới máy. Không thấy thì tìm trong mục Thông báo của Zalo.',
            default => 'Xem bảng mã: developers.zalo.me/docs/zalo-notification-service/phu-luc/bang-ma-loi',
        }.'</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /** Zalo trả timestamp mili-giây dạng chuỗi. */
    private function readableTime(string $raw): string
    {
        if (! ctype_digit($raw)) {
            return $raw;
        }

        return date('H:i:s d/m/Y', (int) (((int) $raw) / 1000));
    }
}
