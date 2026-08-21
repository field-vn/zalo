<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Webhook;

/**
 * Xác thực header `X-ZEvent-Signature` của Zalo.
 *
 *     mac = sha256(appId + data + timestamp + OASecretKey)
 *
 * `data` PHẢI là chuỗi body thô đúng như Zalo gửi. Đây là chỗ sai phổ biến
 * nhất: decode JSON rồi encode lại sẽ đổi thứ tự khoá, khoảng trắng và cách
 * escape unicode — chữ ký lệch ngay dù nội dung y hệt.
 *
 * Lưu ý OASecretKey KHÁC app_secret: nó là "OA Secret Key" trong phần cài đặt
 * webhook của ứng dụng.
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly string $appId,
        private readonly string $secret,
        /** Cửa sổ chấp nhận timestamp, tính bằng giây. 0 = không kiểm tra. */
        private readonly int $tolerance = 300,
    ) {}

    public function verify(string $rawBody, string $timestamp, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->secret === '') {
            return false;
        }

        if (! $this->timestampFresh($timestamp)) {
            return false;
        }

        $expected = hash('sha256', $this->appId.$rawBody.$timestamp.$this->secret);

        // Zalo gửi dạng "mac=<hash>"; chấp nhận cả hash trần cho chắc.
        $received = str_starts_with($signature, 'mac=')
            ? substr($signature, 4)
            : $signature;

        // hash_equals chứ không phải === — chống timing attack.
        return hash_equals($expected, $received);
    }

    /**
     * Chống replay: chặn việc phát lại một request đã bị bắt được.
     *
     * Cửa sổ để rộng vì Zalo có thể gửi lại khi lần đầu thất bại. Chống trùng
     * ở tầng nghiệp vụ nên dựa vào `msg_id` chứ không dựa vào cái này.
     */
    private function timestampFresh(string $timestamp): bool
    {
        if ($this->tolerance <= 0) {
            return true;
        }

        if (! is_numeric($timestamp)) {
            return false;
        }

        // Zalo gửi timestamp mili giây.
        $sent = (int) round(((float) $timestamp) / 1000);

        return abs(time() - $sent) <= $this->tolerance;
    }
}
