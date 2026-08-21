<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Messages;

/**
 * Message là value object, không phải mảng thô.
 *
 * Đây là thứ tạo khác biệt lớn nhất về cảm giác dùng: IDE gợi ý được, và
 * payload được validate trước khi bắn đi thay vì nhận lỗi khó hiểu từ Zalo.
 */
interface Message
{
    /** @return array<string, mixed> */
    public function toPayload(): array;
}
