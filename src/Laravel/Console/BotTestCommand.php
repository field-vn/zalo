<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

class BotTestCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:bot:test {bot : Slug hoặc id của bot}';

    protected $description = 'Gọi thử Bot API để xác nhận token còn dùng được';

    public function handle(Factory $zalo): int
    {
        $key = $this->stringArgument('bot');

        $this->newLine();

        try {
            $info = $zalo->bot($key)->me();
        } catch (ZaloException $e) {
            $this->line("  <fg=red>✗</> {$key} — {$e->getMessage()}");
            $this->newLine();

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        $this->line('  <fg=green>✓</> Token còn dùng được');
        $this->line('     Tên       '.($data['first_name'] ?? $data['name'] ?? '—'));
        $this->line('     Username  '.(isset($data['username']) ? '@'.$data['username'] : '—'));
        $this->newLine();

        return self::SUCCESS;
    }
}
