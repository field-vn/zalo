<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Events\ZaloOaConnected;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloAuditLog;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;

/**
 * Logic cấp quyền dùng chung cho controller (web) và command (CLI).
 *
 * Đặt ở đây thay vì nhân đôi trong hai nơi — luồng này có nhiều bước dễ sai
 * (đổi code, lưu token, đồng bộ thông tin OA, bắn event), lặp lại là mời lỗi.
 */
final class Authorizer
{
    public function __construct(private readonly ZaloManager $zalo) {}

    /** URL để admin OA bấm vào và cấp quyền. */
    public function consentUrl(ZaloOa $oa): string
    {
        return $this->zalo->oauth($oa->app_key)->consentUrl(
            $this->redirectUri($oa),
            OAuthState::issue((int) $oa->getKey()),
        );
    }

    /**
     * Zalo yêu cầu redirect_uri tuyệt đối và khớp CHÍNH XÁC giá trị đã khai
     * trong Zalo Developers.
     *
     * Uỷ quyền cho OaPresenter để UI và luồng gửi đi dùng CÙNG một nguồn —
     * nếu hai nơi tính khác nhau, UI sẽ hiện một đằng mà gửi một nẻo.
     */
    public function redirectUri(ZaloOa $oa): string
    {
        return OaPresenter::redirectUri($oa->app_key);
    }

    /**
     * Đổi code lấy token, lưu lại, rồi đồng bộ thông tin OA.
     *
     * @throws ZaloException
     */
    public function completeWithCode(ZaloOa $oa, string $code): ZaloOa
    {
        $tokens = $this->zalo->oauth($oa->app_key)->exchangeCode($code);

        (new EloquentTokenStore($oa))->put($tokens);

        $oa->forceFill(['is_active' => true])->save();
        $oa->refresh();

        // Manager cache channel theo id — phải xoá, nếu không lần gọi tiếp theo
        // vẫn dùng instance dựng từ lúc chưa có token.
        $this->zalo->forgetResolved();

        $this->syncProfile($oa);

        ZaloAuditLog::record('oa.authorized', $oa);
        ZaloOaConnected::dispatch($oa);

        return $oa;
    }

    /**
     * Lấy tên và avatar từ Zalo để user không phải gõ tay.
     *
     * Đồng thời đây là bước xác thực thật sự đầu tiên của cặp app_id/app_secret
     * — trước lúc này không có cách nào kiểm tra chúng.
     */
    public function syncProfile(ZaloOa $oa): void
    {
        try {
            $info = $this->zalo->oa($oa->slug)->info();
        } catch (ZaloException) {
            // Token đã lưu thành công rồi; không lấy được profile chỉ là bất tiện,
            // không phải lý do để coi cả luồng cấp quyền là thất bại.
            return;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        $oa->forceFill(array_filter([
            'name' => $data['name'] ?? null,
            'avatar_url' => $data['avatar'] ?? null,
            'description' => $data['description'] ?? null,
            'package_type' => isset($data['package_name']) ? (string) $data['package_name'] : null,
            'oa_id' => isset($data['oa_id']) ? (string) $data['oa_id'] : null,
        ], static fn ($v): bool => $v !== null && $v !== ''))->save();
    }
}
