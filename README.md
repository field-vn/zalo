# Zalo

[![Tests](https://github.com/field-vn/zalo/actions/workflows/tests.yml/badge.svg)](https://github.com/field-vn/zalo/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![Downloads](https://img.shields.io/packagist/dt/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![License](https://img.shields.io/packagist/l/field-vn/zalo.svg)](LICENSE)

SDK Laravel cho **Zalo Official Account** và **Zalo Bot**: quản lý nhiều OA, tự refresh token, nhận webhook, kèm giao diện cấu hình.

## Mục lục

- [Yêu cầu](#yêu-cầu)
- [Cài đặt](#cài-đặt)
- [Kết nối Official Account](#kết-nối-official-account)
- [Kết nối Bot](#kết-nối-bot)
- [Gửi tin nhắn](#gửi-tin-nhắn)
- [Nhận tin nhắn](#nhận-tin-nhắn)
- [Chi phí và giới hạn](#chi-phí-và-giới-hạn)
- [Cấu hình](#cấu-hình)
- [Giao diện web](#giao-diện-web)
- [Commands](#commands)
- [Testing](#testing)
- [Mở rộng](#mở-rộng)

## Yêu cầu

- PHP 8.2+
- Laravel 10, 11, 12 hoặc 13

## Cài đặt

```bash
composer require field-vn/zalo
```

Thêm credential của Zalo App vào `.env`:

```dotenv
ZALO_APP_ID=
ZALO_APP_SECRET=
```

Chạy trình cài đặt:

```bash
php artisan zalo:install
```

Lệnh này kiểm tra env, publish config, chạy migration và nhắc các bước còn thiếu.

### Xác thực domain

Zalo chỉ chấp nhận URL thuộc domain đã xác thực. Làm bước này **trước**, nếu không webhook sẽ báo *"chưa được xác thực domain"* và OAuth trả `-14003 Invalid redirect uri`.

Vào Zalo Developers → App → Xác thực domain, tải file HTML được cấp, đặt vào thư mục `public/` của dự án, rồi bấm xác thực.

## Kết nối Official Account

```bash
php artisan zalo:oa:add
```

Nhập tên, slug và OA ID (lấy ở trang quản trị Zalo OA). Lệnh sẽ hỏi có cấp quyền luôn không.

Luồng cấp quyền in ra một link — mở bằng tài khoản **admin của OA** và bấm đồng ý. Nếu callback truy cập được từ Internet thì token tự lưu; nếu đang chạy localhost, copy giá trị `code` trên thanh địa chỉ rồi dán vào terminal.

Redirect URI phải khớp chính xác giá trị khai trong Zalo Developers, kể cả dấu `/` cuối. Dashboard `/zalo` hiển thị sẵn giá trị đúng kèm nút copy.

Kiểm tra kết nối:

```bash
php artisan zalo:oa:list
php artisan zalo:oa:test cskh
```

### Scheduler

Thêm cron sau, nếu không token sẽ hết hạn:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

`refresh_token` của Zalo sống khoảng ba tháng và xoay vòng mỗi lần dùng. Package đăng ký sẵn `zalo:token:refresh --all` chạy hàng giờ.

## Kết nối Bot

Bot dùng token tĩnh, không cần OAuth và không dính Zalo App. Lấy token tại [bot.zaloplatforms.com](https://bot.zaloplatforms.com).

```bash
php artisan zalo:bot:add
```

Lệnh gọi `getMe` ngay để kiểm tra token. Nếu token sai, bản ghi vừa tạo sẽ bị xoá.

### Lấy `chat_id`

Bot cần `chat_id` để gửi tin. Zalo không có API liệt kê `chat_id`, nên phải cắm webhook để package ghi lại khi có người nhắn tới.

```dotenv
# Chuỗi do bạn tự đặt, dài 8–256 ký tự
ZALO_BOT_WEBHOOK_SECRET=
```

```bash
php artisan zalo:bot:webhook support --set
# Mở Zalo, nhắn cho bot một câu
php artisan zalo:bot:chats support
php artisan zalo:bot:send support <chat_id> "Xin chào"
```

Mọi người nhắn tới bot được lưu vào bảng `zl_bot_chats`. Có thể làm toàn bộ các bước trên bằng giao diện tại `/zalo/bots/{slug}`.

`getUpdates` và webhook loại trừ nhau — Zalo trả lỗi 400 nếu gọi `getUpdates` khi bot đang cắm webhook.

## Gửi tin nhắn

```php
use FieldVn\Zalo\Laravel\Facades\Zalo;

Zalo::oa('cskh')->messages()->text($userId, 'Đơn hàng đã được xác nhận');
Zalo::bot('support')->text($chatId, 'Xin chào');
```

Hoặc dùng helper toàn cục:

```php
zalo_oa('cskh')->messages()->text($userId, 'Xin chào');
zalo_bot('support')->text($chatId, 'Xin chào');
```

### Official Account

**Tin có nút bấm**

```php
use FieldVn\Zalo\Core\Channels\OA\Messages\Button;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;

Zalo::oa('cskh')->messages()->send(
    TextMessage::to($userId)
        ->text('Đơn hàng #1234 đang giao')
        ->button(Button::url('Theo dõi', 'https://shop.vn/don/1234'))
        ->button(Button::phone('Gọi shipper', '0900000000'))
);
```

Message object là immutable — mỗi lần gọi trả về bản sao mới, nên dựng sẵn tin mẫu rồi tuỳ biến cho từng người nhận là an toàn.

**Gửi ảnh**

OA yêu cầu upload ảnh trước để lấy `attachment_id`:

```php
$id = Zalo::oa('cskh')->uploads()->image('/duong/dan/anh.jpg');

Zalo::oa('cskh')->messages()->image($userId, $id, 'Ảnh sản phẩm');
```

`attachment_id` dùng lại được, nên gửi cùng một ảnh cho nhiều người chỉ cần upload một lần. Package kiểm tra file tồn tại, dung lượng dưới 1 MB và đúng định dạng trước khi gọi API.

**Payload tuỳ ý**

Với những dạng tin package chưa bọc thành class (list, carousel, `request_user_info`, file):

```php
use FieldVn\Zalo\Core\Channels\OA\Messages\RawMessage;

Zalo::oa('cskh')->messages()->send(
    RawMessage::to($userId)->message([
        'attachment' => [
            'type' => 'template',
            'payload' => ['template_type' => 'list', 'elements' => [/* … */]],
        ],
    ])
);
```

Gọi thẳng endpoint chưa được bọc:

```php
Zalo::oa('cskh')->request()->get('/v3.0/oa/duong-dan-moi', ['param' => 'x']);
```

### Bot

```php
$bot = Zalo::bot('support');

$bot->text($chatId, 'Xin chào');
$bot->photo($chatId, 'https://…/anh.png', 'Chú thích');
$bot->sticker($chatId, $stickerId);
$bot->typing($chatId);
```

Bot nhận thẳng URL ảnh, không cần upload trước như OA.

### Nhiều OA

```php
Zalo::oa();                  // OA active đầu tiên
Zalo::oa('marketing');       // theo slug
Zalo::availableOas();        // Collection<ZaloOa>, dùng cho dropdown

Zalo::oas(fn ($oa) => in_array('cskh', $oa->tags ?? []))
    ->each(fn ($channel) => $channel->messages()->text($userId, $noiDung));
```

## Nhận tin nhắn

OA và Bot dùng hai URL và hai cơ chế xác thực khác nhau:

| Kênh | URL | Xác thực |
|---|---|---|
| OA | `/zalo/webhook` | chữ ký `X-ZEvent-Signature` |
| Bot | `/zalo/webhook/bot/{slug}` | secret ở header `X-Bot-Api-Secret-Token` |

Mỗi bot có URL riêng vì payload Zalo gửi không kèm định danh bot.

```dotenv
ZALO_WEBHOOK_SECRET=          # OA Secret Key, lấy trong cài đặt webhook của App
ZALO_BOT_WEBHOOK_SECRET=      # chuỗi bạn tự đặt cho bot, 8–256 ký tự
```

`ZALO_WEBHOOK_SECRET` khác `ZALO_APP_SECRET`. Nó là *OA Secret Key* nằm ở phần cài đặt webhook, không phải secret của ứng dụng.

### Lắng nghe event

```php
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;

class TraLoiOa
{
    public function handle(ZaloMessageReceived $e): void
    {
        if ($e->text === null || $e->oa === null) {
            return;
        }

        zalo_oa($e->oa->slug)->messages()->text($e->userId, "Bạn vừa nói: {$e->text}");
    }
}
```

```php
use FieldVn\Zalo\Laravel\Events\ZaloBotMessageReceived;

class TraLoiBot
{
    public function handle(ZaloBotMessageReceived $e): void
    {
        zalo_bot($e->bot->slug)->text($e->chatId, "Đã nhận: {$e->text}");
    }
}
```

| Event | Khi nào |
|---|---|
| `ZaloWebhookReceived` | Mọi sự kiện của OA, kèm payload gốc |
| `ZaloMessageReceived` | Người dùng gửi tin nhắn tới OA |
| `ZaloFollowerAdded` | Người dùng quan tâm OA |
| `ZaloFollowerRemoved` | Người dùng bỏ quan tâm |
| `ZaloOaConnected` | OA vừa được cấp quyền |
| `ZaloOaDisconnected` | OA mất kết nối, cần cấp quyền lại |
| `ZaloBotUpdateReceived` | Mọi update của Bot, kèm payload gốc |
| `ZaloBotMessageReceived` | Người dùng nhắn cho Bot |

`ZaloWebhookReceived` và `ZaloBotUpdateReceived` được bắn cho mọi loại sự kiện, kể cả loại package chưa bọc riêng.

### Hành vi cần biết

- Route webhook không đi qua auth của giao diện. Chữ ký (OA) hoặc secret header (Bot) là lớp bảo vệ duy nhất.
- Chưa cấu hình secret thì webhook bị từ chối 401.
- Webhook của Bot yêu cầu HTTPS vì secret đi nguyên văn trong header.
- Mặc định xử lý qua queue (`ZALO_WEBHOOK_QUEUE=true`).
- Lỗi trong listener của bạn không làm webhook trả 500. Nếu trả 500, Zalo sẽ gửi lại và bạn xử lý trùng.
- Chống trùng nên dựa vào `$e->messageId`.

Bật `ZALO_WEBHOOK_LOG=true` để ghi payload vào `zl_webhook_logs` khi cần debug. Mặc định tắt vì payload chứa nội dung tin nhắn của người dùng.

Package không tự gửi cảnh báo khi OA mất kết nối. Lắng nghe `ZaloOaDisconnected` để tự xử lý.

## Chi phí và giới hạn

### Tin Tư vấn (OA)

Tính từ tương tác cuối của người dùng:

| Khoảng thời gian | Qua OpenAPI |
|---|---|
| Trong 48 giờ | Gửi được, miễn phí |
| 48 giờ đến 7 ngày | Gửi được, Zalo tính phí |
| Sau 7 ngày | Bị từ chối |

Package không tự chặn khi quá 48 giờ vì nó không biết thời điểm tương tác cuối. Nếu cần kiểm soát chi phí, hãy tự lưu mốc tương tác từ webhook.

"Tương tác" gồm: gửi tin nhắn tới OA, gửi tin trong nhóm GMF, gọi thoại tới OA, đồng ý nhận cuộc gọi, bình luận bài viết, tương tác chatbot, bấm Menu hoặc CTA, bấm widget.

### Khả năng của từng kênh

| | Bot | OA |
|---|---|---|
| Text | ✅ | ✅ |
| Ảnh | ✅ (URL) | ✅ (upload trước) |
| Sticker | ✅ | ❌ |
| Trạng thái đang soạn tin | ✅ | ❌ |
| Nút bấm | ❌ | ✅ |
| List, carousel | ❌ | ✅ |
| Giới hạn thời gian | Không | Có, xem bảng trên |

### ZBS Template Message

Từ 01/01/2026 Zalo hợp nhất ZNS, tin UID Giao dịch và tin UID Truyền thông thành **ZBS Template Message**, gửi qua UID hoặc số điện thoại theo template đã duyệt.

Package chưa hỗ trợ. Hai method `transaction()` và `promotion()` trỏ tới endpoint có trước thời điểm hợp nhất.

## Cấu hình

### Zalo App

```dotenv
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_APP_REDIRECT=            # để trống thì tự suy ra từ ZALO_UI_PATH
```

App credential chỉ đọc từ env, không lưu vào DB hay sửa qua giao diện.

### Prefix bảng

```dotenv
ZALO_TABLE_PREFIX=zl_
```

Chốt giá trị này **trước lần migrate đầu tiên**. Đổi sau khi đã migrate sẽ khiến code tìm bảng theo tên mới trong khi DB giữ tên cũ.

Prefix cộng dồn với prefix của DB connection: `DB_PREFIX=app_` cộng `zl_` cho ra bảng `app_zl_oas`.

Package tạo 6 bảng: `oas`, `oa_tokens`, `bots`, `bot_chats`, `audit_logs`, `webhook_logs`.

### Toàn bộ biến env

```dotenv
# Zalo App
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_APP_KEY=default
ZALO_APP_REDIRECT=

# Webhook
ZALO_WEBHOOK_ENABLED=true
ZALO_WEBHOOK_PATH=zalo/webhook
ZALO_WEBHOOK_SECRET=
ZALO_WEBHOOK_QUEUE=true
ZALO_WEBHOOK_QUEUE_NAME=
ZALO_WEBHOOK_TOLERANCE=300
ZALO_WEBHOOK_LOG=false

# Bot
ZALO_BOT_WEBHOOK_SECRET=

# Giao diện
ZALO_UI_ENABLED=true
ZALO_UI_PATH=zalo
ZALO_UI_USER=admin
ZALO_UI_PASSWORD=
ZALO_UI_ALLOWED_IPS=

# Khác
ZALO_TABLE_PREFIX=zl_
ZALO_SCHEDULER=true
ZALO_HTTP_TIMEOUT=10
ZALO_HTTP_CONNECT_TIMEOUT=5
ZALO_HTTP_RETRY=3
```

### `APP_KEY`

Token lưu trong DB được mã hoá bằng `APP_KEY`. Đổi `APP_KEY` sẽ làm mất toàn bộ token và phải cấp quyền lại cho mọi OA.

## Giao diện web

Truy cập `https://your-app.com/zalo`.

- **Tổng quan** — sức khoẻ token, Redirect URI và Webhook URL kèm nút copy
- **Official Account** — danh sách; mỗi OA có trang riêng để sửa, cấp quyền, gửi tin thử
- **Bot** — danh sách; mỗi bot có trang riêng để sửa, cắm webhook, xem `chat_id`, gửi tin thử

Giao diện không cần build step và không cần `vendor:publish`. Muốn tuỳ biến thì chạy `php artisan vendor:publish --tag=zalo-views`.

```dotenv
ZALO_UI_ENABLED=true
ZALO_UI_PATH=zalo
ZALO_UI_USER=admin
ZALO_UI_PASSWORD=             # để trống thì giao diện chỉ chạy ở môi trường local
ZALO_UI_ALLOWED_IPS=          # ví dụ: 113.161.0.0/16,203.0.113.5
```

Basic Auth gửi credential ở mọi request nên site phải chạy HTTPS.

Nếu dự án đã có hệ thống auth riêng, định nghĩa gate — nó được ưu tiên hơn basic auth:

```php
// AppServiceProvider::boot()
Zalo::auth(fn ($request) => $request->user()?->is_admin === true);
```

Token và secret không hiển thị đầy đủ trên giao diện.

## Commands

| Command | Mô tả |
|---|---|
| `zalo` | Trạng thái OA, Bot và sức khoẻ token |
| `zalo:install` | Cài đặt: kiểm env, publish config, migrate |
| `zalo:doctor` | Chẩn đoán cấu hình kèm hướng dẫn sửa |
| `zalo:oa:add` | Thêm OA |
| `zalo:oa:list` | Liệt kê OA và trạng thái token |
| `zalo:oa:test {oa}` | Gọi thử API để xác nhận kết nối |
| `zalo:authorize {oa}` | Cấp quyền và lấy token lần đầu |
| `zalo:token:refresh` | `{oa?}` · `--all` · `--force` |
| `zalo:bot:add` | Thêm Bot, tự kiểm tra token |
| `zalo:bot:list` | Liệt kê Bot |
| `zalo:bot:test {bot}` | Kiểm tra token bot |
| `zalo:bot:webhook {bot}` | Xem · `--set` · `--delete` · `--url=` |
| `zalo:bot:chats {bot?}` | Liệt kê `chat_id` đã ghi nhận |
| `zalo:bot:send {bot} {chat} {text?}` | Gửi tin · `--photo=` · `--sticker=` |

Gặp vấn đề thì chạy `zalo:doctor` trước — lệnh này kiểm credential, redirect URI, bảng, mã hoá, giao diện, scheduler, từng OA và từng Bot.

## Testing

`Zalo::fake()` chặn mọi lời gọi tới Zalo và cho phép assert những gì đã gửi:

```php
use FieldVn\Zalo\Laravel\Facades\Zalo;

it('gửi xác nhận khi đặt hàng', function () {
    Zalo::fake();

    $this->post('/don-hang', ['san_pham' => 1]);

    Zalo::assertSentTo('user-1', 'Đơn hàng đã được xác nhận');
});
```

Không cần OA trong DB, không cần token, không cần giả lập OAuth.

| Assertion | Mô tả |
|---|---|
| `assertSentTo($userId, $text?)` | Đã gửi tin nhắn tới người này |
| `assertNotSentTo($userId)` | Chưa gửi cho người này |
| `assertSentVia($slug)` | Đã gửi qua đúng OA đó |
| `assertSent($callback)` | Điều kiện tuỳ ý |
| `assertNotSent($callback)` | |
| `assertNothingSent()` | |
| `assertSentCount($n)` | |
| `sent()` | Collection các request đã ghi |

Đặt response giả:

```php
Zalo::fake()->push(['error' => -216, 'message' => 'Token hết hạn']);
```

`fake()` chỉ thay tầng mạng. Message builder, resource và phần validate payload vẫn chạy code thật, nên tin nhắn dựng sai (quá 2000 ký tự, nút không phải HTTPS) vẫn bị bắt trong test.

## Mở rộng

Thay thành phần mà không cần fork:

```php
// Đổi tầng HTTP
$this->app->bind(FieldVn\Zalo\Contracts\Transport::class, MyTransport::class);

// Đổi nguồn danh sách OA (multi-tenant, config thuần, API nội bộ…)
$this->app->bind(FieldVn\Zalo\Contracts\OaRepository::class, TenantOaRepository::class);
```

`OAChannel`, `BotChannel` và các `Resource` đều dùng `Macroable`.

## Phát triển package

```bash
composer test
composer analyse
composer format
```

## Đóng góp

Xem [CONTRIBUTING.md](CONTRIBUTING.md) và [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Bảo mật

Phát hiện lỗ hổng? Xem [SECURITY.md](SECURITY.md) — vui lòng không mở public issue.

## License

MIT — xem [LICENSE](LICENSE).
