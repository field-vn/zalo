<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Route webhook.
|
| CỐ Ý không có middleware nào: người gọi là Zalo, không phải người dùng.
|  - không `web`  → không session, không CSRF (Zalo không có token CSRF)
|  - không auth   → Zalo không đăng nhập được
|
| Bảo vệ duy nhất là chữ ký X-ZEvent-Signature, kiểm tra trong controller.
*/

Route::post('/', WebhookController::class)->name('zalo.webhook');
