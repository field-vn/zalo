<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Controllers\AuthorizeController;
use FieldVn\Zalo\Laravel\Http\Controllers\BotController;
use FieldVn\Zalo\Laravel\Http\Controllers\DashboardController;
use FieldVn\Zalo\Laravel\Http\Controllers\OaController;
use FieldVn\Zalo\Laravel\Http\Controllers\ZbsController;
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
Route::get('oas/{oa}', [OaController::class, 'show'])->name('zalo.oas.show');
Route::put('oas/{oa}', [OaController::class, 'update'])->name('zalo.oas.update');
Route::post('oas/{oa}/send', [OaController::class, 'send'])->name('zalo.oas.send');
Route::post('oas/{oa}/test', [OaController::class, 'test'])->name('zalo.oas.test');
Route::post('oas/{oa}/toggle', [OaController::class, 'toggle'])->name('zalo.oas.toggle');

Route::get('oas/{oa}/zbs', [ZbsController::class, 'index'])->name('zalo.oas.zbs');
Route::post('oas/{oa}/zbs/send', [ZbsController::class, 'send'])->name('zalo.oas.zbs.send');
Route::post('oas/{oa}/zbs/status', [ZbsController::class, 'status'])->name('zalo.oas.zbs.status');

Route::delete('oas/{oa}', [OaController::class, 'destroy'])->name('zalo.oas.destroy');

Route::get('bots', [BotController::class, 'index'])->name('zalo.bots.index');
Route::post('bots', [BotController::class, 'store'])->name('zalo.bots.store');
Route::get('bots/{bot}', [BotController::class, 'show'])->name('zalo.bots.show');
Route::put('bots/{bot}', [BotController::class, 'update'])->name('zalo.bots.update');
Route::post('bots/{bot}/send', [BotController::class, 'send'])->name('zalo.bots.send');
Route::post('bots/{bot}/webhook', [BotController::class, 'setWebhook'])->name('zalo.bots.webhook.set');
Route::delete('bots/{bot}/webhook', [BotController::class, 'deleteWebhook'])->name('zalo.bots.webhook.delete');
Route::post('bots/{bot}/test', [BotController::class, 'test'])->name('zalo.bots.test');
Route::post('bots/{bot}/toggle', [BotController::class, 'toggle'])->name('zalo.bots.toggle');
Route::delete('bots/{bot}', [BotController::class, 'destroy'])->name('zalo.bots.destroy');

Route::get('oa/{oa}/authorize', [AuthorizeController::class, 'redirect'])
    ->name('zalo.oa.authorize');

Route::get('oauth/callback', [AuthorizeController::class, 'callback'])
    ->name('zalo.oauth.callback');
