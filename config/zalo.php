<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prefix bảng
    |--------------------------------------------------------------------------
    |
    | CHỐT GIÁ TRỊ NÀY TRƯỚC KHI MIGRATE LẦN ĐẦU. Đổi sau khi đã migrate sẽ
    | khiến code tìm bảng theo tên mới trong khi DB vẫn giữ tên cũ.
    | Nếu buộc phải đổi: php artisan zalo:table:rename --from=zl_ --to=moi_
    |
    | Lưu ý: prefix này cộng dồn với prefix của DB connection.
    | DB_PREFIX=app_ + zl_ => bảng thật là app_zl_oas
    |
    */

    'table_prefix' => env('ZALO_TABLE_PREFIX', 'zl_'),

    /*
    |--------------------------------------------------------------------------
    | Zalo App
    |--------------------------------------------------------------------------
    |
    | App credentials CHỈ nằm ở env — không bao giờ vào DB hay UI.
    | Đây là hằng số của môi trường, không phải dữ liệu vận hành.
    |
    | Một App có thể liên kết nhiều OA. Nếu bạn quản OA của nhiều khách qua
    | nhiều App khác nhau, thêm key vào mảng này rồi trỏ cột `app_key` của OA.
    |
    */

    'default_app' => env('ZALO_APP_KEY', 'default'),

    'apps' => [
        'default' => [
            'app_id' => env('ZALO_APP_ID'),
            'app_secret' => env('ZALO_APP_SECRET'),

            // KHÁC app_secret. Đây là "OA Secret Key" trong phần cài đặt
            // webhook của ứng dụng, dùng để xác thực X-ZEvent-Signature.
            'webhook_secret' => env('ZALO_WEBHOOK_SECRET'),

            'redirect' => env('ZALO_APP_REDIRECT')
                ?: '/'.trim((string) env('ZALO_UI_PATH', 'zalo'), '/').'/oauth/callback',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ZBS Template Message
    |--------------------------------------------------------------------------
    |
    | Gửi tin theo mẫu tới SỐ ĐIỆN THOẠI — kênh duy nhất tới được người chưa
    | từng tương tác với OA. Mỗi tin TÍNH PHÍ, trừ vào số dư ZBS Account.
    |
    | MẶC ĐỊNH LÀ `development`, và đó là chủ ý:
    |   development → chỉ gửi tới quản trị viên OA/App, miễn phí
    |   production  → gửi cho khách thật, mất tiền
    |
    | Quên đổi sang production thì tin không tới khách — khó chịu nhưng phát
    | hiện ngay. Ngược lại, mặc định production mà quên thì một vòng lặp sai
    | lúc dev là một hoá đơn thật, và bạn chỉ biết khi nhận sao kê.
    |
    */

    'zbs' => [
        'mode' => env('ZALO_ZBS_MODE', 'development'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zalo Bot
    |--------------------------------------------------------------------------
    |
    | KHÁC HẲN webhook secret của OA:
    |   - OA:  Zalo CẤP cho bạn, dùng để tính X-ZEvent-Signature
    |   - Bot: bạn TỰ ĐẶT, Zalo gửi trả lại nguyên văn ở header mỗi lần gọi
    |
    | Zalo BẮT BUỘC phải có giá trị này khi gọi setWebhook — để rỗng thì bị
    | từ chối với "Bad request: The secret_token must not be empty".
    |
    | Sinh một chuỗi ngẫu nhiên đủ dài, ví dụ:
    |   php -r "echo bin2hex(random_bytes(24));"
    |
    */

    'bot' => [
        'webhook_secret' => env('ZALO_BOT_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint
    |--------------------------------------------------------------------------
    */

    'endpoints' => [
        'oa' => 'https://openapi.zalo.me',
        'oauth' => 'https://oauth.zaloapp.com/v4',
        'oauth_consent' => 'https://oauth.zaloapp.com/v4/oa/permission',
        'bot' => 'https://bot-api.zapps.me/bot',
        'zbs' => 'https://business.openapi.zalo.me',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('ZALO_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('ZALO_HTTP_CONNECT_TIMEOUT', 5),
        'retry' => [
            'times' => (int) env('ZALO_HTTP_RETRY', 3),
            'sleep' => 200,                       // ms, nhân đôi mỗi lần
            'on' => [429, 500, 502, 503, 504],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | Cần cron gọi `php artisan schedule:run` trên server, nếu không token
    | sẽ KHÔNG tự refresh. Kiểm tra bằng: php artisan zalo:doctor
    |
    | rotate_before là phần quan trọng nhất: refresh_token sống ~3 tháng và
    | xoay vòng mỗi lần dùng. App im lặng quá lâu thì token chết vĩnh viễn.
    |
    */

    'scheduler' => [
        'enabled' => (bool) env('ZALO_SCHEDULER', true),
        'refresh_before' => 15,   // phút — refresh access_token trước khi hết hạn
        'rotate_before' => 14,   // ngày  — refresh cưỡng bức để xoay refresh_token
        'max_failures' => 3,    // lỗi liên tiếp thì đánh dấu OA ngắt kết nối
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    |
    | BẮT BUỘC HTTPS. Basic Auth gửi credential base64 ở MỌI request — trên
    | HTTP thuần thì coi như truyền plaintext qua đường dây.
    |
    | Chưa đặt ZALO_UI_PASSWORD => UI chỉ chạy được ở môi trường local.
    | Đã định nghĩa Zalo::auth() => gate đó thắng, bỏ qua basic auth.
    |
    */

    'ui' => [
        'enabled' => (bool) env('ZALO_UI_ENABLED', true),
        'path' => env('ZALO_UI_PATH', 'zalo'),
        'middleware' => ['web'],
        'user' => env('ZALO_UI_USER'),
        'password' => env('ZALO_UI_PASSWORD'),
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ZALO_UI_ALLOWED_IPS'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Secret nằm trong `apps.*.webhook_secret` vì nó thuộc về từng ứng dụng,
    | không phải cấu hình toàn cục.
    |
    | Route webhook KHÔNG đi qua middleware của UI — Zalo gọi vào, không phải
    | người dùng. Bảo vệ duy nhất và đủ là chữ ký X-ZEvent-Signature.
    |
    */

    'webhook' => [
        'enabled' => (bool) env('ZALO_WEBHOOK_ENABLED', true),
        'path' => env('ZALO_WEBHOOK_PATH', 'zalo/webhook'),

        // Xử lý qua queue: Zalo có timeout và sẽ gửi lại nếu không nhận được
        // 200 kịp. Việc nặng phải đẩy ra khỏi vòng đời request.
        'queue' => (bool) env('ZALO_WEBHOOK_QUEUE', true),
        'queue_name' => env('ZALO_WEBHOOK_QUEUE_NAME'),

        // Cửa sổ chấp nhận timestamp (giây). 0 = tắt kiểm tra.
        'tolerance' => (int) env('ZALO_WEBHOOK_TOLERANCE', 300),

        // Ghi payload vào zl_webhook_logs để soi khi debug. Tắt mặc định vì
        // payload chứa nội dung tin nhắn của người dùng.
        'log' => (bool) env('ZALO_WEBHOOK_LOG', false),
    ],

];
