<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA;

/**
 * Nội dung gửi qua OaNotifier — text CS và/hoặc mẫu ZBS.
 */
final class ZaloOutboundMessage
{
    /**
     * @param  array<string, string|int>  $templateData
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly ?string $templateId = null,
        public readonly array $templateData = [],
    ) {}
}
