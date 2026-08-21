<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use FieldVn\Zalo\Core\Http\Response;

/**
 * Zalo trả về lỗi ở tầng nghiệp vụ.
 *
 * Lưu ý: Zalo trả HTTP 200 kèm `error != 0` cho phần lớn lỗi, nên không thể
 * chỉ dựa vào status code.
 */
class ApiException extends ZaloException
{
    public function __construct(
        string $message,
        public readonly int $errorCode = 0,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    public static function fromResponse(Response $response): self
    {
        return new self(
            $response->errorMessage() ?: 'Zalo trả về lỗi không rõ nguyên nhân.',
            $response->errorCode(),
            $response,
        );
    }

    /** Token hết hạn hoặc bị thu hồi — cần refresh hoặc authorize lại. */
    public function isTokenError(): bool
    {
        return in_array($this->errorCode, [-216, -217, -32, -124], true);
    }
}
