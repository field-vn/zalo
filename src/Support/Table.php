<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Support;

/**
 * Nguồn duy nhất cho tên bảng — dùng ở cả migration lẫn model.
 *
 * Model KHÔNG được hardcode $table; phải override getTable() gọi vào đây,
 * vì prefix chỉ biết được lúc runtime.
 */
final class Table
{
    public const OAS = 'oas';

    public const OA_TOKENS = 'oa_tokens';

    public const BOTS = 'bots';

    public const BOT_CHATS = 'bot_chats';

    public const AUDIT_LOGS = 'audit_logs';

    public const WEBHOOK_LOGS = 'webhook_logs';

    public const CONTACTS = 'contacts';

    /** Mọi bảng package quản lý, dùng cho doctor và uninstall. */
    public const ALL = [
        self::OAS,
        self::OA_TOKENS,
        self::BOTS,
        self::BOT_CHATS,
        self::AUDIT_LOGS,
        self::WEBHOOK_LOGS,
        self::CONTACTS,
    ];

    public static function prefix(): string
    {
        return (string) config('zalo.table_prefix', 'zl_');
    }

    public static function name(string $table): string
    {
        return self::prefix().$table;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_map(static fn (string $t): string => self::name($t), self::ALL);
    }
}
