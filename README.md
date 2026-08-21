# Zalo

[![Tests](https://github.com/field-vn/zalo/actions/workflows/tests.yml/badge.svg)](https://github.com/field-vn/zalo/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![Downloads](https://img.shields.io/packagist/dt/field-vn/zalo.svg)](https://packagist.org/packages/field-vn/zalo)
[![License](https://img.shields.io/packagist/l/field-vn/zalo.svg)](LICENSE)

SDK Laravel cho **Zalo Official Account** và **Zalo Bot**. Quản lý nhiều OA, tự refresh token, kèm UI cấu hình.

> ⚠️ **Đang phát triển (0.x)** — API còn có thể thay đổi. Chưa nên dùng cho production.

## Vì sao dùng package này

- **Nhiều OA trong một app** — `Zalo::oa('cskh')`, `Zalo::oa('marketing')`
- **Token tự refresh** — kể cả việc *xoay* `refresh_token` trước khi nó hết hạn, thứ khiến hầu hết tích hợp Zalo tự viết chết sau 3 tháng im lặng
- **Message là object, không phải mảng thô** — IDE gợi ý được, payload validate trước khi gửi
- **`zalo:doctor`** — chẩn đoán cấu hình, chỉ thẳng cách sửa

## Yêu cầu

- PHP 8.2+
- Laravel 10, 11 hoặc 12

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

Lệnh này kiểm tra env, publish config, migrate, và cảnh báo những thứ bạn dễ quên (cron cho scheduler, credential cho UI, HTTPS).

## Kết nối OA đầu tiên

```bash
php artisan zalo:oa:add
```

Nhập tên, slug và OA ID (lấy ở trang quản trị Zalo OA), rồi lệnh sẽ hỏi có cấp quyền luôn không.

Luồng cấp quyền in ra một link — mở bằng tài khoản **admin của OA**, bấm đồng ý. Nếu callback truy cập được từ Internet thì token tự lưu; nếu đang chạy localhost, copy giá trị `code` trên thanh địa chỉ dán vào terminal.

> **Redirect URI phải khớp CHÍNH XÁC** giá trị khai trong Zalo Developers. Lệnh `zalo:authorize` in sẵn giá trị đúng để bạn copy.

Kiểm tra bất cứ lúc nào:

```bash
php artisan zalo:oa:list
php artisan zalo:oa:test cskh
```

## Cấu hình

### Zalo App — chỉ ở env

```dotenv
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_APP_REDIRECT=            # để trống → tự suy ra từ ZALO_UI_PATH
```

App credentials **không bao giờ** vào DB hay UI. Đây là hằng số của môi trường, không phải dữ liệu vận hành.

### UI

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

### Prefix bảng

```dotenv
ZALO_TABLE_PREFIX=zl_
```

> ⚠️ **Chốt giá trị này trước lần migrate đầu tiên.** Đổi sau khi đã migrate sẽ khiến code tìm bảng theo tên mới trong khi DB vẫn giữ tên cũ.

Prefix này *cộng dồn* với prefix của DB connection: `DB_PREFIX=app_` + `zl_` → bảng thật là `app_zl_oas`.

### Scheduler — đừng bỏ qua

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Không có cron này thì token **sẽ chết**. `refresh_token` của Zalo sống ~3 tháng và xoay vòng mỗi lần dùng — app im lặng quá lâu là mất kết nối vĩnh viễn, phải cấp quyền lại thủ công.

Package đăng ký sẵn `zalo:token:refresh --all` chạy hàng giờ; việc của bạn chỉ là đảm bảo `schedule:run` được gọi.

## Sử dụng

```php
use FieldVn\Zalo\Laravel\Facades\Zalo;
use FieldVn\Zalo\Core\Channels\OA\Messages\Button;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;

// Gửi nhanh
Zalo::oa('cskh')->messages()->text($userId, 'Đơn hàng đã được xác nhận');

// Có nút bấm
Zalo::oa('cskh')->messages()->send(
    TextMessage::to($userId)
        ->text('Đơn hàng #1234 đang giao')
        ->button(Button::url('Theo dõi', 'https://shop.vn/don/1234'))
);

Zalo::oa('cskh')->users()->profile($userId);
Zalo::oa('cskh')->tags()->assign($userId, 'khach-vip');

// Bot — nhánh độc lập, không dính Zalo App
Zalo::bot('support')->messages()->send($chatId, 'pong');
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

Helper toàn cục, nếu bạn thích:

```php
zalo_oa('cskh')->messages()->text($userId, 'Xin chào');
zalo_bot('support')->messages()->send($chatId, 'pong');
```

### Endpoint chưa được bọc

```php
Zalo::oa('cskh')->request()->get('/v3.0/oa/duong-dan-moi', ['param' => 'x']);
```

## Commands

| Command | Việc |
|---|---|
| `zalo` | Trạng thái: OA nào sống, token còn bao lâu |
| `zalo:install` | Cài đặt |
| `zalo:doctor` | Chẩn đoán cấu hình kèm cách sửa |
| `zalo:oa:add` | Thêm OA |
| `zalo:oa:list` | Liệt kê OA và trạng thái token |
| `zalo:oa:test {oa}` | Gọi thử API, xác nhận kết nối còn sống |
| `zalo:authorize {oa}` | Cấp quyền, lấy token lần đầu |
| `zalo:token:refresh` | `{oa?}` · `--all` · `--force` |

## Events

```php
use FieldVn\Zalo\Laravel\Events\ZaloOaConnected;
use FieldVn\Zalo\Laravel\Events\ZaloOaDisconnected;
```

`ZaloOaDisconnected` bắn khi OA mất kết nối và cần cấp quyền lại thủ công. Package **không** tự gửi cảnh báo — nó không thể đoán bạn muốn nhận qua Slack, email hay hệ thống giám sát nào.

## Điểm mở rộng

Mọi thành phần chính đều thay được mà không phải fork:

```php
// Đổi tầng HTTP
$this->app->bind(FieldVn\Zalo\Contracts\Transport::class, MyTransport::class);

// Đổi nguồn danh sách OA (multi-tenant, config thuần, API nội bộ…)
$this->app->bind(FieldVn\Zalo\Contracts\OaRepository::class, TenantOaRepository::class);
```

`OAChannel` và các `Resource` đều dùng `Macroable` — thêm method của riêng bạn thoải mái.

## Lưu ý về `APP_KEY`

Token lưu trong DB được mã hoá bằng `APP_KEY`. **Đổi `APP_KEY` là mất toàn bộ token**, phải cấp quyền lại cho mọi OA.

## Testing

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
