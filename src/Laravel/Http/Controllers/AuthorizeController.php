<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\Authorizer;
use FieldVn\Zalo\Laravel\Support\OAuthState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthorizeController
{
    public const CONNECTED_REDIRECT_SECONDS = 5;

    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * Chuyển hướng admin OA sang trang cấp quyền của Zalo.
     *
     * `{oa}` được resolve sẵn thành model bởi Route::bind trong ServiceProvider —
     * KHÔNG typehint string ở đây, nếu không Laravel sẽ nhồi model vào tham số
     * string và ném TypeError.
     */
    public function redirect(ZaloOa $oa): RedirectResponse
    {
        return redirect()->away($this->authorizer->consentUrl($oa));
    }

    /** Zalo chuyển admin về đây kèm `code`. */
    public function callback(Request $request, OaRepository $oas): RedirectResponse
    {
        $home = redirect()->route('zalo.dashboard');

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
            $connected = $this->authorizer->completeWithCode($record, $code);
        } catch (ZaloException $e) {
            return $home->with('zalo.error', 'Cấp quyền thất bại: '.$e->getMessage());
        }

        $name = trim((string) $connected->name);
        $slug = trim((string) $connected->slug);

        return redirect()
            ->route('zalo.oauth.connected')
            ->with('zalo.connected', [
                'name' => $name !== '' ? $name : $slug,
                'slug' => $slug,
            ]);
    }

    /**
     * Trang báo liên kết xong — tách khỏi dashboard vì `/zalo` là UI kỹ thuật,
     * flash một dòng trên tổng quan dễ trôi.
     */
    public function connected(Request $request): View|RedirectResponse
    {
        /** @var array{name?:mixed, slug?:mixed} $payload */
        $payload = $request->session()->get('zalo.connected', []);
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));

        if ($name === '' && $slug === '') {
            return redirect()->away($this->appHomeUrl());
        }

        return view('zalo::oauth-connected', [
            'oaName' => $name !== '' ? $name : $slug,
            'oaSlug' => $slug,
            'homeUrl' => $this->appHomeUrl(),
            'redirectSeconds' => self::CONNECTED_REDIRECT_SECONDS,
        ]);
    }

    /** Trang chủ sản phẩm (`APP_URL`), không phải UI kỹ thuật `/zalo`. */
    private function appHomeUrl(): string
    {
        $fromConfig = trim((string) config('app.url'), '/');

        return $fromConfig !== '' ? $fromConfig : url('/');
    }
}
