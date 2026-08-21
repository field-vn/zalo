<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Middleware;

use Closure;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo vệ UI. Xem ADR-0004.
 *
 * Nguyên tắc bất di bất dịch: FAIL-CLOSED. Chưa cấu hình gì thì chặn ở mọi
 * môi trường khác local — không bao giờ mở mặc định.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config('zalo.ui');

        // 1. Lọc IP trước — rẻ nhất, và độc lập hoàn toàn với việc auth có lỗi hay không.
        /** @var list<string> $allowed */
        $allowed = $cfg['allowed_ips'] ?? [];

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403, 'IP của bạn không nằm trong danh sách được phép truy cập Zalo UI.');
        }

        // 2. Có gate của app thì gate thắng — dùng lại hệ thống auth sẵn có.
        if (ZaloManager::hasAuthGate()) {
            abort_unless(ZaloManager::checkAuthGate($request), 403);

            return $next($request);
        }

        $user     = (string) ($cfg['user'] ?? '');
        $password = (string) ($cfg['password'] ?? '');

        // 3. Chưa cấu hình credential → chỉ chạy được ở local.
        if ($user === '' || $password === '') {
            abort_unless(app()->environment('local'), 403, $this->notConfiguredMessage());

            return $next($request);
        }

        // 4. Basic auth. hash_equals chứ không phải === — chống timing attack,
        //    một dòng và miễn phí. Luôn so cả hai vế để thời gian không đổi.
        $okUser = hash_equals($user, (string) $request->getUser());
        $okPass = hash_equals($password, (string) $request->getPassword());

        if (! $okUser || ! $okPass) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Zalo"',
            ]);
        }

        return $next($request);
    }

    private function notConfiguredMessage(): string
    {
        return 'Zalo UI chưa được bảo vệ. Đặt ZALO_UI_USER và ZALO_UI_PASSWORD trong .env, '
            .'hoặc định nghĩa Zalo::auth() trong AppServiceProvider.';
    }
}
