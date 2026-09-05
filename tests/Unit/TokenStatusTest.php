<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Auth\TokenPair;
use FieldVn\Zalo\Laravel\Support\TokenStatus;

it('counts remaining minutes from expiresAt', function () {
    $now = new DateTimeImmutable('2026-09-05 12:00:00');
    $pair = new TokenPair('a', 'r', $now->modify('+90 minutes'), $now->modify('+90 days'));
    $status = TokenStatus::fromPair($pair, $now);
    expect($status->present)->toBeTrue()
        ->and($status->remainingMinutes)->toBe(90)
        ->and($status->isFresh(60))->toBeTrue()
        ->and($status->isFresh(1440))->toBeFalse();
});

it('missing is never fresh', function () {
    expect(TokenStatus::missing()->isFresh(0))->toBeFalse()
        ->and(TokenStatus::missing()->isExpired())->toBeTrue();
});
