<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;
use JsonException;

/**
 * Tạo template ZBS từ file JSON (agency / tạo hàng loạt).
 *
 * Zalo đang đánh giá lại API create; ưu tiên giao diện ZBS Account nếu không
 * cần tạo số lượng lớn. Không có Open API xoá template.
 */
class ZbsCreateCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:zbs:create
        {file : Đường dẫn file JSON đúng payload API tạo template}
        {--oa= : Slug của OA. Bỏ trống thì dùng OA mặc định}';

    protected $description = 'Tạo template ZBS từ file JSON';

    public function handle(Factory $zalo): int
    {
        try {
            $oa = $zalo->oa($this->stringOption('oa') ?: null);
            $payload = $this->readPayload($this->stringArgument('file'));
            $response = $oa->zbs()->create($payload);
        } catch (ApiException $e) {
            $this->components->error($e->explain());

            return self::FAILURE;
        } catch (ZaloException|JsonException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->payload();

        $this->newLine();
        $this->components->info('Đã gửi mẫu. Zalo sẽ kiểm duyệt.');
        $this->components->twoColumnDetail('<fg=gray>template_id</>', (string) ($data['template_id'] ?? $data['templateId'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Trạng thái</>', (string) ($data['status'] ?? 'PENDING_REVIEW'));
        $this->newLine();
        $this->line('  <fg=gray>Không có API xoá. Chờ webhook change_template_status hoặc:</>');
        $id = (string) ($data['template_id'] ?? $data['templateId'] ?? '<id>');
        $this->line("  <comment>php artisan zalo:zbs:wait {$id}</comment>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     * @throws JsonException
     */
    private function readPayload(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ConfigurationException("Không đọc được file JSON: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigurationException("Không đọc được nội dung file: {$path}");
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            throw new ConfigurationException('File phải chứa một object JSON (payload tạo template).');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
