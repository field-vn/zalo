<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;
use FieldVn\Zalo\Tests\Support\FakeTransport;

function zbsCmdNet(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

function zbsCmdOa(string $slug = 'cskh'): ZaloOa
{
    $oa = ZaloOa::create(['name' => 'CSKH', 'slug' => $slug, 'oa_id' => 'oa-'.$slug, 'is_active' => true]);

    ZaloOaToken::create([
        'oa_id' => $oa->id,
        'access_token' => 'a',
        'refresh_token' => 'r',
        'expires_at' => now()->addHour(),
        'refresh_expires_at' => now()->addDays(80),
    ]);

    return $oa->refresh();
}

it('zalo:zbs:create đọc JSON và POST /template/create', function (): void {
    zbsCmdOa();
    $fake = zbsCmdNet()->push(['error' => 0, 'data' => ['template_id' => '31239', 'status' => 'PENDING_REVIEW']]);

    $path = sys_get_temp_dir().'/zbs-create-'.uniqid().'.json';
    file_put_contents($path, json_encode([
        'template_name' => 'Xác nhận đơn hàng ABC',
        'template_type' => 1,
        'tag' => 1,
        'layout' => ['header' => ['components' => [['TITLE' => ['value' => 'Hi']]]]],
        'tracking_id' => 'tpl-1',
    ]));

    $this->artisan('zalo:zbs:create', ['file' => $path, '--oa' => 'cskh'])
        ->assertSuccessful();

    expect($fake->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/create')
        ->and($fake->lastRequest()['data']['tracking_id'])->toBe('tpl-1');

    @unlink($path);
});

it('zalo:zbs:create CHẶN khi file không tồn tại', function (): void {
    zbsCmdOa();
    $fake = zbsCmdNet();

    $this->artisan('zalo:zbs:create', ['file' => '/khong/co.json', '--oa' => 'cskh'])
        ->assertFailed();

    expect($fake->requests)->toBeEmpty();
});

it('zalo:zbs:status --watch dừng khi đã giao', function (): void {
    zbsCmdOa();
    zbsCmdNet()->push(['error' => 0, 'data' => ['status' => 1, 'delivery_time' => '1600328011517']]);

    $this->artisan('zalo:zbs:status', [
        'message' => 'm-1',
        '--oa' => 'cskh',
        '--watch' => true,
        '--timeout' => 10,
        '--interval' => 1,
    ])->assertSuccessful();
});

it('zalo:zbs:wait dừng khi template ENABLE', function (): void {
    zbsCmdOa();
    zbsCmdNet()->push(['error' => 0, 'data' => ['templateId' => '31239', 'status' => 'ENABLE']]);

    $this->artisan('zalo:zbs:wait', [
        'template' => '31239',
        '--oa' => 'cskh',
        '--timeout' => 10,
        '--interval' => 1,
    ])->assertSuccessful();
});

it('zalo:zbs:templates --id xem chi tiết qua info()', function (): void {
    zbsCmdOa();
    $fake = zbsCmdNet()->push(['error' => 0, 'data' => [
        'templateId' => '31239',
        'templateName' => 'Đơn hàng',
        'status' => 'REJECT',
        'reason' => 'Nội dung không đúng chính sách',
        'previewUrl' => 'https://account.zalo.solutions/preview/abc',
        'listParams' => [
            ['name' => 'order_code', 'require' => true, 'type' => 'STRING', 'maxLength' => 30],
        ],
    ]]);

    $this->artisan('zalo:zbs:templates', ['oa' => 'cskh', '--id' => '31239'])
        ->assertSuccessful();

    expect($fake->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/info/v2')
        ->and($fake->lastRequest()['data'])->toBe(['template_id' => '31239']);
});
