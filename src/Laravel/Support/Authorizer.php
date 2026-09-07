<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Events\ZaloOaConnected;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloAuditLog;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;
use Illuminate\Database\QueryException;
use Throwable;

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
     * Nếu Zalo trả về `oa_id` đã thuộc hàng khác, gộp token vào hàng đó
     * (unique `zl_oas.oa_id`) rồi xoá hàng placeholder.
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

        $canonical = $this->syncProfile($oa);

        ZaloAuditLog::record('oa.authorized', $canonical);
        ZaloOaConnected::dispatch($canonical);

        return $canonical;
    }

    /**
     * Lấy tên và avatar từ Zalo để user không phải gõ tay.
     *
     * Đồng thời đây là bước xác thực thật sự đầu tiên của cặp app_id/app_secret
     * — trước lúc này không có cách nào kiểm tra chúng.
     */
    public function syncProfile(ZaloOa $oa): ZaloOa
    {
        try {
            $info = $this->zalo->oa($oa->slug)->info();
        } catch (ZaloException) {
            // Token đã lưu thành công rồi; không lấy được profile chỉ là bất tiện,
            // không phải lý do để coi cả luồng cấp quyền là thất bại.
            return $oa;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        $profile = array_filter([
            'name' => $data['name'] ?? null,
            'avatar_url' => $data['avatar'] ?? null,
            'description' => $data['description'] ?? null,
            'package_type' => isset($data['package_name']) ? (string) $data['package_name'] : null,
        ], static fn ($v): bool => $v !== null && $v !== '');

        $newOaId = isset($data['oa_id']) ? trim((string) $data['oa_id']) : '';
        if ($newOaId === '') {
            $oa->forceFill($profile)->save();

            return $oa;
        }

        $other = ZaloOa::query()
            ->where('oa_id', $newOaId)
            ->whereKeyNot($oa->getKey())
            ->first();

        if ($other !== null) {
            return $this->mergeOaInto($oa, $other, $profile);
        }

        try {
            $oa->forceFill($profile + ['oa_id' => $newOaId])->save();
        } catch (QueryException $e) {
            if (! $this->isDuplicateOaId($e)) {
                throw $e;
            }

            $other = ZaloOa::query()
                ->where('oa_id', $newOaId)
                ->whereKeyNot($oa->getKey())
                ->first();
            if ($other === null) {
                throw $e;
            }

            return $this->mergeOaInto($oa, $other, $profile);
        }

        return $oa;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function mergeOaInto(ZaloOa $source, ZaloOa $canonical, array $profile): ZaloOa
    {
        $pair = (new EloquentTokenStore($source))->get();
        if ($pair !== null) {
            (new EloquentTokenStore($canonical))->put($pair);
        }

        $canonical->forceFill(array_merge($profile, [
            'is_active' => true,
            'meta' => $this->mergedMeta($canonical, $source),
        ]))->save();

        $source->forceDelete();

        return $canonical->fresh(['token']) ?? $canonical;
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedMeta(ZaloOa $canonical, ZaloOa $source): array
    {
        $canonicalMeta = is_array($canonical->meta) ? $canonical->meta : [];
        $ids = array_values(array_unique(array_filter(
            [...$this->organizationIdsFrom($canonical), ...$this->organizationIdsFrom($source)],
            static fn (int $id): bool => $id > 0,
        )));

        $primary = (int) ($canonicalMeta['organization_id'] ?? 0);
        if ($primary <= 0) {
            $primary = $ids[0] ?? 0;
        }

        $meta = $canonicalMeta;
        if ($primary > 0) {
            $meta['organization_id'] = $primary;
        }
        $meta['organization_ids'] = $ids;

        return $meta;
    }

    /**
     * @return list<int>
     */
    private function organizationIdsFrom(ZaloOa $oa): array
    {
        $ids = [];
        $meta = is_array($oa->meta) ? $oa->meta : [];
        foreach ((array) ($meta['organization_ids'] ?? []) as $id) {
            $ids[] = (int) $id;
        }
        $ids[] = (int) ($meta['organization_id'] ?? 0);
        if (preg_match('/^org-(\d+)$/', (string) $oa->slug, $matches) === 1) {
            $ids[] = (int) $matches[1];
        }

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }

    private function isDuplicateOaId(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'zl_oas_oaid_uq')
            || (str_contains($message, 'Duplicate entry') && str_contains($message, 'oa_id'));
    }
}
