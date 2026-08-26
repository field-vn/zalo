<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource;
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
        {--enabled : Chỉ hiện template đã duyệt và đang dùng được}';

    protected $description = 'Liệt kê template ZBS và tham số của chúng';

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

            $this->line('  <fg=gray>'.match ($e->errorCode) {
                -124 => 'Token OA hết hạn — cấp quyền lại cho OA này.',
                -120, -135, -138 => 'OA hoặc App chưa được cấp quyền dùng ZBS. '
                    .'Đăng ký tài khoản ZBS và liên kết với App tại zalo.solutions.',
                -105 => 'App chưa liên kết với OA nào.',
                default => 'Bảng mã lỗi: developers.zalo.me/docs/zalo-notification-service/phu-luc/bang-ma-loi',
            }.'</>');

            return self::FAILURE;
        }
    }

    private function showAll(ZbsResource $zbs): int
    {
        $response = $zbs->templates(
            status: $this->option('enabled') ? ZbsResource::STATUS_ENABLE : null,
        );

        /** @var list<array<string, mixed>> $items */
        $items = (array) $response->payload();

        if ($items === []) {
            $this->newLine();
            $this->components->warn(
                $this->option('enabled')
                    ? 'Không có template nào đang dùng được.'
                    : 'OA này chưa có template nào.',
            );
            $this->line('  <fg=gray>Tạo mẫu tin trong tài khoản ZBS rồi chờ Zalo kiểm duyệt.</>');

            if ($this->option('enabled')) {
                $this->line('  <fg=gray>Bỏ --enabled để xem cả mẫu đang chờ duyệt.</>');
            }

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

    private function showOne(ZbsResource $zbs, string $templateId): int
    {
        $data = $zbs->template($templateId);

        if ($data === null) {
            $this->newLine();
            $this->components->error("OA này không có template nào mang id `{$templateId}`.");
            $this->line('  <fg=gray>Chạy lệnh không kèm --id để xem danh sách.</>');
            $this->newLine();

            return self::FAILURE;
        }

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
