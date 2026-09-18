<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

/**
 * Poll trạng thái duyệt template qua GET /template/info/v2.
 *
 * Webhook `change_template_status` là cách Zalo khuyến nghị. Lệnh này dành
 * cho script/CLI không nhận webhook.
 */
class ZbsWaitCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:zbs:wait
        {template : template_id đang chờ duyệt}
        {--oa= : Slug của OA. Bỏ trống thì dùng OA mặc định}
        {--until=ENABLE : Status dừng (cách nhau bởi dấu phẩy, mặc định ENABLE)}
        {--timeout=300 : Số giây chờ tối đa}
        {--interval=5 : Giây giữa mỗi lần hỏi}';

    protected $description = 'Chờ template ZBS được duyệt (hoặc bị từ chối)';

    public function handle(Factory $zalo): int
    {
        $until = $this->listOption('until');

        if ($until === []) {
            $until = ['ENABLE'];
        }

        $this->newLine();
        $this->line('  <fg=gray>Webhook change_template_status chính xác hơn poll. Dùng lệnh này khi không nhận webhook.</>');
        $this->newLine();

        try {
            $oa = $zalo->oa($this->stringOption('oa') ?: null);
            $response = $oa->zbs()->waitUntilStatus(
                $this->stringArgument('template'),
                statuses: $until,
                timeoutSeconds: $this->intOption('timeout', 300),
                intervalSeconds: $this->intOption('interval', 5),
            );
        } catch (ApiException $e) {
            $this->components->error($e->explain());

            return self::FAILURE;
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->payload();
        $status = strtoupper((string) ($data['status'] ?? ''));

        $this->components->twoColumnDetail('<fg=gray>template_id</>', $this->stringArgument('template'));
        $this->components->twoColumnDetail('<fg=gray>Trạng thái</>', $status !== '' ? $status : '—');

        if (isset($data['reason']) && $data['reason'] !== '') {
            $this->components->twoColumnDetail('<fg=gray>Lý do</>', (string) $data['reason']);
        }

        $this->newLine();

        return $status === 'REJECT' ? self::FAILURE : self::SUCCESS;
    }
}
