<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use FieldVn\Zalo\Core\Errors\ErrorCatalog;
use FieldVn\Zalo\Core\Errors\ErrorInfo;
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

    public function info(): ErrorInfo
    {
        return ErrorCatalog::lookup($this->errorCode, $this->getMessage(), $this->source());
    }

    public function source(): string
    {
        if ($this->response !== null && array_key_exists('ok', $this->response->all())) {
            return 'zalo_bot';
        }

        if (ErrorCatalog::has($this->errorCode)) {
            return ErrorCatalog::lookup($this->errorCode)->source;
        }

        return 'zalo_oa';
    }

    /**
     * Token hết hạn hoặc bị thu hồi — cần refresh hoặc authorize lại.
     *
     * Âm là mã OA/ZBS; 401 là của Bot API (quy ước HTTP).
     * -32 là rate limit, -217 là user chặn tin mời — không phải token.
     */
    public function isTokenError(): bool
    {
        return in_array($this->errorCode, [-216, -220, -124, 401], true);
    }
}
