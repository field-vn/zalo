<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Đọc argument/option về đúng kiểu string.
 *
 * `$this->argument()` và `$this->option()` của Symfony trả về
 * `array|bool|float|int|string|null`, nên ép `(string)` thẳng sẽ vỡ ở
 * PHPStan level 8. Thu về một chỗ thay vì rải `is_string()` khắp các command.
 *
 * @mixin Command
 */
trait InteractsWithInput
{
    protected function stringArgument(string $key, string $default = ''): string
    {
        $value = $this->argument($key);

        return is_string($value) ? trim($value) : $default;
    }

    protected function stringOption(string $key, string $default = ''): string
    {
        $value = $this->option($key);

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Option dạng danh sách ngăn cách bởi dấu phẩy.
     *
     * @return list<string>
     */
    protected function listOption(string $key): array
    {
        $raw = $this->stringOption($key);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
