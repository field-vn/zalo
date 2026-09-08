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

/** @return array<string, mixed> */
function zbsCreatePayload(array $overrides = []): array
{
    return $overrides + [
        'template_name' => 'Xác nhận đơn hàng ABC',
        'template_type' => ZbsResource::TYPE_CUSTOM,
        'tag' => ZbsResource::TAG_TRANSACTION,
        'layout' => [
            'header' => ['components' => [['LOGO' => ['light' => ['type' => 'IMAGE', 'media_id' => 'm1']]]]],
            'body' => ['components' => [['TITLE' => ['value' => 'Xác nhận đơn']]]],
            'footer' => ['components' => []],
        ],
        'params' => [
            ['name' => 'order_code', 'type' => '11', 'sample_value' => 'DH-1'],
        ],
        'tracking_id' => 'tpl-dh-001',
        'note' => 'CSKH đơn hàng',
    ];
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

it('KHÔNG gửi filterPreset khi không được yêu cầu', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates();

    expect($t->lastRequest()['data'])->not->toHaveKey('filterPreset');
});

it('gửi filterPreset khi được yêu cầu', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates(filterPreset: ZbsResource::FILTER_THIS_APP);

    expect($t->lastRequest()['data']['filterPreset'])->toBe(1);
});

it('gửi filterPreset=0 khi FILTER_ALL được truyền tường minh', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    zbs($t)->templates(filterPreset: ZbsResource::FILTER_ALL);

    expect($t->lastRequest()['data']['filterPreset'])->toBe(0);
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi filterPreset không phải 0 hoặc 1', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->templates(filterPreset: 2))
        ->toThrow(ConfigurationException::class, 'filterPreset');

    expect($t->requests)->toBeEmpty();
});

it('info() gọi GET /template/info/v2', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => [
        'templateId' => '222',
        'templateName' => 'Hai',
        'status' => 'ENABLE',
        'reason' => 'Template đã được duyệt',
        'previewUrl' => 'https://account.zalo.solutions/preview/abc',
    ]]);

    $response = zbs($t)->info('222');

    expect($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/info/v2')
        ->and($t->lastRequest()['data'])->toBe(['template_id' => '222'])
        ->and($response->payload()['templateName'])->toBe('Hai');
});

it('template() lấy chi tiết từ /template/info/v2', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => [
        'templateId' => '222',
        'templateName' => 'Hai',
        'status' => 'ENABLE',
    ]]);

    $found = zbs($t)->template('222');

    expect($found['templateName'])->toBe('Hai')
        ->and($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/info/v2')
        ->and($t->lastRequest()['data'])->toBe(['template_id' => '222']);
});

it('template() trả null khi Zalo báo id không hợp lệ', function (): void {
    // Trả null thay vì ném: gọi lệnh với id gõ nhầm là chuyện thường, và
    // người dùng cần thấy "không có id này" chứ không phải một stack trace.
    $t = new FakeTransport;
    $t->push(['error' => -109, 'message' => 'Invalid template id']);

    expect(zbs($t)->template('999'))->toBeNull();
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi info() thiếu template_id', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->info('  '))
        ->toThrow(ConfigurationException::class, 'template_id');

    expect($t->requests)->toBeEmpty();
});

it('create() POST /template/create với payload đã duyệt', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['template_id' => '31239', 'status' => 'PENDING_REVIEW']]);

    $payload = zbsCreatePayload();
    zbs($t)->create($payload);

    $req = $t->lastRequest();

    expect($req['method'])->toBe('POST')
        ->and($req['url'])->toBe('https://business.openapi.zalo.me/template/create')
        ->and($req['data']['template_name'])->toBe('Xác nhận đơn hàng ABC')
        ->and($req['data']['template_type'])->toBe(1)
        ->and($req['data']['tag'])->toBe(1)
        ->and($req['data']['tracking_id'])->toBe('tpl-dh-001')
        ->and($req['data']['layout'])->toBe($payload['layout']);
});

it('CHẶN create() khi tên mẫu quá ngắn', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->create(zbsCreatePayload(['template_name' => 'ngắn'])))
        ->toThrow(ConfigurationException::class, 'template_name');

    expect($t->requests)->toBeEmpty();
});

it('CHẶN create() khi thiếu tracking_id', function (): void {
    $t = new FakeTransport;
    $payload = zbsCreatePayload();
    unset($payload['tracking_id']);

    expect(fn () => zbs($t)->create($payload))
        ->toThrow(ConfigurationException::class, 'tracking_id');

    expect($t->requests)->toBeEmpty();
});

it('CHẶN create() khi layout rỗng', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->create(zbsCreatePayload(['layout' => []])))
        ->toThrow(ConfigurationException::class, 'layout');

    expect($t->requests)->toBeEmpty();
});

it('CHẶN create() khi template_type ngoài 1–5', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->create(zbsCreatePayload(['template_type' => 9])))
        ->toThrow(ConfigurationException::class, 'template_type');

    expect($t->requests)->toBeEmpty();
});

it('edit() POST /template/edit kèm template_id', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['template_id' => '31239', 'status' => 'PENDING_REVIEW']]);

    $payload = zbsCreatePayload();
    unset($payload['tracking_id']);
    zbs($t)->edit('31239', $payload);

    $req = $t->lastRequest();

    expect($req['method'])->toBe('POST')
        ->and($req['url'])->toBe('https://business.openapi.zalo.me/template/edit')
        ->and($req['data']['template_id'])->toBe('31239')
        ->and($req['data']['template_name'])->toBe('Xác nhận đơn hàng ABC');
});

it('CHẶN edit() khi thiếu template_id', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->edit('  ', zbsCreatePayload()))
        ->toThrow(ConfigurationException::class, 'template_id');

    expect($t->requests)->toBeEmpty();
});

it('edit() không bắt tracking_id', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    $payload = zbsCreatePayload();
    unset($payload['tracking_id']);

    zbs($t)->edit('31239', $payload);

    expect($t->lastRequest()['data'])->not->toHaveKey('tracking_id');
});

function zbsTempPng(int $bytes = 128): string
{
    $path = tempnam(sys_get_temp_dir(), 'zbs').'.png';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGNiAAAABgADNjd8qAAAAABJRU5ErkJggg==');
    file_put_contents($path, $png.str_repeat("\0", max(0, $bytes - strlen($png))));

    return $path;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/zbs*.png') ?: [] as $f) {
        @unlink($f);
    }
});

it('uploadImage() trả media_id từ POST /upload/image', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['media_id' => 'media-123']]);

    $path = zbsTempPng();

    expect(zbs($t)->uploadImage($path))->toBe('media-123');

    $req = $t->lastRequest();

    expect($req['method'])->toBe('MULTIPART')
        ->and($req['url'])->toBe('https://business.openapi.zalo.me/upload/image')
        ->and($req['data']['__files'])->toBe(['file' => $path]);
});

it('CHẶN uploadImage() khi file không tồn tại', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->uploadImage('/khong/co/that.png'))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});

it('CHẶN uploadImage() khi ảnh vượt 500 KB', function (): void {
    $t = new FakeTransport;
    $path = zbsTempPng(ZbsResource::MAX_IMAGE_BYTES + 1);

    expect(fn () => zbs($t)->uploadImage($path))
        ->toThrow(ConfigurationException::class, '500');

    expect($t->requests)->toBeEmpty();
});

it('waitForDelivery() dừng khi status = 1', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['status' => 0]])
        ->push(['error' => 0, 'data' => ['status' => 1, 'delivery_time' => '1']]);

    $slept = 0;
    $clock = [100, 101];
    $response = zbs($t)->waitForDelivery(
        'm-1',
        timeoutSeconds: 60,
        intervalSeconds: 3,
        sleep: function () use (&$slept): void {
            $slept++;
        },
        now: static function () use (&$clock): int {
            return array_shift($clock) ?? 101;
        },
    );

    expect((int) $response->payload()['status'])->toBe(1)
        ->and($slept)->toBe(1)
        ->and($t->requests)->toHaveCount(2);
});

it('waitForDelivery() ném khi hết giờ mà vẫn chưa giao', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['status' => 0]]);

    $clock = [0, 61];

    expect(fn () => zbs($t)->waitForDelivery(
        'm-1',
        timeoutSeconds: 60,
        intervalSeconds: 3,
        sleep: static fn (): null => null,
        now: static function () use (&$clock): int {
            return array_shift($clock) ?? 61;
        },
    ))->toThrow(ConfigurationException::class, 'Hết thời gian');
});

it('waitUntilStatus() dừng khi template ENABLE', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['templateId' => '1', 'status' => 'PENDING_REVIEW']])
        ->push(['error' => 0, 'data' => ['templateId' => '1', 'status' => 'ENABLE']]);

    $clock = [100, 101];
    $response = zbs($t)->waitUntilStatus(
        '1',
        statuses: ['ENABLE', 'REJECT'],
        timeoutSeconds: 300,
        intervalSeconds: 5,
        sleep: static fn (): null => null,
        now: static function () use (&$clock): int {
            return array_shift($clock) ?? 101;
        },
    );

    expect($response->payload()['status'])->toBe('ENABLE')
        ->and($t->lastRequest()['url'])->toBe('https://business.openapi.zalo.me/template/info/v2');
});

it('CHẶN wait* khi timeout hoặc interval không dương', function (): void {
    $t = new FakeTransport;

    expect(fn () => zbs($t)->waitForDelivery('m-1', timeoutSeconds: 0, intervalSeconds: 3))
        ->toThrow(ConfigurationException::class);

    expect(fn () => zbs($t)->waitUntilStatus('1', statuses: [], timeoutSeconds: 10, intervalSeconds: 1))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});
