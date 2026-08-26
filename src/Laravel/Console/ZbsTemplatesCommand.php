<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

/**
 * Liệt kê template ZBS đã được duyệt, hoặc xem tham số của một template.
 *
 * Phải chạy lệnh này trước khi gửi: sai tên tham số thì Zalo từ chối, và tin
 * bị từ chối vẫn có thể bị tính phí.
 */
class ZbsTemplatesCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:zbs:templates
        {oa? : Slug của OA. Bỏ trống thì dùng OA mặc định}
        {--id= : Xem chi tiết một template, gồm tham số bắt buộc}
        {--all : Kể cả template chưa duyệt hoặc đã tắt}';

    protected $description = 'Liệt kê template ZBS đã duyệt và tham số của chúng';

    public function handle(Factory $zalo): int
    {
        try {
            $oa = $zalo->oa($this->stringArgument('oa') ?: null);
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            return $this->stringOption('id') !== ''
                ? $this->showOne($oa->zbs(), $this->stringOption('id'))
                : $this->showAll($oa->zbs());
        } catch (ApiException $e) {
            $this->components->error("Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");

            if ($e->errorCode === -124 || $e->isTokenError()) {
                $this->line('  <fg=gray>Token OA hết hạn hoặc thiếu quyền ZBS.</>');
            }

            return self::FAILURE;
        }
    }

    /** @param \FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource $zbs */
    private function showAll($zbs): int
    {
        $response = $zbs->templates(status: $this->option('all') ? '' : 'ENABLE');

        /** @var list<array<string, mixed>> $items */
        $items = (array) $response->payload();

        if ($items === []) {
            $this->newLine();
            $this->components->warn('Chưa có template nào được duyệt.');
            $this->line('  <fg=gray>Tạo mẫu tin trong tài khoản ZBS rồi chờ Zalo kiểm duyệt.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['template_id', 'Tên', 'Trạng thái', 'Chất lượng'],
            array_map(static fn (array $t): array => [
                (string) ($t['templateId'] ?? $t['template_id'] ?? '—'),
                mb_strimwidth((string) ($t['templateName'] ?? $t['template_name'] ?? '—'), 0, 40, '…'),
                (string) ($t['status'] ?? '—'),
                (string) ($t['templateQuality'] ?? $t['template_quality'] ?? '—'),
            ], $items),
        );

        $first = $items[0]['templateId'] ?? $items[0]['template_id'] ?? '<template_id>';

        $this->newLine();
        $this->line("  Xem tham số: <comment>php artisan zalo:zbs:templates --id={$first}</comment>");
        $this->newLine();

        return self::SUCCESS;
    }

    /** @param \FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource $zbs */
    private function showOne($zbs, string $templateId): int
    {
        $response = $zbs->template($templateId);

        /** @var array<string, mixed> $data */
        $data = (array) $response->payload();

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Tên</>', (string) ($data['templateName'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Trạng thái</>', (string) ($data['status'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Quota hôm nay</>', (string) ($data['templateRemainingQuota'] ?? '—'));

        /** @var list<array<string, mixed>> $params */
        $params = (array) ($data['listParams'] ?? []);

        if ($params === []) {
            $this->newLine();
            $this->components->warn('Template này không khai tham số nào.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Tham số', 'Kiểu', 'Bắt buộc', 'Độ dài'],
            array_map(static function (array $p): array {
                $min = $p['minLength'] ?? null;
                $max = $p['maxLength'] ?? null;

                return [
                    (string) ($p['name'] ?? '—'),
                    (string) ($p['type'] ?? '—'),
                    ($p['require'] ?? false) ? 'có' : 'không',
                    $min === null && $max === null ? '—' : "{$min}–{$max}",
                ];
            }, $params),
        );

        $example = [];
        foreach ($params as $p) {
            $example[(string) ($p['name'] ?? 'x')] = 'giá trị';
        }

        $this->newLine();
        $this->line('  Gửi thử: <comment>php artisan zalo:zbs:send <sđt> '.$templateId
            ." '".json_encode($example, JSON_UNESCAPED_UNICODE)."'</comment>");
        $this->newLine();

        return self::SUCCESS;
    }
}
