<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Models\ZaloOaToken;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| ZBS tiêu tiền thật, nên phần lớn test ở đây khoá các nhánh CHẶN TRƯỚC KHI
| GỌI MẠNG và khoá mặc định development. Mọi URL đi qua route() chứ không ghép
| tay — vòng trước đã có bài học về chuyện đó.
*/

beforeEach(function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
});

function zbsAuth(): array
{
    return ['Authorization' => 'Basic '.base64_encode('admin:secret')];
}

function zbsNet(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

function zbsOa(string $slug = 'cskh'): ZaloOa
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

/** Danh sách template + quota — trang index gọi hai lượt. */
function zbsListing(FakeTransport $fake, array $templates): FakeTransport
{
    return $fake
        ->push(['error' => 0, 'data' => $templates])
        ->push(['error' => 0, 'data' => ['dailyQuota' => 500, 'remainingQuota' => 499]]);
}

it('CHẶN trang ZBS khi chưa đăng nhập', function (): void {
    $oa = zbsOa();

    test()->get(route('zalo.oas.zbs', $oa))->assertStatus(401);
});

it('hiện mẫu tin kèm trạng thái', function (): void {
    zbsListing(zbsNet(), [
        ['templateId' => 629101, 'templateName' => 'Thông báo lịch hẹn', 'status' => 'PENDING_REVIEW'],
    ]);

    test()->withHeaders(zbsAuth())->get(route('zalo.oas.zbs', zbsOa()))
        ->assertOk()
        ->assertSee('629101')
        ->assertSee('PENDING_REVIEW');
});

it('KHÔNG làm hỏng cả trang khi Zalo từ chối', function (): void {
    // OA chưa được cấp quyền ZBS là chuyện rất thường. Trang phải còn đọc được
    // và nói rõ phải làm gì, thay vì ném ra trang lỗi 500.
    zbsNet()->push(['error' => -135, 'message' => 'No permission to send ZNS']);

    test()->withHeaders(zbsAuth())->get(route('zalo.oas.zbs', zbsOa()))
        ->assertOk()
        ->assertSee('-135')
        ->assertSee('zalo.solutions');
});

it('dựng ô nhập theo tham số của mẫu đã chọn', function (): void {
    zbsListing(zbsNet(), [[
        'templateId' => 629101,
        'templateName' => 'Lịch hẹn',
        'status' => 'ENABLE',
        'listParams' => [
            ['name' => 'customer_name', 'require' => true, 'type' => 'STRING', 'maxLength' => 30],
            ['name' => 'location', 'require' => true, 'type' => 'STRING', 'maxLength' => 200],
        ],
    ]]);

    test()->withHeaders(zbsAuth())
        ->get(route('zalo.oas.zbs', ['oa' => zbsOa(), 'template' => '629101']))
        ->assertOk()
        ->assertSee('params[customer_name]', false)
        ->assertSee('params[location]', false);
});

it('cho nhập JSON tay khi mẫu chưa khai tham số', function (): void {
    // Mẫu PENDING_REVIEW trả listParams rỗng dù giao diện Zalo đã hiện đủ
    // tham số. Không có lối thoát này thì người dùng kẹt cho tới lúc duyệt xong.
    zbsListing(zbsNet(), [[
        'templateId' => 629101,
        'templateName' => 'Lịch hẹn',
        'status' => 'PENDING_REVIEW',
        'listParams' => [],
    ]]);

    test()->withHeaders(zbsAuth())
        ->get(route('zalo.oas.zbs', ['oa' => zbsOa(), 'template' => '629101']))
        ->assertOk()
        ->assertSee('name="raw"', false);
});

/*
| Gửi tin.
*/

it('gửi ở chế độ development khi không tick production', function (): void {
    $fake = zbsNet()->push(['error' => 0, 'data' => ['msg_id' => 'm-1']]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'params' => ['customer_name' => 'Triều'],
        ])
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['data'])->toBe([
        'phone' => '84987654321',
        'template_id' => '629101',
        'template_data' => ['customer_name' => 'Triều'],
        'mode' => 'development',
    ]);
});

it('KHÔNG gửi production khi chưa tick ô xác nhận', function (): void {
    // Một cú bấm nhầm không nên đủ để trừ số dư.
    $fake = zbsNet();

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'params' => ['customer_name' => 'Triều'],
            'production' => '1',
        ])
        ->assertSessionHas('zalo.error');

    expect($fake->requests)->toBeEmpty();
});

it('gửi production khi đã tick xác nhận', function (): void {
    $fake = zbsNet()->push(['error' => 0, 'data' => ['msg_id' => 'm-1']]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'params' => ['customer_name' => 'Triều'],
            'production' => '1',
            'confirm' => '1',
        ])
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['data']['mode'])->toBe('production');
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi số điện thoại sai', function (): void {
    $fake = zbsNet();

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => 'khong-phai-so',
            'template_id' => '629101',
            'params' => ['customer_name' => 'Triều'],
        ])
        ->assertSessionHas('zalo.error');

    expect($fake->requests)->toBeEmpty();
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi chưa điền tham số nào', function (): void {
    $fake = zbsNet();

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'params' => ['customer_name' => '  '],
        ])
        ->assertSessionHas('zalo.error');

    expect($fake->requests)->toBeEmpty();
});

it('nhận tham số dạng JSON khi mẫu chưa khai tham số', function (): void {
    $fake = zbsNet()->push(['error' => 0, 'data' => ['msg_id' => 'm-1']]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'raw' => '{"customer_name":"Triều","time":"18:00 20-08-2026"}',
        ])
        ->assertSessionHas('zalo.success');

    expect($fake->lastRequest()['data']['template_data'])
        ->toBe(['customer_name' => 'Triều', 'time' => '18:00 20-08-2026']);
});

it('báo lỗi rõ khi JSON hỏng, không gọi mạng', function (): void {
    $fake = zbsNet();

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'raw' => '{hong',
        ])
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, 'JSON'));

    expect($fake->requests)->toBeEmpty();
});

it('dịch mã lỗi thành câu nói được phải làm gì', function (): void {
    zbsNet()->push(['error' => -127, 'message' => 'Test template messages can only be sent to admin']);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.send', zbsOa()), [
            'phone' => '0987654321',
            'template_id' => '629101',
            'params' => ['customer_name' => 'Triều'],
        ])
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, 'quản trị viên'));
});

/*
| Tra trạng thái giao tin.
*/

it('báo trạng thái 0 là CHƯA GIAO chứ không phải thành công', function (): void {
    // Zalo nhận tin và giao được tin là hai chuyện. Báo 0 như tin vui thì
    // người dùng ngồi chờ một tin không bao giờ tới.
    zbsNet()->push(['error' => 0, 'data' => ['status' => 0, 'message' => 'not delivered']]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.status', zbsOa()), ['message_id' => 'm-1'])
        ->assertSessionHas('zalo.error')
        ->assertSessionMissing('zalo.success');
});

it('báo trạng thái 1 là đã giao', function (): void {
    zbsNet()->push(['error' => 0, 'data' => ['status' => 1, 'delivery_time' => '1600328011517']]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.status', zbsOa()), ['message_id' => 'm-1'])
        ->assertSessionHas('zalo.success');
});

it('báo trạng thái -1 là không tìm thấy tin', function (): void {
    zbsNet()->push(['error' => 0, 'data' => ['status' => -1]]);

    test()->withHeaders(zbsAuth())
        ->post(route('zalo.oas.zbs.status', zbsOa()), ['message_id' => 'sai'])
        ->assertSessionHas('zalo.error', fn (string $m): bool => str_contains($m, 'Không tìm thấy'));
});
