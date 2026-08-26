<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| ZBS tính phí mỗi tin, nên phần lớn test ở đây khoá lại các nhánh CHẶN TRƯỚC
| KHI GỌI MẠNG, và khoá mặc định development.
*/

function zbs(FakeTransport $t, string $mode = ZbsResource::MODE_DEVELOPMENT): ZbsResource
{
    return new ZbsResource(
        transport: $t,
        headers: static fn (): array => ['access_token' => 'tok'],
        baseUrl: 'https://business.openapi.zalo.me',
        mode: $mode,
    );
}

it('gửi đúng endpoint và payload', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['msg_id' => 'm-1']]);

    zbs($t)->send('0987654321', 'tpl-1', ['otp' => '123456']);

    $req = $t->lastRequest();

    expect($req['method'])->toBe('POST')
        ->and($req['url'])->toBe('https://business.openapi.zalo.me/message/template')
        ->and($req['data'])->toBe([
            'phone' => '84987654321',
            'template_id' => 'tpl-1',
            'template_data' => ['otp' => '123456'],
            'mode' => 'development',
        ]);
});

it('MẶC ĐỊNH là development — không tự tiêu tiền', function (): void {
    // Quên đổi sang production thì tin không tới khách, phát hiện ngay.
    // Ngược lại, mặc định production mà quên thì biết khi nhận sao kê.
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->send('0987654321', 'tpl-1', ['x' => 'y']);

    expect($t->lastRequest()['data']['mode'])->toBe('development');
});

it('chuẩn hoá số điện thoại trước khi gửi', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->send('+84 987 654 321', 'tpl-1', ['x' => 'y']);

    expect($t->lastRequest()['data']['phone'])->toBe('84987654321');
});

it('ép mọi giá trị template_data về chuỗi', function (): void {
    // Truyền int cho mã OTP hay số đơn là chuyện tự nhiên trong PHP, nhưng
    // Zalo yêu cầu chuỗi.
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->send('0987654321', 'tpl-1', ['otp' => 123456, 'don' => 42]);

    expect($t->lastRequest()['data']['template_data'])->toBe(['otp' => '123456', 'don' => '42']);
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi số điện thoại sai', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->send('khong-phai-so', 'tpl-1', ['x' => 'y']))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi thiếu template_id', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->send('0987654321', '  ', ['x' => 'y']))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi template_data rỗng', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->send('0987654321', 'tpl-1', []))
        ->toThrow(ConfigurationException::class, 'template_data rỗng');

    expect($t->requests)->toBeEmpty();
});

it('từ chối mode lạ', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->send('0987654321', 'tpl-1', ['x' => 'y'], mode: 'staging'))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});

it('chỉ kèm tracking_id khi có', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->send('0987654321', 'tpl-1', ['x' => 'y']);
    expect($t->lastRequest()['data'])->not->toHaveKey('tracking_id');

    $t->push(['error' => 0, 'data' => []]);
    zbs($t)->send('0987654321', 'tpl-1', ['x' => 'y'], trackingId: 'don-123');
    expect($t->lastRequest()['data']['tracking_id'])->toBe('don-123');
});

it('production phải được chọn tường minh', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t, ZbsResource::MODE_PRODUCTION)->send('0987654321', 'tpl-1', ['x' => 'y']);

    expect($t->lastRequest()['data']['mode'])->toBe('production')
        ->and(zbs($t, ZbsResource::MODE_PRODUCTION)->isProduction())->toBeTrue()
        ->and(zbs($t)->isProduction())->toBeFalse();
});

it('tra cứu đi đúng endpoint', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);
    zbs($t)->templates();
    expect($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/all');

    $t->push(['error' => 0, 'data' => []]);
    zbs($t)->sampleData('tpl-1');
    expect($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/sample-data')
        ->and($t->lastRequest()['data'])->toBe(['template_id' => 'tpl-1']);

    $t->push(['error' => 0, 'data' => []]);
    zbs($t)->quota();
    expect($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/message/quota');

    $t->push(['error' => 0, 'data' => []]);
    zbs($t)->status('m-1');
    expect($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/message/status');
});

/*
| Nhóm dưới đây khoá lại đúng con lỗi đã gặp ngoài đời: gọi templates() trả về
| `-132 Invalid status` vì package truyền chuỗi "ENABLE" trong khi Zalo chỉ
| nhận số. Trong response Zalo lại trả `status` là CHỮ, nên rất dễ nhầm chiều.
*/

it('KHÔNG lọc trạng thái khi không được yêu cầu', function (): void {
    // Lọc sẵn ENABLE làm OA đang chờ duyệt trông như chưa tạo mẫu nào.
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates();

    expect($t->lastRequest()['data'])->toBe(['offset' => 0, 'limit' => 100])
        ->and($t->lastRequest()['data'])->not->toHaveKey('status');
});

it('gửi status dạng SỐ chứ không phải chuỗi', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates(status: ZbsResource::STATUS_ENABLE);

    expect($t->lastRequest()['data']['status'])->toBe(1);
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi status ngoài khoảng 1–5', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->templates(status: 9))
        ->toThrow(ConfigurationException::class, '1–5');

    expect($t->requests)->toBeEmpty();
});

it('giới hạn limit ở mức Zalo cho phép', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates(limit: 500);

    expect($t->lastRequest()['data']['limit'])->toBe(100);
});

it('template() lấy ra từ danh sách, không gọi endpoint riêng', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => [
        ['templateId' => 111, 'templateName' => 'Một'],
        ['templateId' => 222, 'templateName' => 'Hai'],
    ]]);

    $found = zbs($t)->template('222');

    expect($found['templateName'])->toBe('Hai')
        ->and($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/all');
});

it('template() trả null khi không có id đó', function (): void {
    // Trả null thay vì ném: gọi lệnh với id gõ nhầm là chuyện thường, và
    // người dùng cần thấy "không có id này" chứ không phải một stack trace.
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => [['templateId' => 111]]]);

    expect(zbs($t)->template('999'))->toBeNull();
});
