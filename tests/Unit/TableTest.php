<?php

declare(strict_types=1);

use FieldVn\Zalo\Support\Table;

it('dùng prefix zl_ mặc định', function (): void {
    expect(Table::name('oas'))->toBe('zl_oas');
});

it('đọc prefix từ config lúc runtime, không cache lại', function (): void {
    config()->set('zalo.table_prefix', 'khac_');

    expect(Table::name('oas'))->toBe('khac_oas')
        ->and(Table::prefix())->toBe('khac_');
});

it('liệt kê mọi bảng package quản lý', function (): void {
    expect(Table::all())->toBe([
        'zl_oas',
        'zl_oa_tokens',
        'zl_bots',
        'zl_bot_chats',
        'zl_audit_logs',
        'zl_webhook_logs',
        'zl_contacts',
    ]);
});
