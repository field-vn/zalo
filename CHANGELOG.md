# Changelog

Mọi thay đổi đáng chú ý của `field-vn/zalo` được ghi tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

> Trong giai đoạn 0.x, thay đổi phá vỡ tương thích có thể xuất hiện ở bản
> **minor** (`0.1` → `0.2`). Dùng `^0.1` nếu cần ổn định.

## [Unreleased]

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

[Unreleased]: https://github.com/field-vn/zalo/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/field-vn/zalo/releases/tag/v0.1.0
