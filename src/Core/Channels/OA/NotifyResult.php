<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

/**
 * Kết quả một lần gửi qua OaNotifier.
 *
 * `ok=true` chỉ khi Zalo đã nhận tin (CS hoặc ZBS). `skipped` dùng channel
 * `none` kèm `reason` giải thích vì sao không gọi mạng.
 */
final class NotifyResult
{
    public const CHANNEL_OA_CS = 'oa_cs';

    public const CHANNEL_ZBS = 'zbs';

    public const CHANNEL_NONE = 'none';

    public function __construct(
        public readonly bool $ok,
        public readonly string $channel,
        public readonly mixed $messageId = null,
        public readonly ?string $reason = null,
    ) {}

    public static function sent(string $channel, mixed $messageId): self
    {
        return new self(true, $channel, $messageId, null);
    }

    public static function failed(string $channel, string $reason): self
    {
        return new self(false, $channel, null, $reason);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, self::CHANNEL_NONE, null, $reason);
    }
}
