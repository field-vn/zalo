<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Support\ZbsPreviewUrl;

it('nhận https tới host Zalo', function (): void {
    expect(ZbsPreviewUrl::from('https://account.zalo.solutions/preview/abc'))
        ->toBe('https://account.zalo.solutions/preview/abc');
});

it('từ chối javascript và data scheme', function (): void {
    expect(ZbsPreviewUrl::from('javascript:alert(1)'))->toBeNull()
        ->and(ZbsPreviewUrl::from('data:text/html,<script>alert(1)</script>'))->toBeNull();
});

it('từ chối host không phải Zalo', function (): void {
    expect(ZbsPreviewUrl::from('https://evil.example/phish'))->toBeNull()
        ->and(ZbsPreviewUrl::from('https://zalo.solutions.evil.com/x'))->toBeNull();
});

it('từ chối URL rỗng hoặc không phải chuỗi', function (): void {
    expect(ZbsPreviewUrl::from(''))->toBeNull()
        ->and(ZbsPreviewUrl::from(null))->toBeNull()
        ->and(ZbsPreviewUrl::from(['https://zalo.me']))->toBeNull();
});
