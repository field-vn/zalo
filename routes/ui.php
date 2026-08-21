<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Controllers\AuthorizeController;
use Illuminate\Support\Facades\Route;

/*
| Route của UI. Toàn bộ nằm sau middleware Authorize (basic auth + IP
| allowlist, fail-closed). Callback của Zalo cũng nằm trong nhóm này —
| nó chạy trong trình duyệt của chính admin đang cấp quyền, nên credential
| đã được trình duyệt ghi nhớ và IP không đổi.
*/

Route::get('oa/{oa}/authorize', [AuthorizeController::class, 'redirect'])
    ->name('zalo.oa.authorize');

Route::get('oauth/callback', [AuthorizeController::class, 'callback'])
    ->name('zalo.oauth.callback');
