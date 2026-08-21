<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Auth\TokenPair;

function pair(string $expires = '+1 hour', ?string $refreshExpires = '+90 days'): TokenPair
{
    $now = new DateTimeImmutable();

    return new TokenPair(
        accessToken: 'access',
        refreshToken: 'refresh',
        expiresAt: $now->modify($expires),
        refreshExpiresAt: $refreshExpires === null ? null : $now->modify($refreshExpires),
    );
}

it('phát hiện access token đã hết hạn', function (): void {
    expect(pair('-1 minute')->isExpired())->toBeTrue()
        ->and(pair('+1 hour')->isExpired())->toBeFalse();
});

it('phát hiện access token sắp hết hạn trong cửa sổ refresh', function (): void {
    expect(pair('+10 minutes')->expiresWithin(15))->toBeTrue()
        ->and(pair('+50 minutes')->expiresWithin(15))->toBeFalse();
});

it('yêu cầu xoay khi refresh token sắp hết hạn', function (): void {
    // Đây là bảo vệ quan trọng nhất: refresh token sống ~3 tháng và xoay vòng
    // mỗi lần dùng, app im lặng quá lâu là mất kết nối vĩnh viễn.
    expect(pair('+1 hour', '+10 days')->needsRotation(14))->toBeTrue()
        ->and(pair('+1 hour', '+80 days')->needsRotation(14))->toBeFalse();
});

it('không yêu cầu xoay khi không biết hạn refresh token', function (): void {
    expect(pair('+1 hour', null)->needsRotation(14))->toBeFalse();
});

it('phát hiện refresh token đã chết hẳn', function (): void {
    expect(pair('-2 hours', '-1 day')->refreshExpired())->toBeTrue()
        ->and(pair('+1 hour', '+1 day')->refreshExpired())->toBeFalse();
});

it('tính hạn từ expires_in của response Zalo', function (): void {
    $now = new DateTimeImmutable('2026-01-01 00:00:00');

    $tokens = TokenPair::fromResponse([
        'access_token'  => 'a',
        'refresh_token' => 'r',
        'expires_in'    => '3600',   // Zalo trả về dạng chuỗi
    ], $now);

    expect($tokens->expiresAt->format('H:i'))->toBe('01:00')
        ->and($tokens->refreshExpiresAt?->format('Y-m-d'))->toBe('2026-04-01');
});
