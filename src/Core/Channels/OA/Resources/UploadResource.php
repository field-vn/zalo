<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Http\Response;

/**
 * Upload media lên OA.
 *
 * Zalo KHÔNG cho gửi ảnh bằng URL như Bot: phải upload trước để lấy
 * `attachment_id`, rồi mới đính vào tin nhắn. Đây là khác biệt hay làm người
 * mới vấp, vì cùng một khái niệm "gửi ảnh" mà hai kênh làm hai kiểu.
 *
 *     $id = $oa->uploads()->image('/duong/dan/anh.jpg');
 *     $oa->messages()->image($userId, $id, 'Ảnh sản phẩm');
 *
 * attachment_id dùng lại được nhiều lần, nên gửi cùng một ảnh cho nhiều người
 * thì upload MỘT lần rồi lưu id lại.
 */
final class UploadResource extends Resource
{
    /** Zalo giới hạn 1MB cho ảnh gửi qua tin nhắn. */
    public const MAX_IMAGE_BYTES = 1024 * 1024;

    /** @var list<string> */
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * Upload ảnh, trả về `attachment_id`.
     *
     * Kiểm tra tại chỗ trước khi gọi mạng: lỗi của Zalo cho ca này rất khó
     * hiểu, còn file thiếu hay quá nặng thì biết ngay mà không tốn request.
     *
     * @throws ConfigurationException khi file không hợp lệ
     */
    public function image(string $path): string
    {
        $this->guardImage($path);

        $response = $this->request
            ->postMultipart('/v2.0/oa/upload/image', ['file' => $path])
            ->throwIfFailed();

        $id = $response->get('data.attachment_id');

        if (! is_string($id) || $id === '') {
            throw new ConfigurationException(
                'Zalo không trả về attachment_id. Body: '.$response->raw
            );
        }

        return $id;
    }

    /** Trả về nguyên Response cho ai cần đọc thêm trường khác. */
    public function imageRaw(string $path): Response
    {
        $this->guardImage($path);

        return $this->request
            ->postMultipart('/v2.0/oa/upload/image', ['file' => $path])
            ->throwIfFailed();
    }

    /** @throws ConfigurationException */
    private function guardImage(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ConfigurationException("Không đọc được file ảnh: {$path}");
        }

        $size = filesize($path);

        if ($size === false || $size === 0) {
            throw new ConfigurationException("File ảnh rỗng hoặc không đọc được kích thước: {$path}");
        }

        if ($size > self::MAX_IMAGE_BYTES) {
            throw new ConfigurationException(sprintf(
                'Ảnh nặng %.2f MB, vượt giới hạn 1 MB của Zalo. Nén lại trước khi gửi: %s',
                $size / 1024 / 1024,
                $path,
            ));
        }

        $mime = $this->mimeOf($path);

        if ($mime !== null && ! in_array($mime, self::IMAGE_TYPES, true)) {
            throw new ConfigurationException(sprintf(
                'Zalo chỉ nhận %s, file này là %s: %s',
                implode(', ', self::IMAGE_TYPES),
                $mime,
                $path,
            ));
        }
    }

    /** Trả null khi không xác định được — không chặn oan vì thiếu ext-fileinfo. */
    private function mimeOf(string $path): ?string
    {
        if (! function_exists('mime_content_type')) {
            return null;
        }

        $mime = @mime_content_type($path);

        return $mime === false ? null : $mime;
    }
}
