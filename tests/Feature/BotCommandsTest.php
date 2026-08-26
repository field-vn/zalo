<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| Fake phải trả ĐÚNG hình dạng body của Bot API: {"ok":..., "result":...}.
| Trước đây fake dùng {"data":...} kiểu OA nên test xanh mà thực tế mọi lời
| gọi Bot đều hỏng — fake bịa ra một API không tồn tại thì test không chứng
| minh được gì.
*/

function botTransport(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

it('thêm bot và tự điền username từ getMe', function (): void {
    botTransport()->push(['ok' => true, 'result' => ['username' => 'abc_support_bot']]);

    $this->artisan('zalo:bot:add', [
        '--name' => 'Support',
        '--slug' => 'support',
        '--token' => '123456:abcdef',
    ])->assertSuccessful();

    $bot = ZaloBot::query()->where('slug', 'support')->first();

    expect($bot)->not->toBeNull()
        ->and($bot->token)->toBe('123456:abcdef')
        ->and($bot->username)->toBe('abc_support_bot')
        ->and($bot->is_active)->toBeTrue();
});

it('XOÁ bot vừa tạo khi token không dùng được', function (): void {
    // Giữ lại bản ghi hỏng chỉ làm zalo:bot:list nhiễu và khiến người dùng
    // tưởng đã thêm thành công.
    botTransport()->push(['ok' => false, 'error_code' => 401, 'description' => 'Token không hợp lệ']);

    $this->artisan('zalo:bot:add', [
        '--name' => 'Support',
        '--slug' => 'support',
        '--token' => 'token-sai',
    ])->assertFailed();

    expect(ZaloBot::withTrashed()->count())->toBe(0);
});

it('cho phép bỏ qua kiểm tra token', function (): void {
    botTransport();

    $this->artisan('zalo:bot:add', [
        '--name' => 'Support',
        '--slug' => 'support',
        '--token' => '123456:abcdef',
        '--skip-verify' => true,
    ])->assertSuccessful();

    expect(ZaloBot::query()->count())->toBe(1);
});

it('từ chối slug đã tồn tại', function (): void {
    botTransport();
    ZaloBot::create(['name' => 'A', 'slug' => 'support', 'token' => 'x']);

    $this->artisan('zalo:bot:add', [
        '--name' => 'B',
        '--slug' => 'support',
        '--token' => '123456:abcdef',
    ])->assertFailed();

    expect(ZaloBot::query()->count())->toBe(1);
});

it('không bao giờ hiện token đầy đủ khi liệt kê', function (): void {
    // Output terminal hay bị copy nguyên vào issue công khai.
    ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => '123456:sieu-bi-mat']);

    $this->artisan('zalo:bot:list')
        ->doesntExpectOutputToContain('sieu-bi-mat')
        ->assertSuccessful();
});

it('che token đúng định dạng', function (): void {
    $bot = new ZaloBot(['token' => '123456:abcdefghijkl']);

    expect($bot->maskedToken())->toBe('123456:••••••••ijkl');
});

it('bot:test báo lỗi khi token hỏng', function (): void {
    botTransport()->push(['ok' => false, 'error_code' => 401, 'description' => 'Token không hợp lệ']);
    ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => 'x']);

    $this->artisan('zalo:bot:test', ['bot' => 'support'])->assertFailed();
});

it('bot:test thành công khi token còn dùng được', function (): void {
    botTransport()->push(['ok' => true, 'result' => ['username' => 'abc_support_bot']]);
    ZaloBot::create(['name' => 'Support', 'slug' => 'support', 'token' => 'x']);

    $this->artisan('zalo:bot:test', ['bot' => 'support'])->assertSuccessful();
});
