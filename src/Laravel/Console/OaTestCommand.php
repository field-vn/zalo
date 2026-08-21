<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use Illuminate\Console\Command;

class OaTestCommand extends Command
{
    protected $signature = 'zalo:oa:test {oa : Slug hoặc id của OA}';

    protected $description = 'Gọi thử Zalo API để xác nhận kết nối còn sống';

    public function handle(Factory $zalo): int
    {
        $key = (string) $this->argument('oa');

        $this->newLine();

        try {
            // getoa là endpoint rẻ nhất và không gây tác dụng phụ — đúng thứ
            // cần cho một phép thử kết nối.
            $info = $zalo->oa($key)->info();
        } catch (ZaloException $e) {
            $this->line("  <fg=red>✗</> {$key} — {$e->getMessage()}");
            $this->newLine();

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        $this->line("  <fg=green>✓</> Kết nối OK");
        $this->line('     Tên        '.($data['name'] ?? '—'));
        $this->line('     OA ID      '.($data['oa_id'] ?? '—'));
        $this->line('     Gói dịch vụ '.($data['package_name'] ?? '—'));
        $this->newLine();

        return self::SUCCESS;
    }
}
