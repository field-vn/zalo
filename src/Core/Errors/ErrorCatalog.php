<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Errors;

/**
 * Tra mã lỗi Open API Zalo → mô tả tiếng Việt + hướng xử lý.
 *
 * Nguồn OA: https://developers.zalo.me/docs/official-account/phu-luc/ma-loi
 * ZBS/Bot/OAuth bổ sung các mã package đã gặp trên sản phẩm.
 */
final class ErrorCatalog
{
    public const OA_DOCS = 'https://developers.zalo.me/docs/official-account/phu-luc/ma-loi';

    public const ZBS_DOCS = 'https://developers.zalo.me/docs/zbs-template-message/bang-ma-loi';

    /**
     * @var array<int, array{source: string, category: string, description: string, hint: string, docs: string}>
     */
    private const ENTRIES = [
        0 => ['source' => 'zalo_oa', 'category' => 'success', 'description' => 'Request thành công.', 'hint' => '', 'docs' => self::OA_DOCS],

        -32 => ['source' => 'zalo_oa', 'category' => 'rate_limit', 'description' => 'Vượt quá giới hạn tốc độ request/phút (app hoặc OA).', 'hint' => 'Giảm tốc độ gọi API và thử lại sau.', 'docs' => self::OA_DOCS],
        -100 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'attachment_id đã hết hạn.', 'hint' => 'Upload lại tệp/ảnh để lấy attachment_id mới.', 'docs' => self::OA_DOCS],
        -200 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'Gửi tin nhắn thất bại.', 'hint' => 'Thử lại. Nếu lặp lại, kiểm tra payload và trạng thái OA.', 'docs' => self::OA_DOCS],
        -201 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Tham số không hợp lệ.', 'hint' => 'Sai user_id, hoặc người này chưa từng tương tác với OA. Kiểm tra tham số request.', 'docs' => self::OA_DOCS],
        -204 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'Official Account đã bị xóa hoặc vô hiệu hóa.', 'hint' => 'Kiểm tra trạng thái tài khoản OA trên Zalo.', 'docs' => self::OA_DOCS],
        -205 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'Official Account không tồn tại.', 'hint' => 'Kiểm tra OA ID trong request và trong cấu hình package.', 'docs' => self::OA_DOCS],
        -209 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'API chưa được hỗ trợ vì ứng dụng chưa kích hoạt.', 'hint' => 'Bật ứng dụng tại Quản lý ứng dụng → Cài đặt → Đang hoạt động.', 'docs' => self::OA_DOCS],
        -210 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Tham số vượt quá giới hạn cho phép.', 'hint' => 'Rút ngắn nội dung / giảm kích thước payload theo giới hạn API.', 'docs' => self::OA_DOCS],
        -211 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'Vượt quá quota sử dụng của tính năng.', 'hint' => 'Kiểm tra hạn mức tính năng và chờ reset quota.', 'docs' => self::OA_DOCS],
        -212 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'OA chưa đăng ký API này.', 'hint' => 'Đăng ký Official Account API tại Quản lý ứng dụng → Đăng ký sử dụng API.', 'docs' => self::OA_DOCS],
        -213 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng chưa quan tâm Official Account.', 'hint' => 'Chỉ gửi khi user đã follow, hoặc dùng ZBS Template Message.', 'docs' => self::OA_DOCS],
        -214 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'Bài viết đang được xử lý.', 'hint' => 'Chờ rồi gọi lại API sau vài phút.', 'docs' => self::OA_DOCS],
        -216 => ['source' => 'zalo_oa', 'category' => 'token', 'description' => 'Access token không hợp lệ.', 'hint' => 'Token hết hạn hoặc bị thu hồi — cấp lại quyền cho OA.', 'docs' => self::OA_DOCS],
        -217 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng đã chặn tin mời quan tâm từ OA.', 'hint' => 'Không gửi invitation/follow request tới user này nữa.', 'docs' => self::OA_DOCS],
        -218 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'Đã quá giới hạn gửi đến người dùng này.', 'hint' => 'Giảm tần suất gửi tới user; xem hạn mức nhận tin của OA.', 'docs' => self::OA_DOCS],
        -219 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'Ứng dụng đã bị gỡ bỏ hoặc vô hiệu hóa.', 'hint' => 'Kiểm tra quyền admin và trạng thái App trên Zalo Developers.', 'docs' => self::OA_DOCS],
        -220 => ['source' => 'zalo_oa', 'category' => 'token', 'description' => 'access_token đã hết hạn hoặc không còn khả dụng.', 'hint' => 'Chờ refresh token hoặc cấp lại quyền cho OA.', 'docs' => self::OA_DOCS],
        -221 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'OA chưa xác thực, chưa dùng được tính năng này.', 'hint' => 'Nộp hồ sơ xác thực OA trên trang quản trị Zalo.', 'docs' => self::OA_DOCS],
        -223 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'OA chưa cấp quyền API, hoặc đã hết hạn mức xuất bản nội dung.', 'hint' => 'Cấp quyền cho app tại developers.zalo.me/app/{AppID}/oa/settings, hoặc kiểm tra hạn mức bài viết.', 'docs' => self::OA_DOCS],
        -224 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'OA chưa mua gói dịch vụ cho tính năng này.', 'hint' => 'Nâng cấp gói dịch vụ OA trên Zalo.', 'docs' => self::OA_DOCS],
        -227 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Tài khoản người dùng bị khóa hoặc không online hơn 45 ngày.', 'hint' => 'Không gửi được tới user này cho đến khi tài khoản hoạt động lại.', 'docs' => self::OA_DOCS],
        -230 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng không tương tác với OA trong 7 ngày qua.', 'hint' => 'Người này không có tương tác với OA trong 7 ngày qua, nên OpenAPI không gửi tin Tư vấn được nữa. Dùng ZBS Template Message, hoặc chờ họ nhắn lại.', 'docs' => self::OA_DOCS],
        -231 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng không tương tác với OA trong cửa sổ tin Tư vấn.', 'hint' => 'Dùng ZBS Template Message, hoặc chờ họ tương tác lại.', 'docs' => self::OA_DOCS],
        -232 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng chưa phát sinh tương tác hoặc tương tác cuối đã hết hạn.', 'hint' => 'Chờ user nhắn OA, hoặc gửi qua ZBS.', 'docs' => self::OA_DOCS],
        -233 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Loại tin nhắn không được hỗ trợ hoặc không khả dụng.', 'hint' => 'Đổi loại tin (CS / transaction / promotion) cho đúng API v3.', 'docs' => self::OA_DOCS],
        -234 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'Loại tin này không gửi được từ 22h đến 6h sáng.', 'hint' => 'Gửi lại trong khung giờ cho phép, hoặc dùng mẫu ZBS phù hợp.', 'docs' => self::OA_DOCS],
        -235 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'API không hỗ trợ phân loại OA của bạn.', 'hint' => 'Kiểm tra loại hình OA và điều kiện sử dụng API.', 'docs' => self::OA_DOCS],
        -237 => ['source' => 'zalo_oa', 'category' => 'oa_state', 'description' => 'Nhóm chat GMF đã hết hạn.', 'hint' => 'Gia hạn dịch vụ nhóm chat rồi thử lại.', 'docs' => self::OA_DOCS],
        -238 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'asset_id đã được sử dụng hoặc không còn khả dụng.', 'hint' => 'Chọn asset_id khác còn hiệu lực.', 'docs' => self::OA_DOCS],
        -240 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Message API v2 đã tắt.', 'hint' => 'Chuyển sang Message API v3: https://go.zalo.me/api-v3', 'docs' => self::OA_DOCS],
        -241 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'asset_id miễn phí trong gói đã được sử dụng.', 'hint' => 'Chọn asset_id khác hoặc nâng gói OA.', 'docs' => self::OA_DOCS],
        -242 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'appsecret_proof không hợp lệ.', 'hint' => 'Kiểm tra cách tạo appsecret_proof theo tài liệu Zalo.', 'docs' => self::OA_DOCS],
        -244 => ['source' => 'zalo_oa', 'category' => 'recipient', 'description' => 'Người dùng đã hạn chế nhận loại tin này từ OA.', 'hint' => 'Không gửi loại tin đó tới user này.', 'docs' => self::OA_DOCS],
        -248 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'Vi phạm tiêu chuẩn nền tảng Zalo.', 'hint' => 'Xem https://go.zalo.me/oa-policy và chỉnh nội dung tin.', 'docs' => self::OA_DOCS],
        -249 => ['source' => 'zalo_oa', 'category' => 'zbs', 'description' => 'Template không hỗ trợ gửi qua UID.', 'hint' => 'Dùng template tùy chỉnh / đánh giá / thanh toán / voucher, hoặc clone template mới sau 10/12/2025.', 'docs' => self::OA_DOCS],
        -320 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'App cần kết nối Zalo Cloud Account để dùng tính năng trả phí.', 'hint' => 'Liên kết tài khoản ZCA với App.', 'docs' => self::OA_DOCS],
        -321 => ['source' => 'zalo_oa', 'category' => 'quota', 'description' => 'Zalo Cloud Account hết tiền hoặc không trừ được phí.', 'hint' => 'Nạp tiền ZCA rồi thử lại.', 'docs' => self::OA_DOCS],
        -403 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'Không tương tác được với nhóm vì OA không sở hữu nhóm.', 'hint' => 'Gửi tới nhóm do OA sở hữu.', 'docs' => self::OA_DOCS],
        -1340 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Không tìm thấy Form.', 'hint' => 'Kiểm tra form id.', 'docs' => self::OA_DOCS],
        -1341 => ['source' => 'zalo_oa', 'category' => 'permission', 'description' => 'OA không có quyền truy cập form này.', 'hint' => 'Dùng form thuộc OA đang cầm token.', 'docs' => self::OA_DOCS],

        -105 => ['source' => 'zalo_zbs', 'category' => 'zbs', 'description' => 'App chưa liên kết với OA nào.', 'hint' => 'Liên kết App với OA trên Zalo Developers.', 'docs' => self::ZBS_DOCS],
        -108 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Số điện thoại không hợp lệ hoặc chưa đăng ký Zalo.', 'hint' => 'Chuẩn hoá SĐT Việt Nam (84…) và kiểm tra user đã có Zalo.', 'docs' => self::ZBS_DOCS],
        -109 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'ID của template không hợp lệ.', 'hint' => 'Kiểm tra template_id — lấy từ $oa->zbs()->templates().', 'docs' => self::ZBS_DOCS],
        -1091 => ['source' => 'zalo_zbs', 'category' => 'zbs', 'description' => 'Không sửa được template này (không phải Reject, hoặc tạo từ Admin tool).', 'hint' => 'Chỉ sửa mẫu bị từ chối do App tạo. Xem API chỉnh sửa template.', 'docs' => self::ZBS_DOCS],
        -110 => ['source' => 'zalo_zbs', 'category' => 'recipient', 'description' => 'Phiên bản Zalo của người nhận không hỗ trợ loại tin này.', 'hint' => 'Người dùng cần cập nhật Zalo; thử kênh khác nếu gấp.', 'docs' => self::ZBS_DOCS],
        -111 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Template không có dữ liệu (template_data rỗng).', 'hint' => 'Điền template_data khớp tham số mẫu.', 'docs' => self::ZBS_DOCS],
        -112 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Kiểu dữ liệu template chưa được định nghĩa.', 'hint' => 'Dùng data type Zalo đã khai trong tài liệu template.', 'docs' => self::ZBS_DOCS],
        -113 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Button trên template không hợp lệ.', 'hint' => 'Kiểm tra CTA/button và đường dẫn liên kết.', 'docs' => self::ZBS_DOCS],
        -1132 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Template phải có tối thiểu một CTA/button.', 'hint' => 'Thêm ít nhất một button khi tạo mẫu.', 'docs' => self::ZBS_DOCS],
        -114 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Nội dung button không hợp lệ.', 'hint' => 'Kiểm tra URL và loại nút thao tác trên mẫu.', 'docs' => self::ZBS_DOCS],
        -115 => ['source' => 'zalo_zbs', 'category' => 'quota', 'description' => 'Số dư ZBS không đủ.', 'hint' => 'Nạp tiền tài khoản ZBS tại zalo.solutions — số dư không đủ.', 'docs' => self::ZBS_DOCS],
        -118 => ['source' => 'zalo_zbs', 'category' => 'recipient', 'description' => 'Số này chưa có tài khoản Zalo, hoặc đã vô hiệu hoá trên 30 ngày.', 'hint' => 'Đổi số nhận, hoặc chờ tài khoản Zalo hoạt động lại.', 'docs' => self::ZBS_DOCS],
        -117 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'OA hoặc App không có quyền dùng template này.', 'hint' => 'Dùng mẫu thuộc OA đang cầm token, hoặc cấp quyền template cho App.', 'docs' => self::ZBS_DOCS],
        -120 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'OA hoặc App chưa được cấp quyền dùng ZBS.', 'hint' => 'Đăng ký ZBS tại zalo.solutions và liên kết với App.', 'docs' => self::ZBS_DOCS],
        -124 => ['source' => 'zalo_zbs', 'category' => 'token', 'description' => 'Token OA hết hạn khi gọi ZBS.', 'hint' => 'Cấp lại quyền cho OA.', 'docs' => self::ZBS_DOCS],
        -126 => ['source' => 'zalo_zbs', 'category' => 'quota', 'description' => 'Ví development đã hết số dư.', 'hint' => 'Nạp ví development hoặc chuyển sang production khi đã sẵn sàng.', 'docs' => self::ZBS_DOCS],
        -127 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'Chế độ development chỉ gửi được tới quản trị viên OA/App.', 'hint' => 'Ở chế độ development, số nhận PHẢI là quản trị viên của OA hoặc của App đang giữ token.', 'docs' => self::ZBS_DOCS],
        -131 => ['source' => 'zalo_zbs', 'category' => 'zbs', 'description' => 'Mẫu chưa được phê duyệt.', 'hint' => 'Chờ Zalo duyệt mẫu, hoặc gửi development tới admin.', 'docs' => self::ZBS_DOCS],
        -132 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Tham số không hợp lệ.', 'hint' => 'Kiểm tra status (số 1–5), template_id, mode, hoặc payload gửi tin.', 'docs' => self::ZBS_DOCS],
        -133 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'Zalo không gửi mẫu này trong khung 22h–6h.', 'hint' => 'Gửi lại sau 6h sáng (UTC+7).', 'docs' => self::ZBS_DOCS],
        -135 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'OA hoặc App chưa được cấp quyền dùng ZBS.', 'hint' => 'Đăng ký tài khoản ZBS tại zalo.solutions và liên kết với App.', 'docs' => self::ZBS_DOCS],
        -138 => ['source' => 'zalo_zbs', 'category' => 'permission', 'description' => 'OA hoặc App chưa được cấp quyền dùng ZBS.', 'hint' => 'Đăng ký tài khoản ZBS tại zalo.solutions và liên kết với App.', 'docs' => self::ZBS_DOCS],
        -139 => ['source' => 'zalo_zbs', 'category' => 'recipient', 'description' => 'Người dùng từ chối nhận loại tin ZBS này.', 'hint' => 'Không gửi lại loại template đó tới user/SĐT này.', 'docs' => self::ZBS_DOCS],
        -1122 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Thiếu tham số bắt buộc của mẫu.', 'hint' => 'Điền đủ mọi ô mà mẫu yêu cầu.', 'docs' => self::ZBS_DOCS],
        -1124 => ['source' => 'zalo_zbs', 'category' => 'validation', 'description' => 'Một tham số sai định dạng.', 'hint' => 'Khớp đúng kiểu dữ liệu trong cột Cài đặt kỹ thuật của mẫu.', 'docs' => self::ZBS_DOCS],
        -14003 => ['source' => 'zalo_oa', 'category' => 'validation', 'description' => 'Redirect URI không hợp lệ hoặc domain chưa xác thực.', 'hint' => 'Xác thực domain trên Zalo Developers và khớp chính xác callback URL (kể cả dấu / cuối).', 'docs' => self::OA_DOCS],

        400 => ['source' => 'zalo_bot', 'category' => 'validation', 'description' => 'Bot API từ chối request (thường sai chat_id hoặc đang cắm webhook khi gọi getUpdates).', 'hint' => 'Kiểm tra chat_id; getUpdates và webhook loại trừ nhau.', 'docs' => self::OA_DOCS],
        401 => ['source' => 'zalo_bot', 'category' => 'token', 'description' => 'Token bot không hợp lệ hoặc bị thu hồi.', 'hint' => 'Lấy token mới trong Zalo Bot Studio.', 'docs' => self::OA_DOCS],
        404 => ['source' => 'zalo_bot', 'category' => 'validation', 'description' => 'Bot API không tìm thấy tài nguyên (chat/endpoint).', 'hint' => 'Kiểm tra chat_id và đường dẫn Bot API.', 'docs' => self::OA_DOCS],
    ];

    /**
     * @var list<int>
     */
    public const OA_DOC_CODES = [
        0, -32, -100, -200, -201, -204, -205, -209, -210, -211, -212, -213, -214,
        -216, -217, -218, -219, -220, -221, -223, -224, -227, -230, -231, -232, -233,
        -234, -235, -237, -238, -240, -241, -242, -244, -248, -249, -320, -321,
        -403, -1340, -1341,
    ];

    /**
     * @var list<int>
     */
    public const ZBS_DOC_CODES = [
        -105, -108, -109, -1091, -110, -111, -112, -113, -1132, -114, -115, -117,
        -118, -120, -124, -126, -127, -131, -132, -133, -135, -138, -139, -1122, -1124,
    ];

    public static function lookup(int $code, string $zaloMessage = '', ?string $sourceOverride = null): ErrorInfo
    {
        $row = self::ENTRIES[$code] ?? null;

        if ($row === null) {
            return new ErrorInfo(
                code: $code,
                message: $zaloMessage !== '' ? $zaloMessage : 'Zalo trả về lỗi không rõ nguyên nhân.',
                description: 'Mã lỗi chưa có trong catalog của package.',
                hint: 'Tra bảng mã Official Account API hoặc ZBS rồi xử lý theo mã số, không theo câu chữ tiếng Anh (câu chữ có thể đổi).',
                category: 'unknown',
                source: $sourceOverride ?? 'zalo_oa',
                docsUrl: self::OA_DOCS,
            );
        }

        return new ErrorInfo(
            code: $code,
            message: $zaloMessage !== '' ? $zaloMessage : $row['description'],
            description: $row['description'],
            hint: $row['hint'],
            category: $row['category'],
            source: $sourceOverride ?? $row['source'],
            docsUrl: $row['docs'],
        );
    }

    public static function has(int $code): bool
    {
        return array_key_exists($code, self::ENTRIES);
    }

    public static function httpStatusFor(string $category): int
    {
        return match ($category) {
            'token' => 401,
            'rate_limit' => 429,
            'validation' => 422,
            'transport' => 503,
            'config' => 422,
            default => 400,
        };
    }
}
