<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use FieldVn\Zalo\Core\Auth\TokenPair;

/**
 * Nơi lưu token của một OA.
 *
 * Bắt buộc phải bền vững: refresh_token XOAY VÒNG mỗi lần refresh, nên giá trị
 * mới phải ghi xuống được. Đây là lý do env một mình không đủ cho nhánh OA.
 */
interface TokenStore
{
    public function get(): ?TokenPair;

    public function put(TokenPair $tokens): void;

    public function forget(): void;

    /** Ghi lại lỗi refresh để scheduler biết khi nào nên ngắt kết nối OA. */
    public function recordFailure(string $message): void;

    public function clearFailures(): void;
}
