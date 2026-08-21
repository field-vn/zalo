<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Controllers\AuthorizeController;
use FieldVn\Zalo\Laravel\Http\Controllers\BotController;
use FieldVn\Zalo\Laravel\Http\Controllers\DashboardController;
use FieldVn\Zalo\Laravel\Http\Controllers\OaController;
use Illuminate\Support\Facades\Route;

/*
| Route của UI. Toàn bộ nằm sau middleware Authorize (basic auth + IP
| allowlist, fail-closed). Callback của Zalo cũng nằm trong nhóm này —
| nó chạy trong trình duyệt của chính admin đang cấp quyền, nên credential
| đã được trình duyệt ghi nhớ và IP không đổi.
*/

Route::get('/', DashboardController::class)->name('zalo.dashboard');

Route::get('oas', [OaController::class, 'index'])->name('zalo.oas.index');
Route::post('oas', [OaController::class, 'store'])->name('zalo.oas.store');
Route::post('oas/{oa}/test', [OaController::class, 'test'])->name('zalo.oas.test');
Route::post('oas/{oa}/toggle', [OaController::class, 'toggle'])->name('zalo.oas.toggle');
Route::delete('oas/{oa}', [OaController::class, 'destroy'])->name('zalo.oas.destroy');

Route::get('bots', [BotController::class, 'index'])->name('zalo.bots.index');
Route::post('bots', [BotController::class, 'store'])->name('zalo.bots.store');
Route::post('bots/{bot}/test', [BotController::class, 'test'])->name('zalo.bots.test');
Route::post('bots/{bot}/toggle', [BotController::class, 'toggle'])->name('zalo.bots.toggle');
Route::delete('bots/{bot}', [BotController::class, 'destroy'])->name('zalo.bots.destroy');

Route::get('oa/{oa}/authorize', [AuthorizeController::class, 'redirect'])
    ->name('zalo.oa.authorize');

Route::get('oauth/callback', [AuthorizeController::class, 'callback'])
    ->name('zalo.oauth.callback');
