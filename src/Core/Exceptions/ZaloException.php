<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Exceptions;

use RuntimeException;

/**
 * Gốc của mọi exception trong package.
 * Người dùng bắt class này là bắt được tất cả.
 */
class ZaloException extends RuntimeException {}
