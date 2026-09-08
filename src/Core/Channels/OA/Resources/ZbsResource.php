<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Exceptions\ApiException;
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
 * Zalo không có Open API xoá/disable template — làm trên ZBS Account. Trạng
 * thái DELETE/DISABLE chỉ đọc được qua list, info, hoặc webhook
 * `change_template_status`.
 *
 *     $oa->zbs()->templates();                 // mọi template và trạng thái
 *     $oa->zbs()->info($id);                   // GET /template/info/v2
 *     $oa->zbs()->template($id);               // info(), null khi id không tồn tại
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

    /** Loại mẫu khi tạo/sửa — `template_type` trên API. */
    public const TYPE_CUSTOM = 1;

    public const TYPE_OTP = 2;

    public const TYPE_PAYMENT = 3;

    public const TYPE_VOUCHER = 4;

    public const TYPE_RATING = 5;

    /** Tag mẫu — Transaction / Customer care / Promotion. */
    public const TAG_TRANSACTION = 1;

    public const TAG_CUSTOMER_CARE = 2;

    public const TAG_PROMOTION = 3;

    /** `filterPreset` trên GET /template/all. */
    public const FILTER_ALL = 0;

    public const FILTER_THIS_APP = 1;

    /** Ảnh template: JPG/PNG, tối đa 500 KB — khác upload tin OA (1 MB). */
    public const MAX_IMAGE_BYTES = 512000;

    /** @var list<string> */
    private const IMAGE_TYPES = ['image/jpeg', 'image/png'];

    /** Delivery đã xong: giao tới máy, hoặc tin không tồn tại. */
    public const DELIVERY_DELIVERED = 1;

    public const DELIVERY_MISSING = -1;

    public const DELIVERY_PENDING = 0;

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
     * @param  int|null  $filterPreset  0 mọi mẫu của OA, 1 chỉ mẫu do App này tạo
     *
     * @throws ConfigurationException khi truyền status hoặc filterPreset không hợp lệ
     */
    public function templates(
        int $offset = 0,
        int $limit = 100,
        ?int $status = null,
        ?int $filterPreset = null,
    ): Response {
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

        if ($filterPreset !== null) {
            if ($filterPreset !== self::FILTER_ALL && $filterPreset !== self::FILTER_THIS_APP) {
                throw new ConfigurationException(
                    'filterPreset không hợp lệ. Zalo nhận 0 (mọi mẫu của OA) hoặc 1 (chỉ mẫu do App này tạo).'
                );
            }

            $params['filterPreset'] = $filterPreset;
        }

        return $this->request()->get('/template/all', $params)->throwIfFailed();
    }

    /**
     * Tạo template mới — Zalo kiểm duyệt, trả PENDING_REVIEW.
     *
     * `$layout` / `$params` là JSON đúng docs Zalo, không qua DSL. API đang
     * được Zalo đánh giá lại; ưu tiên tạo trên ZBS Account nếu không cần
     * tạo hàng loạt.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ConfigurationException khi thiếu trường bắt buộc
     */
    public function create(array $payload): Response
    {
        $body = $this->assertTemplatePayload($payload, requireTracking: true);

        return $this->request()->post('/template/create', $body)->throwIfFailed();
    }

    /**
     * Sửa template đang REJECT. Mẫu ENABLE không sửa được qua API.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ConfigurationException khi thiếu trường bắt buộc
     */
    public function edit(string $templateId, array $payload): Response
    {
        if (trim($templateId) === '') {
            throw new ConfigurationException('Thiếu template_id.');
        }

        $body = $this->assertTemplatePayload($payload, requireTracking: false);
        $body['template_id'] = $templateId;

        return $this->request()->post('/template/edit', $body)->throwIfFailed();
    }

    /**
     * Chi tiết một template qua GET /template/info/v2.
     *
     * Đây là thứ cần đọc trước khi gửi: sai tên tham số thì Zalo từ chối, và
     * tin bị từ chối vẫn có thể bị tính phí. Payload có `listParams`, `reason`,
     * `previewUrl`, `status`.
     *
     * @throws ConfigurationException khi thiếu template_id
     * @throws ApiException khi Zalo từ chối (kể cả id không tồn tại)
     */
    public function info(string $templateId): Response
    {
        if (trim($templateId) === '') {
            throw new ConfigurationException('Thiếu template_id.');
        }

        return $this->request()
            ->get('/template/info/v2', ['template_id' => $templateId])
            ->throwIfFailed();
    }

    /**
     * Chi tiết một template, gồm tham số bắt buộc và ràng buộc độ dài.
     *
     * Gọi `info()`. Trả null khi Zalo báo id không hợp lệ (`-109`) thay vì ném
     * — gọi lệnh với id gõ nhầm là chuyện thường.
     *
     * @return array<string, mixed>|null null khi OA không có template mang id này
     */
    public function template(string $templateId): ?array
    {
        try {
            $payload = $this->info($templateId)->payload();
        } catch (ApiException $e) {
            if ($e->errorCode === -109) {
                return null;
            }

            throw $e;
        }

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        return $payload;
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

    /**
     * Upload ảnh/logo để lấy `media_id` gắn vào layout khi tạo/sửa mẫu.
     *
     * Khác `$oa->uploads()->image()`: endpoint business, trả `media_id`,
     * tối đa 500 KB, chỉ JPG/PNG.
     *
     * @throws ConfigurationException khi file không hợp lệ hoặc thiếu media_id
     */
    public function uploadImage(string $path): string
    {
        $this->guardImage($path);

        $response = $this->request()
            ->postMultipart('/upload/image', ['file' => $path])
            ->throwIfFailed();

        $id = $response->get('data.media_id');

        if (! is_string($id) || $id === '') {
            throw new ConfigurationException(
                'Zalo không trả về media_id. Body: '.$response->raw
            );
        }

        return $id;
    }

    /**
     * Poll GET /message/status đến khi giao (1) hoặc tin không tồn tại (-1).
     *
     * `$sleep` nhận micro giây — mặc định `usleep`. Truyền no-op trong test.
     *
     * @param  (callable(int): mixed)|null  $sleep
     * @param  (callable(): int)|null  $now
     */
    public function waitForDelivery(
        string $messageId,
        int $timeoutSeconds = 60,
        int $intervalSeconds = 3,
        ?callable $sleep = null,
        ?callable $now = null,
    ): Response {
        $this->guardPoll($timeoutSeconds, $intervalSeconds);
        $sleep ??= static function (int $us): void {
            usleep($us);
        };
        $now ??= static fn (): int => time();
        $deadline = $now() + $timeoutSeconds;

        while (true) {
            $response = $this->status($messageId);
            $payload = (array) $response->payload();
            $status = isset($payload['status']) ? (int) $payload['status'] : null;

            if ($status === self::DELIVERY_DELIVERED || $status === self::DELIVERY_MISSING) {
                return $response;
            }

            if ($now() >= $deadline) {
                throw new ConfigurationException(
                    'Hết thời gian chờ giao tin. Zalo vẫn chưa đánh dấu đã giao hoặc không tồn tại.'
                );
            }

            $sleep($intervalSeconds * 1_000_000);
        }
    }

    /**
     * Poll GET /template/info/v2 đến khi status nằm trong `$statuses`.
     *
     * Webhook `change_template_status` là cách Zalo khuyến nghị; method này
     * cho CLI / script không nhận webhook.
     *
     * @param  list<string>  $statuses
     * @param  (callable(int): mixed)|null  $sleep
     * @param  (callable(): int)|null  $now
     */
    public function waitUntilStatus(
        string $templateId,
        array $statuses = ['ENABLE', 'REJECT'],
        int $timeoutSeconds = 300,
        int $intervalSeconds = 5,
        ?callable $sleep = null,
        ?callable $now = null,
    ): Response {
        $this->guardPoll($timeoutSeconds, $intervalSeconds);

        $wanted = array_values(array_filter(array_map(
            static fn (mixed $s): string => strtoupper(trim((string) $s)),
            $statuses,
        ), static fn (string $s): bool => $s !== ''));

        if ($wanted === []) {
            throw new ConfigurationException('Thiếu danh sách status cần chờ (ENABLE, REJECT, …).');
        }

        $sleep ??= static function (int $us): void {
            usleep($us);
        };
        $now ??= static fn (): int => time();
        $deadline = $now() + $timeoutSeconds;

        while (true) {
            $response = $this->info($templateId);
            $payload = (array) $response->payload();
            $status = strtoupper((string) ($payload['status'] ?? ''));

            if (in_array($status, $wanted, true)) {
                return $response;
            }

            if ($now() >= $deadline) {
                throw new ConfigurationException(sprintf(
                    'Hết thời gian chờ template `%s` (đang `%s`, cần %s).',
                    $templateId,
                    $status !== '' ? $status : '—',
                    implode('/', $wanted),
                ));
            }

            $sleep($intervalSeconds * 1_000_000);
        }
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function assertTemplatePayload(array $payload, bool $requireTracking): array
    {
        $name = trim((string) ($payload['template_name'] ?? ''));
        $length = mb_strlen($name);

        if ($length < 10 || $length > 60) {
            throw new ConfigurationException(
                'template_name phải từ 10 đến 60 ký tự.'
            );
        }

        $type = $this->intField($payload, 'template_type');

        if ($type < self::TYPE_CUSTOM || $type > self::TYPE_RATING) {
            throw new ConfigurationException(
                'template_type không hợp lệ. Zalo nhận 1–5 (tuỳ chỉnh, xác thực, thanh toán, voucher, đánh giá).'
            );
        }

        $tag = $this->intField($payload, 'tag');

        if ($tag < self::TAG_TRANSACTION || $tag > self::TAG_PROMOTION) {
            throw new ConfigurationException(
                'tag không hợp lệ. Zalo nhận 1 Transaction, 2 Customer care, 3 Promotion.'
            );
        }

        $layout = $payload['layout'] ?? null;

        if (! is_array($layout) || $layout === []) {
            throw new ConfigurationException('layout không được rỗng.');
        }

        if ($requireTracking && trim((string) ($payload['tracking_id'] ?? '')) === '') {
            throw new ConfigurationException('Thiếu tracking_id.');
        }

        // Chỉ gửi các trường docs Zalo nhận. Key lạ trong $payload bị bỏ —
        // thêm field mới ở đây chứ không spread $payload.
        $body = [
            'template_name' => $name,
            'template_type' => $type,
            'tag' => $tag,
            'layout' => $layout,
        ];

        if (isset($payload['params']) && is_array($payload['params']) && $payload['params'] !== []) {
            $body['params'] = $payload['params'];
        }

        $note = trim((string) ($payload['note'] ?? ''));

        if ($note !== '') {
            $body['note'] = $note;
        }

        $tracking = trim((string) ($payload['tracking_id'] ?? ''));

        if ($tracking !== '') {
            $body['tracking_id'] = $tracking;
        }

        return $body;
    }

    /** @param  array<string, mixed>  $payload */
    private function intField(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new ConfigurationException("Thiếu hoặc sai `{$key}`.");
    }

    private function guardPoll(int $timeoutSeconds, int $intervalSeconds): void
    {
        if ($timeoutSeconds < 1 || $intervalSeconds < 1) {
            throw new ConfigurationException('timeout và interval phải là số nguyên dương (giây).');
        }
    }

    /** @throws ConfigurationException */
    private function guardImage(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ConfigurationException("Không đọc được file ảnh: {$path}");
        }

        $size = filesize($path);

        if ($size === false || $size === 0) {
            throw new ConfigurationException("File ảnh rỗng hoặc không đọc được kích thước: {$path}");
        }

        if ($size > self::MAX_IMAGE_BYTES) {
            throw new ConfigurationException(sprintf(
                'Ảnh nặng %.0f KB, vượt giới hạn 500 KB của ZBS. Nén lại trước khi upload: %s',
                $size / 1024,
                $path,
            ));
        }

        $mime = $this->mimeOf($path);

        if ($mime !== null && ! in_array($mime, self::IMAGE_TYPES, true)) {
            throw new ConfigurationException(sprintf(
                'ZBS chỉ nhận JPG hoặc PNG, file này là %s: %s',
                $mime,
                $path,
            ));
        }
    }

    private function mimeOf(string $path): ?string
    {
        if (! function_exists('mime_content_type')) {
            return null;
        }

        $mime = @mime_content_type($path);

        return $mime === false ? null : $mime;
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
