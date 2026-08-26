<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use FieldVn\Zalo\Support\PhoneNumber;
use Illuminate\Console\Command;
use JsonException;

/**
 * Gửi một tin ZBS theo template tới số điện thoại.
 *
 * Mặc định chạy ở chế độ `development`: miễn phí, nhưng CHỈ tới được số của
 * quản trị viên OA hoặc App. Gửi cho khách thật cần `--production`, và mỗi
 * tin đều tính phí vào số dư ZBS.
 */
class ZbsSendCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:zbs:send
        {phone : Số điện thoại người nhận (0987…, +8498…, 8498… đều được)}
        {template : template_id đã duyệt}
        {data : template_data dạng JSON, ví dụ \'{"otp":"123456"}\'}
        {--oa= : Slug của OA. Bỏ trống thì dùng OA mặc định}
        {--tracking= : tracking_id tuỳ chỉnh để đối soát}
        {--production : GỬI THẬT VÀ TÍNH PHÍ. Không có cờ này thì chạy dev mode}';

    protected $description = 'Gửi tin ZBS theo template tới số điện thoại';

    public function handle(Factory $zalo): int
    {
        try {
            $oa = $zalo->oa($this->stringOption('oa') ?: null);
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $phone = PhoneNumber::normalize($this->stringArgument('phone'));
            $data = $this->parseData();
        } catch (ZaloException|JsonException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $production = (bool) $this->option('production');
        $mode = $production ? ZbsResource::MODE_PRODUCTION : ZbsResource::MODE_DEVELOPMENT;

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Người nhận</>', $phone);
        $this->components->twoColumnDetail('<fg=gray>Template</>', $this->stringArgument('template'));
        $this->components->twoColumnDetail(
            '<fg=gray>Chế độ</>',
            $production
                ? '<fg=red;options=bold>production — TÍNH PHÍ</>'
                : '<fg=green>development — miễn phí, chỉ tới admin OA/App</>',
        );
        $this->newLine();

        // Hỏi lại trước khi tiêu tiền. Dev mode không hỏi vì không mất gì.
        if ($production && ! $this->option('no-interaction')
            && ! $this->confirm('Gửi thật và tính phí vào số dư ZBS?', false)) {
            $this->components->warn('Đã huỷ.');

            return self::SUCCESS;
        }

        try {
            $response = $oa->zbs()->send(
                phone: $phone,
                templateId: $this->stringArgument('template'),
                data: $data,
                trackingId: $this->stringOption('tracking') ?: null,
                mode: $mode,
            );
        } catch (ApiException $e) {
            $this->components->error("Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");

            if (! $production) {
                $this->line('  <fg=gray>Ở dev mode, số nhận PHẢI là quản trị viên của OA hoặc App.</>');
            }

            if ($e->response !== null) {
                $this->line('  <fg=gray>Body: '.$e->response->raw.'</>');
            }

            return self::FAILURE;
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $result */
        $result = (array) $response->payload();

        $this->components->info('Zalo đã nhận.');
        $this->components->twoColumnDetail('<fg=gray>message_id</>', (string) ($result['msg_id'] ?? $result['message_id'] ?? '—'));

        if (isset($result['sent_time'])) {
            $this->components->twoColumnDetail('<fg=gray>sent_time</>', (string) $result['sent_time']);
        }

        $this->newLine();
        $this->line('  <fg=gray>Mở Zalo trên máy người nhận để xác nhận tin đã tới.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<string, string|int>
     *
     * @throws JsonException
     */
    private function parseData(): array
    {
        $raw = $this->stringArgument('data');

        /** @var array<string, string|int> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || $decoded === []) {
            throw new JsonException('template_data phải là một object JSON không rỗng.');
        }

        return $decoded;
    }
}
