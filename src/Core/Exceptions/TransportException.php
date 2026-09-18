<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use FieldVn\Zalo\Core\Errors\ErrorCatalog;
use FieldVn\Zalo\Core\Errors\ErrorInfo;
use Throwable;

/** Lỗi ở tầng mạng — timeout, DNS, TLS, kết nối bị từ chối. */
class TransportException extends ZaloException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function source(): string
    {
        return 'transport';
    }

    public function explain(): string
    {
        return $this->getMessage();
    }

    public function info(): ErrorInfo
    {
        return new ErrorInfo(
            code: 0,
            message: $this->getMessage(),
            description: 'Không gọi được Zalo API (mạng, timeout, DNS, TLS).',
            hint: 'Kiểm tra kết nối tới openapi.zalo.me rồi thử lại.',
            category: 'transport',
            source: 'transport',
            docsUrl: ErrorCatalog::OA_DOCS,
        );
    }
}
