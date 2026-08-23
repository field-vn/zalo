<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Controllers\BotWebhookController;
use FieldVn\Zalo\Laravel\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Route webhook.
|
| CỐ Ý không có middleware nào: người gọi là Zalo, không phải người dùng.
|  - không `web`  → không session, không CSRF (Zalo không có token CSRF)
|  - không auth   → Zalo không đăng nhập được
|
| Bảo vệ duy nhất nằm trong controller:
|  - OA  → chữ ký X-ZEvent-Signature
|  - Bot → secret nguyên văn ở header X-Bot-Api-Secret-Token
|
| Hai cơ chế khác hẳn nhau nên tách hẳn hai controller, không rẽ nhánh.
*/

Route::post('/', WebhookController::class)->name('zalo.webhook');

/*
| Mỗi bot một URL: payload Zalo gửi không kèm định danh bot nào, nên đường
| dẫn là cách duy nhất để biết update thuộc về bot nào.
|
| Lấy URL của từng bot: php artisan zalo:bot:webhook <slug>
*/
Route::post('bot/{bot}', BotWebhookController::class)->name('zalo.webhook.bot');
