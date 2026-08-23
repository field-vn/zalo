<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Contracts;

use FieldVn\Zalo\Core\Http\Response;

/**
 * Tầng HTTP. Bind interface này để thay thế client mà không phải fork package.
 */
interface Transport
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $query = [], array $headers = []): Response;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function post(string $url, array $payload = [], array $headers = []): Response;

    /**
     * Gửi dạng application/x-www-form-urlencoded — luồng OAuth của Zalo yêu cầu.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, string>  $headers
     */
    public function postForm(string $url, array $form = [], array $headers = []): Response;

    /**
     * Gửi multipart/form-data — upload ảnh/file lên OA yêu cầu dạng này.
     *
     * `$files` là map tên trường => đường dẫn file trên đĩa. Tách khỏi `$form`
     * để transport tự lo việc mở stream và đóng lại, thay vì bắt caller
     * truyền resource rồi tự nhớ fclose.
     *
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $form
     * @param  array<string, string>  $headers
     */
    public function postMultipart(string $url, array $files, array $form = [], array $headers = []): Response;
}
