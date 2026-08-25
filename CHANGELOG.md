# Changelog

Mọi thay đổi đáng chú ý của `field-vn/zalo` được ghi tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

> **Trong giai đoạn 0.x**, thay đổi phá vỡ tương thích có thể xuất hiện ở bản
> **minor** (`0.1` → `0.2`). Ghim `^0.1` chứ đừng ghim `^0` nếu bạn cần ổn định.

## [Unreleased]

Chưa có thay đổi nào sau v0.1.0.

## [0.1.0] — 2026-08-25

Bản phát hành đầu tiên.

### Official Account

- Quản lý nhiều OA qua `Zalo::oa()`, chọn theo slug hoặc id
- Token tự refresh, kể cả **xoay `refresh_token`** trước khi nó hết hạn —
  đây là thứ khiến hầu hết tích hợp Zalo tự viết chết sau ba tháng, im lặng
- Luồng OAuth cấp quyền: route callback và `zalo:authorize`, chạy được cả khi
  callback không truy cập được từ Internet
- Message object: `TextMessage` (kèm `Button`), `ImageMessage`, `RawMessage`
- `uploads()->image()` lấy `attachment_id` — OA không nhận URL ảnh như Bot
- `RawMessage` làm lối thoát cho dạng tin package chưa xác minh được payload
- Webhook `/zalo/webhook`, xác thực `X-ZEvent-Signature` trên **raw body**,
  chống replay theo timestamp, fail-closed khi thiếu secret
- Events: `ZaloWebhookReceived`, `ZaloMessageReceived`, `ZaloFollowerAdded`,
  `ZaloFollowerRemoved`, `ZaloOaConnected`, `ZaloOaDisconnected`

### Bot

- Quản lý nhiều Bot qua `Zalo::bot()`, token tĩnh, không dính Zalo App
- Đường tắt `text()`, `photo()`, `sticker()`, `typing()` ngay trên channel.
  Bot **cố ý không có message object** — nó không có nút, không có list,
  không có gì để kết hợp, nên thêm một lớp object vào chỉ là nghi thức thừa
- Webhook riêng `/zalo/webhook/bot/{slug}`, xác thực secret nguyên văn ở
  header `X-Bot-Api-Secret-Token` bằng `hash_equals`, fail-closed.
  Mỗi bot một URL vì payload Zalo gửi không kèm định danh bot nào
- Bảng `zl_bot_chats` **tự lưu `chat_id`** mỗi lần webhook về — Zalo không có
  API nào liệt kê, và `getUpdates` chỉ đọc được một lần rồi mất
- Events: `ZaloBotUpdateReceived`, `ZaloBotMessageReceived`

### Giao diện

- UI ở `/zalo`, bảo vệ bằng basic auth + IP allowlist, fail-closed
- Trang chi tiết cho từng OA và từng Bot: sửa cấu hình, cắm/gỡ webhook,
  xem `chat_id`, gửi tin thử
- Sinh sẵn `ZALO_BOT_WEBHOOK_SECRET` hợp lệ để copy khi chưa có
- Token và secret **không bao giờ** hiện đầy đủ, kể cả với người đã đăng nhập
- Không build step, không cần `vendor:publish`: CSS nhúng thẳng vào layout

### Công cụ

- Commands: `zalo`, `zalo:install`, `zalo:doctor`, `zalo:oa:add`,
  `zalo:oa:list`, `zalo:oa:test`, `zalo:authorize`, `zalo:token:refresh`,
  `zalo:bot:add`, `zalo:bot:list`, `zalo:bot:test`, `zalo:bot:webhook`,
  `zalo:bot:chats`, `zalo:bot:send`
- `Zalo::fake()` cho test của dự án — không cần OA trong DB hay token.
  Chỉ thay tầng mạng, nên message builder và validate payload vẫn chạy thật
- Prefix bảng cấu hình được qua `ZALO_TABLE_PREFIX` (mặc định `zl_`)

### Đã sửa trong quá trình xác minh với Zalo thật

Những lỗi dưới đây từng tồn tại và đã được sửa trước khi phát hành. Ghi lại
vì chúng cho thấy chỗ nào của Zalo API dễ hiểu sai:

- **URL Bot API**: đúng là `bot-api.zapps.me/bot<token>/`, token **dính liền**
  chữ `bot`. Thêm một dấu `/` vào giữa thì Zalo trả HTTP 200 kèm
  `{"ok":false,"description":"Not Found"}` — mọi lời gọi Bot đều hỏng mà nhìn
  bên ngoài như thành công
- **Hình dạng lỗi**: OA dùng `{"error": 0}`, Bot dùng `{"ok": true}`. Chỉ xét
  `error` thì mọi lỗi của Bot lọt qua thành thành công
- **Vị trí kết quả**: OA bọc trong `data`, Bot bọc trong `result`
- **`getMe` của Bot** trả `account_name`, không có trường `username`
- **`setWebhook` của Bot bắt buộc `secret_token`** dài 8–256 ký tự, khác
  Telegram nơi trường này tuỳ chọn
- **`getUpdates` bị cấm** khi bot đang cắm webhook (lỗi 400)
- **Route model binding** cần `SubstituteBindings`; nhóm route webhook cố ý
  không có middleware nào nên `{bot}` từng không được resolve
- **`getRouteKeyName()`**: `Route::bind` chỉ lo chiều URL → model. Thiếu khai
  báo này thì `route('...', $model)` sinh URL bằng khoá chính và mọi nút bấm
  trong UI trả 404

### Chưa xác minh

Một phần **OA API** vẫn dựa trên tài liệu chứ chưa gọi thật. Xem mục
"Những gì chưa được xác minh" trong README. Đáng ngờ nhất là
`ImageMessage::url()` — nhiều khả năng chỉ Bot mới nhận URL ảnh.

[Unreleased]: https://github.com/field-vn/zalo/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/field-vn/zalo/releases/tag/v0.1.0
