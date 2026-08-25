# Changelog

Mọi thay đổi đáng chú ý của `field-vn/zalo` được ghi tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

> Trong giai đoạn 0.x, thay đổi phá vỡ tương thích có thể xuất hiện ở bản
> **minor** (`0.1` → `0.2`). Dùng `^0.1` nếu cần ổn định.

## [Unreleased]

## [0.2.0] — 2026-08-25

### Fixed

- **Webhook trả 200 thay vì 401 khi xác thực thất bại.** Zalo gửi một POST
  kiểm tra kết nối khi khai webhook URL và chỉ chấp nhận URL trả về 200.
  Request đó không mang chữ ký hợp lệ, nên 401 khiến webhook không bao giờ
  thiết lập được. Payload không xác thực vẫn KHÔNG được xử lý — không bắn
  event, không ghi DB — chỉ khác ở mã trạng thái trả về.
- Ghi log khi chữ ký hoặc secret không khớp. Trước đây nhánh này im lặng nên
  không chẩn đoán được từ phía server.

### Added

- Trang chi tiết OA hiển thị Redirect URI kèm nút copy và các bước khai báo
  bên Zalo Developers.

## [0.1.1] — 2026-08-25

### Changed

- Viết lại README theo giọng tài liệu sử dụng: bỏ phần ghi chú nội bộ, phần
  đánh giá mức độ hoàn thiện và các đoạn so sánh với nền tảng khác
- CHANGELOG, template issue/PR và CODE_OF_CONDUCT rút gọn tương ứng

Không có thay đổi nào về code so với 0.1.0.

## [0.1.0] — 2026-08-25

Bản phát hành đầu tiên.

### Official Account

- Quản lý nhiều OA qua `Zalo::oa()`, chọn theo slug hoặc id
- Tự refresh access token và xoay `refresh_token` trước khi hết hạn
- Luồng OAuth cấp quyền qua route callback hoặc `zalo:authorize`, hỗ trợ cả
  trường hợp callback không truy cập được từ Internet
- Message object: `TextMessage` kèm `Button`, `ImageMessage`, `RawMessage`
- `uploads()->image()` upload ảnh lấy `attachment_id`
- Webhook `/zalo/webhook`, xác thực `X-ZEvent-Signature` trên raw body, chống
  replay theo timestamp
- Events: `ZaloWebhookReceived`, `ZaloMessageReceived`, `ZaloFollowerAdded`,
  `ZaloFollowerRemoved`, `ZaloOaConnected`, `ZaloOaDisconnected`

### Bot

- Quản lý nhiều Bot qua `Zalo::bot()` với token tĩnh
- `text()`, `photo()`, `sticker()`, `typing()`
- Webhook riêng cho từng bot tại `/zalo/webhook/bot/{slug}`, xác thực secret ở
  header `X-Bot-Api-Secret-Token` bằng `hash_equals`
- Bảng `zl_bot_chats` tự lưu `chat_id` mỗi khi webhook về
- Events: `ZaloBotUpdateReceived`, `ZaloBotMessageReceived`

### Giao diện

- Giao diện quản lý tại `/zalo`, bảo vệ bằng basic auth và IP allowlist
- Trang chi tiết cho từng OA và Bot: sửa cấu hình, cắm webhook, xem `chat_id`,
  gửi tin thử
- Không cần build step, không cần `vendor:publish`

### Công cụ

- 14 artisan command, gồm `zalo:doctor` để chẩn đoán cấu hình
- `Zalo::fake()` cho test, chỉ thay tầng mạng
- Prefix bảng cấu hình qua `ZALO_TABLE_PREFIX`, mặc định `zl_`

### Ghi chú

- Hai method `transaction()` và `promotion()` trỏ tới endpoint có trước ngày
  01/01/2026, thời điểm Zalo hợp nhất chúng cùng ZNS thành ZBS Template
  Message. ZBS Template Message chưa được hỗ trợ.

[Unreleased]: https://github.com/field-vn/zalo/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/field-vn/zalo/releases/tag/v0.2.0
[0.1.1]: https://github.com/field-vn/zalo/releases/tag/v0.1.1
[0.1.0]: https://github.com/field-vn/zalo/releases/tag/v0.1.0
