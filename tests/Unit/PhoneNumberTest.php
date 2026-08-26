<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Support\PhoneNumber;

/*
| Zalo chỉ nhận số đã chuẩn hoá theo mã quốc gia (84987654321), trong khi CSDL
| của phần lớn dự án Việt Nam lưu dạng 0987654321. Đây là chỗ tốn tiền nếu để
| lọt: tin ZBS gửi hỏng vẫn có thể bị tính phí.
*/

it('chuẩn hoá mọi dạng thường gặp về 84987654321', function (string $input): void {
    expect(PhoneNumber::normalize($input))->toBe('84987654321');
})->with([
    'số 0 đầu' => '0987654321',
    'có dấu +' => '+84987654321',
    'đã chuẩn' => '84987654321',
    'thiếu cả 0 lẫn mã vùng' => '987654321',
    'có dấu cách' => '098 765 4321',
    'có gạch ngang' => '098-765-4321',
    'có dấu chấm' => '098.765.4321',
    '+ kèm dấu cách' => '+84 987 654 321',
]);

it('giữ nguyên số 10 chữ số sau mã vùng', function (): void {
    // Đầu số cũ 11 chữ số vẫn tồn tại trong dữ liệu cũ của nhiều dự án.
    expect(PhoneNumber::normalize('01234567890'))->toBe('841234567890');
});

it('từ chối số rõ ràng không hợp lệ', function (string $input): void {
    expect(fn () => PhoneNumber::normalize($input))->toThrow(ConfigurationException::class);
})->with([
    'rỗng' => '',
    'chỉ khoảng trắng' => '   ',
    'quá ngắn' => '0123',
    'quá dài' => '0987654321987654321',
    'toàn chữ' => 'khong-phai-so',
]);

it('isValid không ném exception', function (): void {
    expect(PhoneNumber::isValid('0987654321'))->toBeTrue()
        ->and(PhoneNumber::isValid('sai'))->toBeFalse();
});

it('KHÔNG chặn theo đầu số nhà mạng', function (): void {
    // Danh sách đầu số thay đổi theo thời gian. Chặn oan một số thật tệ hơn
    // là để Zalo tự từ chối một số sai.
    expect(PhoneNumber::normalize('0999999999'))->toBe('84999999999');
});

it('thông báo lỗi chỉ ra định dạng đúng', function (): void {
    expect(fn () => PhoneNumber::normalize('abc'))
        ->toThrow(ConfigurationException::class, '84987654321');
});
