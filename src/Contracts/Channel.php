<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

/**
 * Điểm chung duy nhất giữa OA và Bot.
 *
 * Cố tình KHÔNG có send() ở đây: OA và Bot khác nhau từ tầng xác thực tới
 * shape của message, nên không có gì để trừu tượng hoá một cách trung thực.
 * Xem ADR-0001.
 */
interface Channel
{
    /** Định danh kênh — slug của OA hoặc Bot. */
    public function key(): string;

    /** Gọi thử một endpoint rẻ tiền để xác nhận kết nối còn sống. */
    public function ping(): bool;
}
