<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

use FieldVn\Zalo\Contracts\Channel;
use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Auth\RefreshingTokenProvider;
use FieldVn\Zalo\Core\Channels\OA\Resources\MessageResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\TagResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\UploadResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\UserResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Core\Http\PendingRequest;
use FieldVn\Zalo\Core\Http\Response;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\TokenStatus;
use FieldVn\Zalo\Laravel\Support\TokenStatusCache;
use Illuminate\Support\Traits\Macroable;

/**
 * Một Official Account.
 *
 * Channel chỉ là điểm vào; việc thật nằm ở Resource. Cách chia này giữ cho
 * class không phình thành God object khi số endpoint tăng lên.
 */
final class OAChannel implements Channel
{
    use Macroable;

    public function __construct(
        private readonly string $slug,
        private readonly Transport $transport,
        private readonly RefreshingTokenProvider $tokens,
        private readonly string $baseUrl = 'https://openapi.zalo.me',
    ) {}

    public function key(): string
    {
        return $this->slug;
    }

    public function messages(): MessageResource
    {
        return new MessageResource($this->request());
    }

    public function users(): UserResource
    {
        return new UserResource($this->request());
    }

    /**
     * ZBS Template Message — gửi tin theo mẫu tới số điện thoại.
     *
     * Dùng access token của OA nhưng endpoint nằm ở domain khác, nên nhận
     * transport trực tiếp thay vì đi qua request() vốn đã gắn baseUrl của OA.
     */
    public function zbs(): ZbsResource
    {
        return new ZbsResource(
            transport: $this->transport,
            headers: fn (): array => ['access_token' => $this->tokens->accessToken()],
            baseUrl: (string) (config('zalo.endpoints.zbs') ?? 'https://business.openapi.zalo.me'),
            mode: (string) (config('zalo.zbs.mode') ?? ZbsResource::MODE_DEVELOPMENT),
        );
    }

    /** Upload ảnh lấy attachment_id — bắt buộc trước khi gửi ảnh qua OA. */
    public function uploads(): UploadResource
    {
        return new UploadResource($this->request());
    }

    public function tags(): TagResource
    {
        return new TagResource($this->request());
    }

    /** Thông tin OA — dùng cho nút "Test kết nối" và để tự điền tên/avatar. */
    public function info(): Response
    {
        return $this->request()->get('/v3.0/oa/getoa')->throwIfFailed();
    }

    public function ping(): bool
    {
        try {
            return $this->info()->successful();
        } catch (ZaloException) {
            return false;
        }
    }

    /** Thoát hiểm cho endpoint package chưa bọc. */
    public function request(): PendingRequest
    {
        return new PendingRequest(
            $this->transport,
            $this->baseUrl,
            // Lười: mỗi request lấy token mới nhất, phòng khi vừa refresh giữa chừng.
            fn (): array => ['access_token' => $this->tokens->accessToken()],
        );
    }

    public function tokens(): RefreshingTokenProvider
    {
        return $this->tokens;
    }

    /**
     * Trạng thái access token (có cache ngắn hạn).
     *
     * Resolve OA theo slug — không đổi chữ ký constructor. OA đã xoá → missing.
     */
    public function tokenStatus(): TokenStatus
    {
        $oa = ZaloOa::query()->where('slug', $this->slug)->first();

        if ($oa === null) {
            return TokenStatus::missing();
        }

        return app(TokenStatusCache::class)->remember($oa);
    }

    /**
     * Chọn kênh rồi gửi: ưu tiên OA CS theo user id, fallback ZBS theo SĐT.
     */
    public function notifier(): OaNotifier
    {
        return new OaNotifier($this);
    }
}
