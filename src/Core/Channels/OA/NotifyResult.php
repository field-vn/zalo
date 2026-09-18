<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

/**
 * Kết quả một lần gửi qua OaNotifier.
 *
 * `ok=true` chỉ khi Zalo đã nhận tin (CS hoặc ZBS). `skipped` dùng channel
 * `none` kèm `reason` giải thích vì sao không gọi mạng.
 *
 * `errorCode` / `error` chỉ có khi thất bại do exception Zalo — skipped
 * (recipient_empty, token_stale, …) không có mã Open API.
 */
final class NotifyResult
{
    public const CHANNEL_OA_CS = 'oa_cs';

    public const CHANNEL_ZBS = 'zbs';

    public const CHANNEL_NONE = 'none';

    /**
     * @param  array<string, mixed>|null  $error
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $channel,
        public readonly mixed $messageId = null,
        public readonly ?string $reason = null,
        public readonly ?int $errorCode = null,
        public readonly ?array $error = null,
    ) {}

    public static function sent(string $channel, mixed $messageId): self
    {
        return new self(true, $channel, $messageId, null);
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    public static function failed(string $channel, string $reason, ?int $errorCode = null, ?array $error = null): self
    {
        return new self(false, $channel, null, $reason, $errorCode, $error);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, self::CHANNEL_NONE, null, $reason);
    }
}
