<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Http\PendingRequest;
use FieldVn\Zalo\Core\Http\Response;
use FieldVn\Zalo\Support\PhoneNumber;

/**
 * ZBS Template Message — gửi tin theo mẫu tới SỐ ĐIỆN THOẠI.
 *
 * Đây là kênh duy nhất gửi được tới người CHƯA từng tương tác với OA. Đổi lại
 * ba ràng buộc, cả ba đều do Zalo đặt ra chứ không phải package:
 *
 *   1. Chỉ gửi được theo template đã đăng ký và ĐƯỢC DUYỆT
 *   2. Mỗi tin đều TÍNH PHÍ, trừ vào số dư ZBS Account
 *   3. Endpoint nằm ở business.openapi.zalo.me, khác openapi.zalo.me của OA
 *
 * Vì tốn tiền nên mặc định chạy ở `development`: chỉ gửi tới quản trị viên
 * của OA hoặc App, không mất phí, không tính vào báo cáo. Muốn gửi cho khách
 * thật phải đặt ZALO_ZBS_MODE=production một cách tường minh.
 *
 *     $oa->zbs()->templates();                 // template nào đã duyệt
 *     $oa->zbs()->template($id);               // tham số bắt buộc của nó
 *     $oa->zbs()->send('0987654321', $id, ['otp' => '123456']);
 */
final class ZbsResource
{
    public const MODE_DEVELOPMENT = 'development';

    public const MODE_PRODUCTION = 'production';

    public function __construct(
        private readonly Transport $transport,
        /** @var callable(): array<string, string> */
        private $headers,
        private readonly string $baseUrl = 'https://business.openapi.zalo.me',
        private readonly string $mode = self::MODE_DEVELOPMENT,
    ) {}

    /**
     * Gửi một tin theo template.
     *
     * `$phone` nhận mọi dạng thường gặp (0987…, +8498…, 8498…) và được chuẩn
     * hoá trước khi gửi.
     *
     * `$data` phải khớp tham số template đã đăng ký — gọi `template($id)` để
     * xem tên và ràng buộc của từng tham số.
     *
     * @param  array<string, string|int>  $data
     *
     * @throws ConfigurationException khi số điện thoại hoặc tham số không hợp lệ
     */
    public function send(
        string $phone,
        string $templateId,
        array $data,
        ?string $trackingId = null,
        ?string $mode = null,
    ): Response {
        if (trim($templateId) === '') {
            throw new ConfigurationException('Thiếu template_id.');
        }

        if ($data === []) {
            throw new ConfigurationException(
                'template_data rỗng. Xem tham số bắt buộc: $oa->zbs()->template($templateId)'
            );
        }

        $payload = [
            'phone' => PhoneNumber::normalize($phone),
            'template_id' => $templateId,
            'template_data' => $this->stringify($data),
            'mode' => $this->resolveMode($mode),
        ];

        if ($trackingId !== null && $trackingId !== '') {
            $payload['tracking_id'] = $trackingId;
        }

        return $this->request()->post('/message/template', $payload)->throwIfFailed();
    }

    /**
     * Template đã đăng ký với OA này.
     *
     * @param  string  $status  ENABLE để chỉ lấy template dùng được
     */
    public function templates(int $offset = 0, int $limit = 100, string $status = 'ENABLE'): Response
    {
        return $this->request()->get('/template/all', [
            'offset' => $offset,
            'limit' => min($limit, 100),
            'status' => $status,
        ])->throwIfFailed();
    }

    /**
     * Chi tiết một template, gồm danh sách tham số bắt buộc và ràng buộc độ dài.
     *
     * Đây là thứ cần đọc trước khi gửi: sai tên tham số thì Zalo từ chối, và
     * tin bị từ chối vẫn có thể bị tính phí.
     */
    public function template(string $templateId): Response
    {
        return $this->request()
            ->get('/template/info', ['template_id' => $templateId])
            ->throwIfFailed();
    }

    /** Số tin còn gửi được trong ngày. */
    public function quota(): Response
    {
        return $this->request()->get('/template/quota')->throwIfFailed();
    }

    /** Trạng thái giao tin của một message_id đã gửi. */
    public function status(string $messageId): Response
    {
        return $this->request()
            ->get('/message/status', ['message_id' => $messageId])
            ->throwIfFailed();
    }

    /** Đang chạy ở chế độ nào — dùng để hiển thị cảnh báo trên UI/CLI. */
    public function mode(): string
    {
        return $this->mode;
    }

    public function isProduction(): bool
    {
        return $this->mode === self::MODE_PRODUCTION;
    }

    /**
     * Zalo yêu cầu mọi giá trị trong template_data là chuỗi.
     *
     * Truyền int (mã OTP, số đơn) là chuyện rất tự nhiên trong PHP, nên ép ở
     * đây thay vì bắt người dùng nhớ.
     *
     * @param  array<string, string|int>  $data
     * @return array<string, string>
     */
    private function stringify(array $data): array
    {
        return array_map(static fn (string|int $v): string => (string) $v, $data);
    }

    private function resolveMode(?string $override): string
    {
        $mode = $override ?? $this->mode;

        if (! in_array($mode, [self::MODE_DEVELOPMENT, self::MODE_PRODUCTION], true)) {
            throw new ConfigurationException(
                "mode `{$mode}` không hợp lệ — chỉ nhận `development` hoặc `production`."
            );
        }

        return $mode;
    }

    private function request(): PendingRequest
    {
        return new PendingRequest($this->transport, $this->baseUrl, $this->headers);
    }
}
