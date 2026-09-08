<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Errors;

/**
 * Mã lỗi Zalo đã chuẩn hoá cho bên thứ 3.
 *
 * `message` là nguyên văn Zalo (hoặc thông báo package). `description` / `hint`
 * là tiếng Việt từ catalog — thứ người gọi API cần để biết phải làm gì.
 */
final readonly class ErrorInfo
{
    public function __construct(
        public int $code,
        public string $message,
        public string $description,
        public string $hint,
        public string $category,
        public string $source,
        public string $docsUrl,
    ) {}

    /**
     * @return array{
     *     ok: false,
     *     source: string,
     *     code: int,
     *     message: string,
     *     description: string,
     *     hint: string,
     *     category: string,
     *     docs: string
     * }
     */
    public function toArray(): array
    {
        return [
            'ok' => false,
            'source' => $this->source,
            'code' => $this->code,
            'message' => $this->message,
            'description' => $this->description,
            'hint' => $this->hint,
            'category' => $this->category,
            'docs' => $this->docsUrl,
        ];
    }
}
