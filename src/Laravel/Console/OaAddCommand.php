<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OaAddCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:oa:add
                            {--name=      : Tên gợi nhớ}
                            {--slug=      : Slug dùng trong code, ví dụ Zalo::oa("cskh")}
                            {--oa-id=     : OA ID lấy từ trang quản trị Zalo OA}
                            {--app=       : Key của app trong config (mặc định: default)}
                            {--tags=      : Danh sách tag, cách nhau bởi dấu phẩy}
                            {--authorize  : Cấp quyền luôn sau khi tạo}';

    protected $description = 'Thêm một Official Account';

    public function handle(): int
    {
        $name = $this->stringOption('name') ?: (string) $this->ask('Tên OA');

        if ($name === '') {
            $this->components->error('Tên không được rỗng.');

            return self::FAILURE;
        }

        $slug = $this->stringOption('slug') ?: (string) $this->ask('Slug', Str::slug($name));
        $slug = Str::slug($slug);

        if (ZaloOa::query()->where('slug', $slug)->exists()) {
            $this->components->error("Slug `{$slug}` đã tồn tại.");

            return self::FAILURE;
        }

        $oaId = $this->stringOption('oa-id') ?: trim((string) $this->ask('OA ID'));

        if ($oaId === '') {
            $this->components->error('OA ID không được rỗng — lấy ở trang quản trị Zalo OA.');

            return self::FAILURE;
        }

        if (ZaloOa::query()->where('oa_id', $oaId)->exists()) {
            $this->components->error("OA ID `{$oaId}` đã được thêm rồi.");

            return self::FAILURE;
        }

        $appKey = $this->stringOption('app') ?: (string) config('zalo.default_app', 'default');

        if (config('zalo.apps.'.$appKey) === null) {
            $this->components->error("App [{$appKey}] chưa được khai trong config/zalo.php.");

            return self::FAILURE;
        }

        $tags = $this->listOption('tags');

        $oa = ZaloOa::create([
            'name' => $name,
            'slug' => $slug,
            'oa_id' => $oaId,
            'app_key' => $appKey,
            'tags' => $tags ?: null,
            // Chưa có token thì chưa dùng được — bật lên sau khi cấp quyền xong.
            'is_active' => false,
        ]);

        $this->newLine();
        $this->line("  <fg=green>✓</> Đã thêm OA <options=bold>{$oa->slug}</>");
        $this->line('  <fg=gray>Trạng thái: chưa cấp quyền (is_active=false)</>');
        $this->newLine();

        if ($this->option('authorize') || $this->confirm('Cấp quyền ngay bây giờ?', true)) {
            return $this->call('zalo:authorize', ['oa' => $oa->slug]);
        }

        $this->line("  <fg=gray>Cấp quyền sau bằng: php artisan zalo:authorize {$oa->slug}</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
