<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Webhook;

/**
 * Xác thực webhook của Zalo Bot.
 *
 * Cơ chế KHÁC HẲN webhook của OA và không dùng chung code được:
 *
 *   OA  → Zalo tính chữ ký sha256(appId + rawBody + timestamp + OASecretKey)
 *         và gửi ở header X-ZEvent-Signature. Secret do Zalo cấp.
 *
 *   Bot → Zalo gửi lại NGUYÊN VĂN chuỗi secret bạn đã khai lúc setWebhook,
 *         ở header X-Bot-Api-Secret-Token. Secret do bạn tự đặt.
 *
 * Vì secret đi nguyên văn nên nó là mật khẩu chứ không phải chữ ký: bắt buộc
 * so sánh bằng hash_equals để không rò rỉ độ dài khớp qua thời gian phản hồi,
 * và bắt buộc HTTPS để không bị đọc trộm trên đường truyền.
 */
final class BotSecretVerifier
{
    /** Zalo quy định 8–256 ký tự. Ngắn hơn thì dò được, dài hơn thì Zalo từ chối. */
    public const MIN_LENGTH = 8;

    public const MAX_LENGTH = 256;

    public const HEADER = 'X-Bot-Api-Secret-Token';

    public function __construct(private readonly string $secret) {}

    /**
     * Fail-closed: thiếu secret ở cấu hình, thiếu header, hay lệch một ký tự
     * đều là từ chối. Không có nhánh nào "cho qua vì chưa cấu hình".
     */
    public function verify(?string $header): bool
    {
        if (! self::isValidLength($this->secret)) {
            return false;
        }

        if ($header === null || $header === '') {
            return false;
        }

        return hash_equals($this->secret, $header);
    }

    public static function isValidLength(string $secret): bool
    {
        $length = strlen($secret);

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    /** Chuỗi ngẫu nhiên hợp lệ để gợi ý cho người dùng. */
    public static function generate(): string
    {
        return bin2hex(random_bytes(24));
    }
}
