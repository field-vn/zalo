# Changelog

Mọi thay đổi đáng chú ý của `field-vn/zalo` được ghi tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

> Trong giai đoạn 0.x, thay đổi phá vỡ tương thích có thể xuất hiện ở bản
> **minor** (`0.1` → `0.2`). Dùng `^0.1` nếu cần ổn định.

## [Unreleased]

## [0.3.0] — 2026-09-05

### Added

- **`TokenStatus` + cache ngắn hạn** — `OAChannel::tokenStatus()` trả
  `remainingMinutes` (missing = `-1`). Cache key `zalo.token_status.{zl_oas.id}`,
  TTL `zalo.notifier.token_status_cache_ttl` (mặc định 90s). Invalidate khi
  `EloquentTokenStore::put()` / `forget()`.
- **Bảng `zl_contacts`** — theo dõi `is_following` và `last_interaction_at` từ
  webhook follow / unfollow / message / user_seen_message. Unique
  `(oa_id, zalo_user_id)`.
- **`OaNotifier`** — chọn kênh rồi gửi: ưu tiên OA CS theo `zalo_user_id`,
  fallback ZBS theo SĐT. Token stale / contact unfollow / ngoài cửa sổ CS →
  ZBS khi đủ điều kiện. CS fail **không** fallback ZBS.

  ```php
  $result = Zalo::oa('cskh')->notifier()->send(
      new ZaloRecipient(zaloUserId: $userId, phone: $phone),
      new ZaloOutboundMessage(text: 'Xin chào', templateId: $id, templateData: [...]),
  );
  ```

- Config `zalo.notifier.*`: `stale_below_minutes` (60), `fresh_buffer_minutes`
  (1440), `cs_window_days` (7), `contact_prune_days` (180).

### Fixed

- **CI chưa từng chạy được test.** Mọi job matrix chết ở `composer update`,
  trước khi tới `composer test` — nghĩa là badge Tests báo đỏ từ commit đầu
  tiên và chưa lần nào có lưới an toàn thật.

  Workflow ghim `illuminate/support` nhưng bỏ trống `illuminate/console`,
  `illuminate/database` và `orchestra/testbench`. Composer chọn `console` một
  phiên bản khác với `support` đã ghim, và `laravel/framework` do testbench
  kéo về lại *replace* `illuminate/*` nên không thể cùng tồn tại — bộ điều
  kiện không bao giờ giải được.

  Nay ghim `laravel/framework` + `orchestra/testbench` theo cặp, dùng
  `include` để không sinh ra cặp PHP/Laravel không tồn tại. Bổ sung
  `pdo_sqlite`, `sqlite3` và nâng `actions/checkout` lên v5.

### Removed

- **Bỏ hỗ trợ Laravel 10 và 11.** Hai nhánh này đã hết hạn hỗ trợ bảo mật
  (Laravel 11 từ 12/03/2026), nên mọi bản phát hành của chúng đều mang
  advisory không bao giờ được vá. Composer bản mới từ chối cài chúng theo mặc
  định — tức là lời hứa "hỗ trợ Laravel 10, 11" trong README thực tế không ai
  dùng được.

  Yêu cầu mới: **Laravel 12 hoặc 13**, PHP 8.2+.

  Dự án đang ghim `^0.2` mà chạy Laravel 11 sẽ tự dừng ở `0.2.2` khi
  `composer update` — không có gì hỏng, chỉ là không nhận bản mới.

## [0.2.2] — 2026-08-26

### Fixed

- **`templates()` trả về `-132 Invalid status`.** Tham số `status` được gửi
  dưới dạng chuỗi (`ENABLE`) trong khi Zalo chỉ nhận số `1`–`5`. Chỗ dễ nhầm:
  trong *response*, Zalo trả `status` là chữ. Nay dùng các hằng
  `ZbsResource::STATUS_ENABLE`…`STATUS_DELETE`.
- **`quota()` gọi sai đường dẫn** — `/template/quota` không tồn tại, đúng là
  `/message/quota`.

### Changed

- **README sắp lại theo trình tự làm việc thật**: phần cấu hình bằng giao diện
  lên đầu vì đó là việc làm trước tiên, phần API cho developer tách xuống dưới.
  Bổ sung mục ZBS, trước đây README vẫn ghi là chưa hỗ trợ.
- `templates()` mặc định trả về template ở **mọi trạng thái**. Lọc sẵn theo
  `ENABLE` khiến OA đang chờ duyệt nhận kết quả rỗng và hiểu nhầm thành chưa
  tạo mẫu nào. Dùng `--enabled` (thay cho `--all` cũ) để chỉ lấy mẫu dùng được.
- `template($id)` đọc từ chính danh sách thay vì gọi endpoint riêng —
  `/template/all` đã trả về `listParams` đầy đủ. Trả về `array|null`.

### Added

- **Giao diện ZBS** tại `/zalo/oas/{slug}/zbs`: danh sách mẫu kèm trạng thái,
  hạn mức còn lại, form gửi thử theo số điện thoại với ô nhập dựng theo tham
  số của mẫu, và ô tra trạng thái giao tin. Mặc định `development`; gửi
  `production` cần tick thêm một ô xác nhận.

  Mẫu chưa duyệt trả về danh sách tham số rỗng — khi đó form chuyển sang ô
  nhập JSON để không kẹt cho tới lúc duyệt xong.

- Command `zalo:zbs:status <msg_id>` — tra trạng thái GIAO tin. `zbs:send` trả
  `msg_id` khi Zalo *nhận* tin, không phải khi người dùng *nhận được*; khi tin
  không tới thì đây là chỗ duy nhất nói được nó tắc ở đâu.
- `sampleData($id)` — dữ liệu mẫu của template, dùng làm `template_data` khi
  gửi thử mà không phải tự đoán tên tham số.
- Thông báo lỗi ZBS gợi ý cách xử lý theo từng mã (`-120`, `-135`, `-138`:
  OA/App chưa được cấp quyền ZBS).

## [0.2.1] — 2026-08-26

### Added

- **ZBS Template Message** — gửi tin theo mẫu tới số điện thoại, kênh duy nhất
  tới được người chưa từng tương tác với OA.

  ```php
  $oa = Zalo::oa('cskh');

  $oa->zbs()->templates();                                  // template đã duyệt
  $oa->zbs()->template($id);                                // tham số bắt buộc
  $oa->zbs()->send('0987654321', $id, ['otp' => '123456']);
  ```

  Mặc định chạy ở `development`: miễn phí, chỉ gửi tới quản trị viên OA/App.
  Gửi cho khách thật cần đặt `ZALO_ZBS_MODE=production`.

- `PhoneNumber::normalize()` — chấp nhận `0987…`, `+8498…`, `8498…` và mọi
  cách viết có dấu cách hay gạch ngang, quy về `84987654321` như Zalo yêu cầu.
- Commands `zalo:zbs:templates` và `zalo:zbs:send`.
- `PhoneNumber` báo lỗi kèm định dạng đúng khi đầu vào không chứa chữ số.

> Đây là bản **patch** dù có tính năng mới. Trong giai đoạn 0.x, giữ ở `0.2.x`
> để các dự án đang ghim `^0.2` nhận được ngay mà không phải sửa
> `composer.json`. Sẽ lên `0.3.0` khi ZBS đã xác minh chạy thật.

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

[Unreleased]: https://github.com/field-vn/zalo/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/field-vn/zalo/releases/tag/v0.3.0
[0.2.2]: https://github.com/field-vn/zalo/releases/tag/v0.2.2
[0.2.1]: https://github.com/field-vn/zalo/releases/tag/v0.2.1
[0.2.0]: https://github.com/field-vn/zalo/releases/tag/v0.2.0
[0.1.1]: https://github.com/field-vn/zalo/releases/tag/v0.1.1
[0.1.0]: https://github.com/field-vn/zalo/releases/tag/v0.1.0
