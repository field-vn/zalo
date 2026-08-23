# Zalo

[![Tests](https://github.com/field-vn/zalo/actions/workflows/tests.yml/badge.svg)](https://github.com/field-vn/zalo/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![Downloads](https://img.shields.io/packagist/dt/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![License](https://img.shields.io/packagist/l/field-vn/zalo.svg)](LICENSE)

SDK Laravel cho **Zalo Official Account** và **Zalo Bot**. Quản lý nhiều OA, tự refresh token, webhook cho cả hai kênh, kèm UI cấu hình không cần build step.

> ⚠️ **Đang phát triển (0.x)** — API còn có thể thay đổi giữa các bản minor. Xem [Những gì chưa được xác minh](#những-gì-chưa-được-xác-minh) trước khi đưa vào production.

## Mục lục

- [Vì sao dùng package này](#vì-sao-dùng-package-này)
- [OA và Bot khác nhau thế nào](#oa-và-bot-khác-nhau-thế-nào)
- [Cài đặt](#cài-đặt)
- [Kết nối OA đầu tiên](#kết-nối-oa-đầu-tiên)
- [Kết nối Bot đầu tiên](#kết-nối-bot-đầu-tiên)
- [Gửi tin nhắn](#gửi-tin-nhắn)
- [Nhận tin nhắn](#nhận-tin-nhắn-webhook)
- [Cấu hình](#cấu-hình)
- [Giao diện web](#giao-diện-web)
- [Commands](#commands)
- [Testing dự án của bạn](#testing-dự-án-của-bạn)
- [Điểm mở rộng](#điểm-mở-rộng)
- [Những gì chưa được xác minh](#những-gì-chưa-được-xác-minh)

## Vì sao dùng package này

- **Nhiều OA trong một app** — `Zalo::oa('cskh')`, `Zalo::oa('marketing')`
- **Token tự refresh**, kể cả việc *xoay* `refresh_token` trước khi nó hết hạn — thứ khiến hầu hết tích hợp Zalo tự viết chết sau ba tháng, im lặng
- **Webhook cho cả OA lẫn Bot**, mỗi bên đúng cơ chế xác thực của nó, fail-closed
- **`chat_id` của Bot tự lưu lại** — Zalo không có API nào liệt kê, và `getUpdates` chỉ đọc được một lần
- **Message là object**, IDE gợi ý được, payload validate trước khi bắn đi
- **`zalo:doctor`** chẩn đoán cấu hình và chỉ thẳng cách sửa

## OA và Bot khác nhau thế nào

Đây là thứ nên đọc trước khi chọn kênh. Hai bên **không** tương đương, và package cố ý không giả vờ rằng chúng tương đương.

| | Zalo Bot | Zalo OA |
|---|---|---|
| Lấy đâu ra | [bot.zaloplatforms.com](https://bot.zaloplatforms.com) | Zalo Developers + OAuth |
| Xác thực | Token tĩnh | OAuth, token hết hạn 1 giờ, refresh xoay vòng |
| Text | ✅ | ✅ |
| Ảnh | ✅ nhận thẳng URL | ✅ nhưng **phải upload trước** lấy `attachment_id` |
| Sticker | ✅ | ❌ |
| "Đang soạn tin" | ✅ | ❌ |
| **Nút bấm** | ❌ | ✅ |
| **List / carousel** | ❌ | ✅ |
| Giới hạn thời gian | không | **48 giờ** kể từ tin cuối của người dùng (tin tư vấn) |
| Xác thực webhook | secret nguyên văn ở header | chữ ký HMAC |

Bot đúng nghĩa là Telegram rút gọn. Vì Bot không có nút bấm và không có gì để kết hợp, package **không** tạo message object cho Bot — thêm một lớp object vào đó chỉ là nghi thức thừa và làm người đọc tưởng hai kênh dùng lẫn nhau được.

Một khác biệt nữa dễ vấp: ở OA, *loại tin* và *định dạng nội dung* là hai trục độc lập.

```
Trục 1 — GỬI KIỂU GÌ (chỉ OA)        Trục 2 — NỘI DUNG LÀ GÌ
  /message/cs           48 giờ         text, text + nút, ảnh,
  /message/transaction  template        file, sticker, list
  /message/promotion    quota
```

Cùng một nội dung text, gửi qua `cs` thì trong 48 giờ là được, gửi qua `promotion` thì cần quota và OA phải đã xác thực.

## Yêu cầu

- PHP 8.2+
- Laravel 10, 11, 12 hoặc 13

## Cài đặt

```bash
composer require field-vn/zalo
```

```dotenv
ZALO_APP_ID=
ZALO_APP_SECRET=
```

```bash
php artisan zalo:install
```

Lệnh này kiểm tra env, publish config, migrate, và cảnh báo những thứ dễ quên: cron cho scheduler, credential cho UI, HTTPS.

> **Xác thực domain trước.** Zalo chỉ chấp nhận URL thuộc domain đã xác thực. Chưa làm bước này thì webhook báo *"chưa được xác thực domain"* và OAuth trả `-14003 Invalid redirect uri` — cùng một nguyên nhân. Vào Zalo Developers → App → Xác thực domain, tải file HTML họ cấp, đặt vào `public/`.

## Kết nối OA đầu tiên

```bash
php artisan zalo:oa:add
```

Nhập tên, slug và OA ID (lấy ở trang quản trị Zalo OA), rồi lệnh hỏi có cấp quyền luôn không.

Luồng cấp quyền in ra một link — mở bằng tài khoản **admin của OA**, bấm đồng ý. Callback truy cập được từ Internet thì token tự lưu; đang chạy localhost thì copy giá trị `code` trên thanh địa chỉ dán vào terminal.

> **Redirect URI phải khớp CHÍNH XÁC** giá trị khai trong Zalo Developers — thừa hay thiếu một dấu `/` cuối cũng hỏng. Dashboard `/zalo` in sẵn giá trị đúng kèm nút copy.

```bash
php artisan zalo:oa:list
php artisan zalo:oa:test cskh
```

## Kết nối Bot đầu tiên

Đơn giản hơn OA nhiều: token tĩnh, không OAuth, không refresh, không dính Zalo App.

```bash
php artisan zalo:bot:add
```

Lệnh gọi `getMe` ngay để bắt lỗi copy nhầm token. Token hỏng thì bản ghi vừa tạo cũng bị xoá, không để lại rác.

### Lấy `chat_id`

Bot chỉ gửi được tin khi biết `chat_id`, mà Zalo **không có API nào liệt kê** nó. Cách duy nhất là bắt lấy lúc người ta nhắn tới — nên phải cắm webhook trước.

```dotenv
# BẠN tự đặt chuỗi này, không phải Zalo cấp. Bắt buộc, dài 8–256 ký tự.
ZALO_BOT_WEBHOOK_SECRET=
```

```bash
php artisan zalo:bot:webhook support          # xem URL và trạng thái secret
php artisan zalo:bot:webhook support --set    # cắm
# … mở Zalo, nhắn cho bot một câu …
php artisan zalo:bot:chats support            # chat_id đã được lưu lại
php artisan zalo:bot:send support <chat_id> "Xin chào"
```

Từ lúc webhook chạy, mọi người nhắn tới bot đều được ghi vào bảng `zl_bot_chats` — không phải poll thủ công nữa. Cũng làm được toàn bộ việc này bằng chuột ở `/zalo/bots/{slug}`.

> `getUpdates` và webhook **loại trừ nhau**: Zalo trả lỗi 400 nếu gọi `getUpdates` khi bot đang cắm webhook.

## Gửi tin nhắn

```php
use FieldVn\Zalo\Laravel\Facades\Zalo;

// Ngắn nhất
Zalo::oa('cskh')->messages()->text($userId, 'Đơn hàng đã được xác nhận');
Zalo::bot('support')->text($chatId, 'pong');
```

Helper toàn cục nếu bạn thích:

```php
zalo_oa('cskh')->messages()->text($userId, 'Xin chào');
zalo_bot('support')->text($chatId, 'pong');
```

### OA — tin có nút

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

Message object là **immutable** — mỗi lần gọi trả về bản sao mới. Nhờ vậy dựng sẵn một tin mẫu rồi đổi từng chút cho nhiều người là an toàn.

### OA — gửi ảnh

OA không nhận URL ảnh như Bot: phải upload trước.

```php
$id = Zalo::oa('cskh')->uploads()->image('/duong/dan/anh.jpg');

Zalo::oa('cskh')->messages()->image($userId, $id, 'Ảnh sản phẩm');
```

`attachment_id` dùng lại được nhiều lần — gửi cùng một ảnh cho nhiều người thì upload **một** lần rồi lưu id lại. Package kiểm file thiếu, rỗng, quá 1 MB hoặc sai định dạng **trước khi** gọi mạng.

### OA — loại tin khác

```php
$messages = Zalo::oa('cskh')->messages();

$messages->send($message);          // tin tư vấn, trong 48 giờ
$messages->transaction($message);   // tin giao dịch, cần template đã duyệt
$messages->promotion($message);     // tin truyền thông, cần quota
```

### Bot

```php
$bot = Zalo::bot('support');

$bot->text($chatId, 'pong');
$bot->photo($chatId, 'https://…/anh.png', 'Chú thích');
$bot->sticker($chatId, $stickerId);
$bot->typing($chatId);              // trước khi làm việc lâu (gọi AI, tra cứu chậm)
```

### Dạng tin package chưa bọc

Zalo OA còn nhiều dạng tin (list/carousel, `request_user_info`, file, template giao dịch) mà package **chưa xác minh được payload thật**, nên cố ý không bọc thành class có kiểu. Dùng `RawMessage` để tự dựng:

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

Bán cho bạn một API trông chắc chắn mà chưa từng chạy thật thì tệ hơn là nói thẳng. Nếu bạn xác minh được một dạng tin, hãy mở issue để nó thành class có kiểu.

Endpoint chưa được bọc thì đi thẳng:

```php
Zalo::oa('cskh')->request()->get('/v3.0/oa/duong-dan-moi', ['param' => 'x']);
```

### Nhiều OA

```php
Zalo::oa();                  // OA active đầu tiên
Zalo::oa('marketing');       // theo slug
Zalo::availableOas();        // Collection<ZaloOa> — cho dropdown

// Phân phối có chủ đích theo tag
Zalo::oas(fn ($oa) => in_array('cskh', $oa->tags ?? []))
    ->each(fn ($channel) => $channel->messages()->text($userId, $noiDung));
```

## Nhận tin nhắn (Webhook)

Hai kênh xác thực theo hai cơ chế khác hẳn nhau, nên có hai route riêng và **không** dùng chung code.

| | URL | Xác thực |
|---|---|---|
| OA | `/zalo/webhook` | chữ ký `X-ZEvent-Signature` |
| Bot | `/zalo/webhook/bot/{slug}` | secret nguyên văn ở `X-Bot-Api-Secret-Token` |

Bot mỗi con một URL riêng vì payload Zalo gửi **không kèm định danh bot nào** — đường dẫn là cách duy nhất để biết update thuộc về bot nào.

```dotenv
ZALO_WEBHOOK_SECRET=          # OA Secret Key — Zalo CẤP cho bạn
ZALO_BOT_WEBHOOK_SECRET=      # Bot secret — BẠN tự đặt, 8–256 ký tự
```

> Ba secret khác nhau, rất hay nhầm: `ZALO_APP_SECRET` (của ứng dụng), `ZALO_WEBHOOK_SECRET` (*OA Secret Key* trong phần cài đặt webhook), và `ZALO_BOT_WEBHOOK_SECRET` (bạn tự đặt cho bot).

### Lắng nghe

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
| `ZaloWebhookReceived` | **Mọi** sự kiện của OA, kể cả loại package chưa bọc riêng |
| `ZaloMessageReceived` | Người dùng gửi tin nhắn tới OA |
| `ZaloFollowerAdded` | Người dùng quan tâm OA |
| `ZaloFollowerRemoved` | Người dùng bỏ quan tâm |
| `ZaloOaConnected` | OA vừa được cấp quyền |
| `ZaloOaDisconnected` | OA mất kết nối, cần cấp quyền lại |
| `ZaloBotUpdateReceived` | **Mọi** update của Bot, kèm payload gốc |
| `ZaloBotMessageReceived` | Người dùng nhắn cho Bot |

Zalo liên tục thêm loại sự kiện mới, nên `ZaloWebhookReceived` và `ZaloBotUpdateReceived` luôn được bắn cho tất cả — bạn không phải chờ package cập nhật mới bắt được chúng.

### Vài điểm đáng biết

- Route webhook **không** đi qua auth của UI. Zalo không đăng nhập được; chữ ký (OA) hoặc secret header (Bot) là lớp bảo vệ duy nhất và đủ.
- Chưa cấu hình secret thì mọi webhook bị từ chối 401 — **fail-closed**, không im lặng cho qua.
- Secret của Bot đi **nguyên văn** trong header nên nó là mật khẩu chứ không phải chữ ký: bắt buộc HTTPS, và package so sánh bằng `hash_equals`.
- Mặc định xử lý qua queue (`ZALO_WEBHOOK_QUEUE=true`). Zalo có timeout và sẽ gửi lại nếu không nhận được 200 kịp.
- Lỗi trong listener của bạn **không** làm webhook trả 500 — nếu trả, Zalo gửi lại và bạn xử lý trùng.
- Chống trùng nên dựa vào `$e->messageId`, không dựa vào timestamp.
- `chat_id` được lưu **trước** khi bắn event: listener của bạn hỏng cũng không làm mất thứ không lấy lại được.

Bật `ZALO_WEBHOOK_LOG=true` để ghi payload vào `zl_webhook_logs` khi cần debug. Mặc định tắt vì payload chứa nội dung tin nhắn của người dùng.

Package **không** tự gửi cảnh báo khi OA mất kết nối — nó không thể đoán bạn muốn nhận qua Slack, email hay hệ thống giám sát nào. Lắng nghe `ZaloOaDisconnected` và tự xử lý.

## Cấu hình

### Zalo App — chỉ ở env

```dotenv
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_APP_REDIRECT=            # để trống → tự suy ra từ ZALO_UI_PATH
```

App credentials **không bao giờ** vào DB hay UI. Đây là hằng số của môi trường, không phải dữ liệu vận hành.

### Prefix bảng

```dotenv
ZALO_TABLE_PREFIX=zl_
```

> ⚠️ **Chốt giá trị này trước lần migrate đầu tiên.** Đổi sau khi đã migrate sẽ khiến code tìm bảng theo tên mới trong khi DB vẫn giữ tên cũ.

Prefix này *cộng dồn* với prefix của DB connection: `DB_PREFIX=app_` + `zl_` → bảng thật là `app_zl_oas`.

Package tạo 6 bảng: `oas`, `oa_tokens`, `bots`, `bot_chats`, `audit_logs`, `webhook_logs`.

### Scheduler — đừng bỏ qua

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Không có cron này thì token **sẽ chết**. `refresh_token` của Zalo sống khoảng ba tháng và **xoay vòng mỗi lần dùng** — app im lặng quá lâu là mất kết nối vĩnh viễn, phải cấp quyền lại thủ công.

Package đăng ký sẵn `zalo:token:refresh --all` chạy hàng giờ; việc của bạn chỉ là đảm bảo `schedule:run` được gọi.

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

# UI
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

## Giao diện web

Truy cập `https://your-app.com/zalo`.

- **Tổng quan** — sức khoẻ token, Redirect URI và Webhook URL kèm nút copy, cảnh báo những gì đang thiếu
- **Official Account** — danh sách; mỗi OA có trang riêng để sửa, cấp quyền, gửi tin thử
- **Bot** — danh sách; mỗi bot có trang riêng để sửa, cắm/gỡ webhook, xem `chat_id`, gửi tin thử

UI **không cần build step và không cần `vendor:publish`**: CSS viết tay nhúng thẳng vào layout, không JavaScript framework. Muốn sửa giao diện thì `php artisan vendor:publish --tag=zalo-views`.

Đây là công cụ **cài đặt và chẩn đoán** cho developer, không phải công cụ vận hành cho nhân viên CSKH. Nút "Gửi tin thử" gửi tin thật, nhưng nó có mặt để bạn xác nhận luồng chạy được — không phải để nhắn tin hằng ngày.

```dotenv
ZALO_UI_ENABLED=true
ZALO_UI_PATH=zalo
ZALO_UI_USER=admin
ZALO_UI_PASSWORD=             # để trống → UI chỉ chạy được ở local
ZALO_UI_ALLOWED_IPS=          # ví dụ: 113.161.0.0/16,203.0.113.5
```

> ⚠️ **Bắt buộc HTTPS.** Basic Auth gửi credential base64 ở *mọi* request — trên HTTP thuần là truyền plaintext qua đường dây.

Đã có hệ thống auth riêng? Định nghĩa gate, nó sẽ thắng basic auth:

```php
// AppServiceProvider::boot()
Zalo::auth(fn ($request) => $request->user()?->is_admin === true);
```

Token và secret **không bao giờ** hiện đầy đủ trên UI, kể cả với người đã đăng nhập — màn hình này hay bị chụp lại lúc gỡ lỗi.

## Commands

| Command | Việc |
|---|---|
| `zalo` | Trạng thái: OA nào sống, token còn bao lâu |
| `zalo:install` | Cài đặt: kiểm env, publish config, migrate |
| `zalo:doctor` | Chẩn đoán cấu hình kèm cách sửa |
| `zalo:oa:add` | Thêm OA |
| `zalo:oa:list` | Liệt kê OA và trạng thái token |
| `zalo:oa:test {oa}` | Gọi thử API, xác nhận kết nối còn sống |
| `zalo:authorize {oa}` | Cấp quyền, lấy token lần đầu |
| `zalo:token:refresh` | `{oa?}` · `--all` · `--force` |
| `zalo:bot:add` | Thêm Bot (tự kiểm tra token ngay) |
| `zalo:bot:list` | Liệt kê Bot |
| `zalo:bot:test {bot}` | Kiểm tra token bot còn dùng được |
| `zalo:bot:webhook {bot}` | Xem · `--set` · `--delete` · `--url=` |
| `zalo:bot:chats {bot?}` | Liệt kê `chat_id` đã ghi nhận |
| `zalo:bot:send {bot} {chat} {text?}` | Gửi tin thật · `--photo=` · `--sticker=` |

Gặp vấn đề thì chạy `zalo:doctor` trước — nó kiểm credential, redirect URI, bảng, mã hoá, UI, scheduler, từng OA và từng Bot, rồi in ra cách sửa.

## Testing dự án của bạn

`Zalo::fake()` chặn mọi lời gọi tới Zalo và cho phép assert những gì đã gửi:

```php
use FieldVn\Zalo\Laravel\Facades\Zalo;

it('gửi xác nhận khi đặt hàng', function () {
    Zalo::fake();

    $this->post('/don-hang', ['san_pham' => 1]);

    Zalo::assertSentTo('user-1', 'Đơn hàng đã được xác nhận');
});
```

**Không cần OA trong DB, không cần token, không cần giả lập OAuth.**

| Assertion | Việc |
|---|---|
| `assertSentTo($userId, $text?)` | Đã gửi tin nhắn tới người này |
| `assertNotSentTo($userId)` | Chưa gửi cho người này |
| `assertSentVia($slug)` | Đã gửi qua đúng OA đó |
| `assertSent($callback)` | Điều kiện tuỳ ý |
| `assertNotSent($callback)` | |
| `assertNothingSent()` | |
| `assertSentCount($n)` | |
| `sent()` | Collection các request đã ghi |

Đặt response giả khi cần:

```php
Zalo::fake()->push(['error' => -216, 'message' => 'Token hết hạn']);

// code của bạn phải xử lý được ApiException
```

`fake()` chỉ thay **tầng mạng** — message builder, resource và validate payload vẫn chạy code thật. Nghĩa là dựng tin nhắn sai (quá 2000 ký tự, nút không phải HTTPS, quên gọi `->text()`) thì test vẫn bắt được; khác hẳn việc mock cả facade rồi test xanh trong khi production hỏng.

## Điểm mở rộng

Mọi thành phần chính đều thay được mà không phải fork:

```php
// Đổi tầng HTTP
$this->app->bind(FieldVn\Zalo\Contracts\Transport::class, MyTransport::class);

// Đổi nguồn danh sách OA (multi-tenant, config thuần, API nội bộ…)
$this->app->bind(FieldVn\Zalo\Contracts\OaRepository::class, TenantOaRepository::class);
```

`OAChannel`, `BotChannel` và các `Resource` đều dùng `Macroable` — thêm method của riêng bạn thoải mái.

## Lưu ý về `APP_KEY`

Token lưu trong DB được mã hoá bằng `APP_KEY`. **Đổi `APP_KEY` là mất toàn bộ token**, phải cấp quyền lại cho mọi OA.

## Những gì chưa được xác minh

Package này đã chạy thật với Zalo Bot API: URL, hình dạng lỗi, `getMe`, `sendMessage`, webhook. Nhưng một số phần của **OA API** vẫn dựa trên tài liệu chứ chưa gọi thật:

| Chỗ nào | Rủi ro |
|---|---|
| Payload tin có nút (`template_type: button`) | Trung bình |
| `timestamp` webhook tính bằng mili giây | Trung bình |
| Ảnh OA dùng `template_type: media` | Trung bình |
| `ImageMessage::url()` — OA nhận URL thay `attachment_id` | **Cao** — nhiều khả năng chỉ Bot mới nhận URL |
| `refresh_token` sống ~90 ngày | Chỉ biết sau vài tháng |

Danh sách đầy đủ và cách kiểm từng cái nằm ở [`docs/zalo/02-test-thuc-te.md`](https://github.com/field-vn/zalo) trong repo phát triển.

Xác minh được cái nào, xin mở issue — kể cả khi kết quả là *"cái này sai"*. Với những dạng tin chưa chắc chắn, package cố ý dùng `RawMessage` thay vì class có kiểu.

## Phát triển package này

```bash
composer test
composer analyse
composer format
```

## Đóng góp

Xem [CONTRIBUTING.md](CONTRIBUTING.md).

## Bảo mật

Phát hiện lỗ hổng? Xem [SECURITY.md](SECURITY.md) — **đừng** mở public issue.

## License

MIT — xem [LICENSE](LICENSE).
