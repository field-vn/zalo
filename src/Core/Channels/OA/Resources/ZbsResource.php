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
 *     $oa->zbs()->templates();                 // mọi template và trạng thái
 *     $oa->zbs()->template($id);               // tham số bắt buộc của một mẫu
 *     $oa->zbs()->sampleData($id);             // dữ liệu mẫu để gửi thử
 *     $oa->zbs()->send('0987654321', $id, ['otp' => '123456']);
 */
final class ZbsResource
{
    public const MODE_DEVELOPMENT = 'development';

    public const MODE_PRODUCTION = 'production';

    /**
     * Trạng thái template khi LỌC danh sách — Zalo nhận số, không nhận chữ.
     *
     * Chú ý chỗ dễ nhầm: trong response Zalo trả `status` là CHỮ ("ENABLE"),
     * nhưng khi truyền lên để lọc thì phải là SỐ. Truyền chữ lên nhận về
     * `-132 Invalid status`.
     */
    public const STATUS_ENABLE = 1;

    public const STATUS_PENDING_REVIEW = 2;

    public const STATUS_REJECT = 3;

    public const STATUS_DISABLE = 4;

    public const STATUS_DELETE = 5;

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
     * Mặc định trả về template ở MỌI trạng thái. Lọc sẵn theo `ENABLE` nghe có
     * vẻ gọn hơn, nhưng khi OA còn template đang chờ duyệt thì kết quả rỗng lại
     * bị hiểu thành "chưa tạo mẫu nào" — trong khi việc cần làm là chờ duyệt.
     *
     * @param  int|null  $status  Một trong các hằng STATUS_*, hoặc null để lấy tất cả
     *
     * @throws ConfigurationException khi truyền status không hợp lệ
     */
    public function templates(int $offset = 0, int $limit = 100, ?int $status = null): Response
    {
        $params = [
            'offset' => $offset,
            'limit' => min($limit, 100),
        ];

        if ($status !== null) {
            if ($status < self::STATUS_ENABLE || $status > self::STATUS_DELETE) {
                throw new ConfigurationException(sprintf(
                    'status `%d` không hợp lệ. Zalo nhận số 1–5 (1 ENABLE, 2 PENDING_REVIEW, '
                    .'3 REJECT, 4 DISABLE, 5 DELETE), không nhận chuỗi như "ENABLE".',
                    $status,
                ));
            }

            $params['status'] = $status;
        }

        return $this->request()->get('/template/all', $params)->throwIfFailed();
    }

    /**
     * Chi tiết một template, gồm tham số bắt buộc và ràng buộc độ dài.
     *
     * Đây là thứ cần đọc trước khi gửi: sai tên tham số thì Zalo từ chối, và
     * tin bị từ chối vẫn có thể bị tính phí.
     *
     * Lấy ra từ chính danh sách chứ không gọi endpoint riêng: `/template/all`
     * đã trả về `listParams` đầy đủ cho từng template, nên thêm một endpoint
     * nữa chỉ thêm một chỗ để sai.
     *
     * @return array<string, mixed>|null null khi OA không có template mang id này
     */
    public function template(string $templateId): ?array
    {
        /** @var list<array<string, mixed>> $items */
        $items = (array) $this->templates()->payload();

        foreach ($items as $item) {
            $id = $item['templateId'] ?? $item['template_id'] ?? null;

            if ($id !== null && (string) $id === $templateId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Dữ liệu mẫu của một template — dùng làm `template_data` để gửi thử.
     *
     * Trả về đúng bộ tham số template cần, đã điền sẵn giá trị mẫu, nên không
     * phải tự đoán tên tham số.
     */
    public function sampleData(string $templateId): Response
    {
        return $this->request()
            ->get('/template/sample-data', ['template_id' => $templateId])
            ->throwIfFailed();
    }

    /** Số tin còn gửi được trong ngày. */
    public function quota(): Response
    {
        return $this->request()->get('/message/quota')->throwIfFailed();
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
