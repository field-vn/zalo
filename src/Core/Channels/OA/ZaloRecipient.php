<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

/**
 * Người nhận cho OaNotifier — user id OA và/hoặc SĐT ZBS.
 *
 * Không suy SĐT từ user id và ngược lại; app phải truyền đủ kênh muốn dùng.
 */
final class ZaloRecipient
{
    public function __construct(
        public readonly ?string $zaloUserId = null,
        public readonly ?string $phone = null,
    ) {}
}
