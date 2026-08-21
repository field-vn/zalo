<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use Throwable;

/** Lỗi ở tầng mạng — timeout, DNS, TLS, kết nối bị từ chối. */
class TransportException extends ZaloException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
