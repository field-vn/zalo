<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Thêm một Zalo Bot.
 *
 * Đơn giản hơn OA rất nhiều: token tĩnh, không OAuth, không refresh, không
 * dính Zalo App. Lấy token ở https://bot.zaloplatforms.com
 */
class BotAddCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:bot:add
                            {--name=  : Tên gợi nhớ}
                            {--slug=  : Slug dùng trong code, ví dụ Zalo::bot("support")}
                            {--token= : Bot token dạng 123456:abcdef}
                            {--skip-verify : Không gọi getMe để kiểm tra token}';

    protected $description = 'Thêm một Zalo Bot';

    public function handle(Factory $zalo): int
    {
        $name = $this->stringOption('name') ?: (string) $this->ask('Tên bot');

        if ($name === '') {
            $this->components->error('Tên không được rỗng.');

            return self::FAILURE;
        }

        $slug = Str::slug($this->stringOption('slug') ?: (string) $this->ask('Slug', Str::slug($name)));

        if (ZaloBot::query()->where('slug', $slug)->exists()) {
            $this->components->error("Slug `{$slug}` đã tồn tại.");

            return self::FAILURE;
        }

        $token = $this->stringOption('token') ?: trim((string) $this->secret('Bot token'));

        if ($token === '') {
            $this->components->error('Token không được rỗng — lấy ở https://bot.zaloplatforms.com');

            return self::FAILURE;
        }

        if (! str_contains($token, ':')) {
            $this->components->warn('Token thường có dạng `123456:abcdef`. Kiểm tra lại nếu bạn copy thiếu.');
        }

        $bot = ZaloBot::create([
            'name' => $name,
            'slug' => $slug,
            'token' => $token,
            'is_active' => true,
        ]);

        if ($this->option('skip-verify')) {
            $this->done($bot);

            return self::SUCCESS;
        }

        // Khác OA: token bot kiểm tra được NGAY vì không cần cấp quyền gì thêm.
        // Tận dụng điều đó để bắt lỗi copy nhầm token ngay lúc thêm.
        return $this->verify($zalo, $bot);
    }

    private function verify(Factory $zalo, ZaloBot $bot): int
    {
        try {
            $info = $zalo->bot($bot->slug)->me();
        } catch (ZaloException $e) {
            $this->newLine();
            $this->components->error('Token không dùng được: '.$e->getMessage());
            $this->line('  <fg=gray>Đã xoá bot vừa tạo. Kiểm tra lại token rồi thử lại.</>');
            $this->newLine();

            // Giữ lại bản ghi hỏng chỉ khiến `zalo:bot:list` nhiễu và người
            // dùng tưởng đã thêm thành công.
            $bot->forceDelete();

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        if (isset($data['username'])) {
            $bot->forceFill(['username' => (string) $data['username']])->save();
        }

        $this->done($bot);

        return self::SUCCESS;
    }

    private function done(ZaloBot $bot): void
    {
        $this->newLine();
        $this->line("  <fg=green>✓</> Đã thêm bot <options=bold>{$bot->slug}</>");

        if ($bot->username !== null) {
            $this->line("     Username  @{$bot->username}");
        }

        $this->line('     Token     '.$bot->maskedToken());
        $this->newLine();
        $this->line("  <fg=gray>Dùng: Zalo::bot('{$bot->slug}')->messages()->send(\$chatId, 'Xin chào');</>");
        $this->newLine();
    }
}
