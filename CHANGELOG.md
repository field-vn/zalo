# Changelog

Mọi thay đổi đáng chú ý của `field-vn/zalo` được ghi tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

## [Unreleased]

### Added
- Quản lý nhiều OA và Bot qua `Zalo::oa()` / `Zalo::bot()`
- Token tự refresh, kể cả xoay `refresh_token` trước khi hết hạn
- Luồng OAuth cấp quyền: route callback và `zalo:authorize` (chạy được cả khi
  callback không truy cập được từ Internet)
- UI bảo vệ bằng basic auth + IP allowlist, fail-closed
- Commands: `zalo`, `zalo:install`, `zalo:doctor`, `zalo:oa:add`,
  `zalo:oa:list`, `zalo:oa:test`, `zalo:authorize`, `zalo:token:refresh`,
  `zalo:bot:add`, `zalo:bot:list`, `zalo:bot:test`
- Webhook: xác thực `X-ZEvent-Signature`, xử lý qua queue, chống replay
- Events: `ZaloWebhookReceived`, `ZaloMessageReceived`, `ZaloFollowerAdded`,
  `ZaloFollowerRemoved`, `ZaloOaConnected`, `ZaloOaDisconnected`
- `Zalo::fake()` cho test của dự án — không cần OA trong DB hay token
- Prefix bảng cấu hình được qua `ZALO_TABLE_PREFIX` (mặc định `zl_`)
