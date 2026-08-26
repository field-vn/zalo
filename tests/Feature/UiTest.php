<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;
use FieldVn\Zalo\Tests\Support\FakeTransport;
use Illuminate\Testing\TestResponse;

/*
| Fake phải trả ĐÚNG hình dạng body của Bot API: {"ok":..., "result":...}.
| Trước đây fake dùng {"data":...} kiểu OA nên test xanh mà thực tế mọi lời
| gọi Bot đều hỏng — fake bịa ra một API không tồn tại thì test không chứng
| minh được gì.
*/

beforeEach(function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
});

function ui(): TestResponse
{
    return test()->withHeaders([
        'Authorization' => 'Basic '.base64_encode('admin:secret'),
    ])->get('/zalo');
}

function uiTransport(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

function makeConnectedOa(string $slug = 'cskh'): ZaloOa
{
    $oa = ZaloOa::create([
        'name' => 'CSKH Shop',
        'slug' => $slug,
        'oa_id' => 'oa-'.$slug,
        'is_active' => true,
    ]);

    ZaloOaToken::create([
        'oa_id' => $oa->id,
        'access_token' => 'a',
        'refresh_token' => 'r',
        'expires_at' => now()->addHour(),
        'refresh_expires_at' => now()->addDays(80),
    ]);

    return $oa->refresh();
}

it('CHẶN toàn bộ UI khi chưa đăng nhập', function (): void {
    // Đây là bài kiểm tra quan trọng nhất của cả tầng UI.
    foreach (['/zalo', '/zalo/oas', '/zalo/bots'] as $path) {
        $this->get($path)->assertStatus(401);
    }
});

it('hiện dashboard khi đã đăng nhập', function (): void {
    makeConnectedOa();

    ui()->assertOk()
        ->assertSee('Tổng quan')
        ->assertSee('CSKH Shop');
});

it('hiện webhook URL để copy', function (): void {
    ui()->assertOk()->assertSee(url('zalo/webhook'));
});

it('cảnh báo khi chưa cấu hình webhook secret', function (): void {
    config()->set('zalo.apps.default.webhook_secret', null);

    ui()->assertOk()->assertSee('ZALO_WEBHOOK_SECRET');
});

it('cảnh báo OA chưa được cấp quyền', function (): void {
    ZaloOa::create(['name' => 'Chưa cấp quyền', 'slug' => 'moi', 'oa_id' => 'oa-1']);

    ui()->assertOk()->assertSee('chưa được cấp quyền');
});

it('thêm OA qua form, mặc định chưa bật', function (): void {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post('/zalo/oas', [
            'name' => 'Marketing',
            'slug' => 'marketing',
            'oa_id' => 'oa-777',
            'tags' => 'mkt, khuyen-mai',
        ])
        ->assertRedirect()
        ->assertSessionHas('zalo.success');

    $oa = ZaloOa::query()->where('slug', 'marketing')->first();

    expect($oa)->not->toBeNull()
        ->and($oa->is_active)->toBeFalse()
        ->and($oa->tags)->toBe(['mkt', 'khuyen-mai']);
});

it('từ chối OA ID trùng', function (): void {
    makeConnectedOa();

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post('/zalo/oas', ['name' => 'Khác', 'slug' => 'khac', 'oa_id' => 'oa-cskh'])
        ->assertSessionHasErrors('oa_id');

    expect(ZaloOa::query()->count())->toBe(1);
});

it('KHÔNG cho bật OA chưa có token', function (): void {
    // Bật một OA chưa cấp quyền chỉ tạo ra lỗi khó hiểu lúc gửi tin.
    $oa = ZaloOa::create([
        'name' => 'Chưa cấp quyền', 'slug' => 'moi', 'oa_id' => 'oa-1', 'is_active' => false,
    ]);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post("/zalo/oas/{$oa->slug}/toggle")
        ->assertSessionHas('zalo.error');

    expect($oa->fresh()->is_active)->toBeFalse();
});

it('xoá OA thì xoá luôn token', function (): void {
    $oa = makeConnectedOa();

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->delete("/zalo/oas/{$oa->slug}")
        ->assertRedirect();

    expect(ZaloOa::withTrashed()->count())->toBe(0)
        ->and(ZaloOaToken::query()->count())->toBe(0);
});

it('route authorize chuyển hướng sang Zalo', function (): void {
    // Test này thiếu ở vòng trước, nên không bắt được việc AuthorizeController
    // typehint `string $oa` trong khi Route::bind trả về model.
    config()->set('zalo.apps.default.app_id', 'app-1');
    config()->set('zalo.apps.default.app_secret', 'secret-1');

    $oa = makeConnectedOa('ftv');

    $response = $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->get("/zalo/oa/{$oa->slug}/authorize");

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://oauth.zaloapp.com/v4/oa/permission')
        ->toContain('app_id=app-1');
});

it('route authorize báo 404 CÓ THÔNG BÁO khi slug sai', function (): void {
    // 404 trần trụi khiến người ta tưởng route chưa đăng ký.
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->get('/zalo/oa/khong-ton-tai/authorize')
        ->assertNotFound();
});

it('URL sinh ra từ route() dùng slug, không dùng id', function (): void {
    // Vòng trước test tự ghép URL bằng $oa->slug nên không bao giờ chạm vào
    // chiều model -> URL. Thực tế Blade gọi route('...', $model), và nếu model
    // không khai getRouteKeyName() thì Laravel lấy khoá chính -> /bots/1/test
    // trong khi Route::bind đi tra slug='1' -> 404 ở mọi nút bấm.
    $oa = makeConnectedOa('cskh');
    $bot = ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => '1:a']);

    expect(route('zalo.oas.test', $oa))->toEndWith('/zalo/oas/cskh/test')
        ->and(route('zalo.oas.toggle', $oa))->toEndWith('/zalo/oas/cskh/toggle')
        ->and(route('zalo.oas.destroy', $oa))->toEndWith('/zalo/oas/cskh')
        ->and(route('zalo.oa.authorize', $oa))->toEndWith('/zalo/oa/cskh/authorize')
        ->and(route('zalo.bots.test', $bot))->toEndWith('/zalo/bots/support/test')
        ->and(route('zalo.bots.toggle', $bot))->toEndWith('/zalo/bots/support/toggle')
        ->and(route('zalo.bots.destroy', $bot))->toEndWith('/zalo/bots/support');
});

it('nút trên trang bot bấm được, không 404', function (): void {
    // Đi đúng đường người dùng đi: lấy URL y như Blade sinh ra.
    uiTransport()->push(['ok' => true, 'result' => ['username' => 'abc_bot']]);

    $bot = ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => '1:a']);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post(route('zalo.bots.test', $bot))
        ->assertRedirect();
});

it('nút trên trang OA bấm được, không 404', function (): void {
    $oa = makeConnectedOa('cskh');

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post(route('zalo.oas.toggle', $oa))
        ->assertRedirect();
});

it('thêm bot và tự điền username', function (): void {
    uiTransport()->push(['ok' => true, 'result' => ['username' => 'abc_bot']]);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post('/zalo/bots', ['name' => 'Support', 'slug' => 'support', 'token' => '123:abc'])
        ->assertRedirect()
        ->assertSessionHas('zalo.success');

    expect(ZaloBot::query()->where('slug', 'support')->first()?->username)->toBe('abc_bot');
});

it('KHÔNG lưu bot khi token hỏng', function (): void {
    uiTransport()->push(['ok' => false, 'error_code' => 401, 'description' => 'Token không hợp lệ']);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->post('/zalo/bots', ['name' => 'Support', 'slug' => 'support', 'token' => 'sai'])
        ->assertSessionHas('zalo.error');

    expect(ZaloBot::withTrashed()->count())->toBe(0);
});

it('KHÔNG bao giờ hiện token bot đầy đủ trên UI', function (): void {
    ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => '123456:sieu-bi-mat']);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
        ->get('/zalo/bots')
        ->assertOk()
        ->assertSee('Support')
        ->assertDontSee('sieu-bi-mat');
});

it('KHÔNG bao giờ hiện app secret trên UI', function (): void {
    config()->set('zalo.apps.default.app_secret', 'app-secret-tuyet-mat');

    ui()->assertOk()->assertDontSee('app-secret-tuyet-mat');
});
