<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Support;

use FieldVn\Zalo\Core\Exceptions\ConfigurationException;

/**
 * Chuẩn hoá số điện thoại về định dạng Zalo yêu cầu: 84987654321.
 *
 * Zalo chỉ nhận số đã chuẩn hoá theo mã quốc gia. Trong CSDL của phần lớn dự
 * án Việt Nam, số lại được lưu ở dạng 0987654321 — gửi thẳng lên là bị từ
 * chối, và thông báo lỗi không nói rõ vì sao.
 *
 * Đây là chỗ tốn tiền nếu để lọt: mỗi tin ZBS gửi hỏng vẫn có thể bị tính.
 */
final class PhoneNumber
{
    private const VN_CODE = '84';

    /**
     * Chấp nhận 0987654321, +84987654321, 84987654321, kể cả có dấu cách,
     * dấu chấm hoặc gạch ngang. Trả về 84987654321.
     *
     * @throws ConfigurationException khi không nhận ra được số hợp lệ
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        $digits = ltrim($digits, '+');

        if ($digits === '') {
            throw new ConfigurationException("Số điện thoại rỗng: `{$phone}`");
        }

        // 0987654321 -> 84987654321
        if (str_starts_with($digits, '0')) {
            $digits = self::VN_CODE.substr($digits, 1);
        }

        // 987654321 (thiếu cả số 0 lẫn mã vùng) -> 84987654321
        if (! str_starts_with($digits, self::VN_CODE) && strlen($digits) === 9) {
            $digits = self::VN_CODE.$digits;
        }

        if (! self::looksValid($digits)) {
            throw new ConfigurationException(sprintf(
                'Số điện thoại `%s` không hợp lệ. Zalo cần dạng 84987654321 '
                .'(hoặc 0987654321, +84987654321 — package tự chuyển đổi).',
                $phone,
            ));
        }

        return $digits;
    }

    /** Kiểm tra mà không ném exception — dùng khi lọc danh sách. */
    public static function isValid(string $phone): bool
    {
        try {
            self::normalize($phone);

            return true;
        } catch (ConfigurationException) {
            return false;
        }
    }

    /**
     * Số Việt Nam sau chuẩn hoá dài 11 hoặc 12 chữ số (84 + 9 hoặc 10).
     *
     * Cố ý KHÔNG kiểm đầu số nhà mạng: danh sách đó thay đổi theo thời gian,
     * và chặn oan một số thật thì tệ hơn để Zalo tự từ chối một số sai.
     */
    private static function looksValid(string $digits): bool
    {
        if (! ctype_digit($digits)) {
            return false;
        }

        if (! str_starts_with($digits, self::VN_CODE)) {
            // Số nước ngoài: chỉ kiểm độ dài thô, Zalo quyết định phần còn lại.
            return strlen($digits) >= 8 && strlen($digits) <= 15;
        }

        $length = strlen($digits);

        return $length === 11 || $length === 12;
    }
}
