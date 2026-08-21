<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Support\OAuthState;

it('sinh state đủ dài để không đoán được', function (): void {
    expect(strlen(OAuthState::issue(1)))->toBe(40);
});

it('trả về đúng OA khi state hợp lệ', function (): void {
    $state = OAuthState::issue(42);

    expect(OAuthState::consume($state))->toBe(42);
});

it('state chỉ dùng được MỘT lần', function (): void {
    // Chống replay: kẻ tấn công bắt được URL callback cũng không dùng lại được.
    $state = OAuthState::issue(42);

    expect(OAuthState::consume($state))->toBe(42)
        ->and(OAuthState::consume($state))->toBeNull();
});

it('từ chối state không tồn tại hoặc rỗng', function (): void {
    expect(OAuthState::consume('bia-dat-ra'))->toBeNull()
        ->and(OAuthState::consume(''))->toBeNull();
});

it('state của OA này không dùng cho OA khác', function (): void {
    $a = OAuthState::issue(1);
    $b = OAuthState::issue(2);

    expect(OAuthState::consume($a))->toBe(1)
        ->and(OAuthState::consume($b))->toBe(2);
});
