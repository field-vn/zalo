<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Laravel\Models\ZaloOa;

function makeOa(array $attributes = []): ZaloOa
{
    return ZaloOa::create(array_merge([
        'name' => 'CSKH Shop',
        'slug' => 'cskh',
        'oa_id' => '1234567890',
        'is_active' => true,
    ], $attributes));
}

it('resolve OA theo slug', function (): void {
    makeOa();

    $channel = app(Factory::class)->oa('cskh');

    expect($channel)->toBeInstanceOf(OAChannel::class)
        ->and($channel->key())->toBe('cskh');
});

it('resolve OA theo id', function (): void {
    $oa = makeOa();

    expect(app(Factory::class)->oa($oa->id)->key())->toBe('cskh');
});

it('dùng OA active đầu tiên làm mặc định', function (): void {
    makeOa(['slug' => 'a', 'oa_id' => '1', 'is_active' => false]);
    makeOa(['slug' => 'b', 'oa_id' => '2']);

    expect(app(Factory::class)->oa()->key())->toBe('b');
});

it('cache channel đã resolve trong cùng một request', function (): void {
    makeOa();
    $zalo = app(Factory::class);

    expect($zalo->oa('cskh'))->toBe($zalo->oa('cskh'));
});

it('báo lỗi kèm hướng dẫn khi không tìm thấy OA', function (): void {
    expect(fn () => app(Factory::class)->oa('khong-ton-tai'))
        ->toThrow(ConfigurationException::class, 'zalo:oa:list');
});

it('từ chối OA đang bị tắt thay vì im lặng bỏ qua', function (): void {
    makeOa(['is_active' => false]);

    expect(fn () => app(Factory::class)->oa('cskh'))
        ->toThrow(ConfigurationException::class, 'đang bị tắt');
});

it('báo lỗi rõ ràng khi App credentials chưa cấu hình', function (): void {
    config()->set('zalo.apps.default.app_id', null);
    makeOa();

    expect(fn () => app(Factory::class)->oa('cskh'))
        ->toThrow(ConfigurationException::class, 'ZALO_APP_ID');
});

it('lọc OA theo tag để phân phối có chủ đích', function (): void {
    makeOa(['slug' => 'cskh', 'oa_id' => '1', 'tags' => ['cskh']]);
    makeOa(['slug' => 'mkt', 'oa_id' => '2', 'tags' => ['marketing']]);

    $channels = app(Factory::class)->oas(
        fn (ZaloOa $oa): bool => in_array('cskh', $oa->tags ?? [], true)
    );

    expect($channels)->toHaveCount(1)
        ->and($channels->first()->key())->toBe('cskh');
});

it('OA mới luôn có app_key mặc định', function (): void {
    // Default của migration là default của DB — instance vừa create() xong vẫn
    // mang null nếu model không tự khai. Authorizer đọc app_key ngay sau khi
    // tạo nên thiếu cái này là vỡ ngay ở bước cấp quyền đầu tiên.
    expect(makeOa()->app_key)->toBe('default');
});

it('chỉ liệt kê OA đang active', function (): void {
    makeOa(['slug' => 'a', 'oa_id' => '1']);
    makeOa(['slug' => 'b', 'oa_id' => '2', 'is_active' => false]);

    expect(app(Factory::class)->availableOas())->toHaveCount(1);
});
