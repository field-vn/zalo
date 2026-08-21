<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\Authorizer;
use Illuminate\Console\Command;

/**
 * Cấp quyền cho một OA từ CLI.
 *
 * Hỗ trợ cả hai tình huống, vì môi trường dev thường không có domain public:
 *
 *  1. Callback truy cập được → Zalo tự gọi về, token lưu xong. Chỉ cần Enter.
 *  2. Callback không truy cập được (localhost) → copy `code` trên thanh địa chỉ
 *     dán vào đây, command tự đổi lấy token.
 */
class AuthorizeCommand extends Command
{
    protected $signature = 'zalo:authorize {oa : Slug hoặc id của OA}';

    protected $description = 'Cấp quyền cho một OA và lấy token lần đầu';

    public function handle(OaRepository $oas, Authorizer $authorizer): int
    {
        $key    = (string) $this->argument('oa');
        $record = $oas->find($key);

        if (! $record instanceof ZaloOa) {
            $this->components->error("Không tìm thấy OA [{$key}].");
            $this->line('  <fg=gray>Xem danh sách: php artisan zalo:oa:list</>');
            $this->line('  <fg=gray>Thêm mới:      php artisan zalo:oa:add</>');

            return self::FAILURE;
        }

        $redirect = $authorizer->redirectUri($record);
        $url      = $authorizer->consentUrl($record);

        $this->newLine();
        $this->line("  <options=bold>Cấp quyền cho OA `{$record->slug}`</>");
        $this->newLine();
        $this->line('  <fg=yellow>Redirect URI phải khớp CHÍNH XÁC giá trị khai trong Zalo Developers:</>');
        $this->line("       <fg=cyan>{$redirect}</>");
        $this->newLine();
        $this->line('  1. Mở link sau bằng tài khoản <options=bold>admin của OA</>:');
        $this->newLine();
        $this->line("       <options=underscore>{$url}</>");
        $this->newLine();
        $this->line('  2. Bấm đồng ý cấp quyền.');
        $this->newLine();

        $code = trim((string) $this->ask(
            '  3. Nếu trình duyệt báo kết nối thành công, để trống và bấm Enter.'
                ."\n     Nếu callback không truy cập được, dán giá trị `code` trên thanh địa chỉ",
            '',
        ));

        if ($code === '') {
            return $this->verify($oas, $record);
        }

        try {
            $authorizer->completeWithCode($record, $code);
        } catch (ZaloException $e) {
            $this->newLine();
            $this->components->error('Cấp quyền thất bại: '.$e->getMessage());
            $this->line('  <fg=gray>Kiểm tra ZALO_APP_ID / ZALO_APP_SECRET và redirect URI đã khai với Zalo.</>');

            return self::FAILURE;
        }

        return $this->success($record);
    }

    /** Người dùng bảo callback đã chạy — xác nhận token thật sự đã có. */
    private function verify(OaRepository $oas, ZaloOa $record): int
    {
        $fresh = $oas->find($record->slug);

        if (! $fresh instanceof ZaloOa || $fresh->token === null) {
            $this->newLine();
            $this->components->error('Chưa thấy token nào được lưu cho OA này.');
            $this->line('  <fg=gray>Callback có thể chưa chạy. Chạy lại lệnh và dán `code` thủ công.</>');

            return self::FAILURE;
        }

        return $this->success($fresh);
    }

    private function success(ZaloOa $oa): int
    {
        $oa->refresh();

        $this->newLine();
        $this->line("  <fg=green;options=bold>✓ Đã kết nối OA `{$oa->slug}`</>");
        $this->line("     Tên     {$oa->name}");
        $this->line("     OA ID   {$oa->oa_id}");

        if ($oa->token?->expires_at !== null) {
            $this->line('     Token   hết hạn lúc '.$oa->token->expires_at->format('H:i d/m/Y'));
        }

        $this->newLine();
        $this->line('  <fg=gray>Token sẽ tự refresh nếu cron schedule:run đang chạy.</>');
        $this->line('  <fg=gray>Kiểm tra: php artisan zalo:doctor</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
