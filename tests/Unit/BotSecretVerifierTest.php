<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;

/*
| Secret của Bot đi NGUYÊN VĂN trong header X-Bot-Api-Secret-Token, khác hẳn
| chữ ký của OA. Nó là mật khẩu, nên mọi nhánh "cho qua" đều là lỗ hổng.
*/

$valid = 'secret-du-dai-cho-zalo-32-ky-tu';

it('chấp nhận đúng secret', function () use ($valid): void {
    expect((new BotSecretVerifier($valid))->verify($valid))->toBeTrue();
});

it('từ chối khi thiếu header', function () use ($valid): void {
    $v = new BotSecretVerifier($valid);

    expect($v->verify(null))->toBeFalse()
        ->and($v->verify(''))->toBeFalse();
});

it('từ chối khi lệch dù chỉ một ký tự', function () use ($valid): void {
    expect((new BotSecretVerifier($valid))->verify($valid.'x'))->toBeFalse();
});

it('từ chối khi secret cấu hình rỗng, kể cả header cũng rỗng', function (): void {
    // Nhánh nguy hiểm nhất: '' === '' là true nếu so sánh ngây thơ, tức là
    // chưa cấu hình gì thì ai gửi request rỗng cũng qua được.
    expect((new BotSecretVerifier(''))->verify(''))->toBeFalse();
});

it('từ chối khi secret cấu hình ngắn hơn mức Zalo cho phép', function (): void {
    expect((new BotSecretVerifier('abc'))->verify('abc'))->toBeFalse();
});

it('kiểm độ dài theo đúng khoảng 8-256 của Zalo', function (): void {
    expect(BotSecretVerifier::isValidLength(str_repeat('a', 7)))->toBeFalse()
        ->and(BotSecretVerifier::isValidLength(str_repeat('a', 8)))->toBeTrue()
        ->and(BotSecretVerifier::isValidLength(str_repeat('a', 256)))->toBeTrue()
        ->and(BotSecretVerifier::isValidLength(str_repeat('a', 257)))->toBeFalse();
});

it('generate() luôn sinh ra secret hợp lệ', function (): void {
    expect(BotSecretVerifier::isValidLength(BotSecretVerifier::generate()))->toBeTrue();
});
