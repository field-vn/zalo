<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\Authorizer;
use FieldVn\Zalo\Laravel\Support\OAuthState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthorizeController
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /** Chuyển hướng admin OA sang trang cấp quyền của Zalo. */
    public function redirect(string $oa, OaRepository $oas): RedirectResponse
    {
        $record = $oas->find($oa);

        if (! $record instanceof ZaloOa) {
            abort(404, "Không tìm thấy OA [{$oa}].");
        }

        return redirect()->away($this->authorizer->consentUrl($record));
    }

    /** Zalo chuyển admin về đây kèm `code`. */
    public function callback(Request $request, OaRepository $oas): RedirectResponse
    {
        $home = redirect()->to(url((string) config('zalo.ui.path', 'zalo')));

        // Admin bấm "Từ chối" — không phải lỗi, đừng hiển thị như lỗi hệ thống.
        if ($request->filled('error')) {
            return $home->with('zalo.error', 'Bạn đã từ chối cấp quyền cho ứng dụng.');
        }

        $oaId = OAuthState::consume((string) $request->query('state', ''));

        if ($oaId === null) {
            // State sai/hết hạn/đã dùng. Có thể là CSRF, cũng có thể chỉ là
            // người dùng để tab mở quá lâu — thông báo phải phủ được cả hai.
            return $home->with(
                'zalo.error',
                'Phiên cấp quyền không hợp lệ hoặc đã hết hạn ('
                    .OAuthState::ttlMinutes().' phút). Hãy bấm Authorize lại.',
            );
        }

        $record = $oas->find($oaId);

        if (! $record instanceof ZaloOa) {
            return $home->with('zalo.error', 'OA tương ứng đã bị xoá trong lúc cấp quyền.');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $home->with('zalo.error', 'Zalo không trả về mã cấp quyền.');
        }

        try {
            $this->authorizer->completeWithCode($record, $code);
        } catch (ZaloException $e) {
            return $home->with('zalo.error', 'Cấp quyền thất bại: '.$e->getMessage());
        }

        return $home->with('zalo.success', "Đã kết nối OA `{$record->slug}` thành công.");
    }
}
