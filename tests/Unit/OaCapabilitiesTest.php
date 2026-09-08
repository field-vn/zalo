<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityRegistry;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\CapabilityResult;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaCapability;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaPackageMatrix;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\OaSnapshot;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\TransportException;
use FieldVn\Zalo\Laravel\Facades\Zalo;
use FieldVn\Zalo\Laravel\Testing\RecordedRequest;
use FieldVn\Zalo\Laravel\Testing\ZaloFake;

afterEach(function (): void {
    CapabilityRegistry::flushExtensions();
});

/** @return array<string, mixed> */
function oaInfoOk(string $package = 'OA Tăng trưởng'): array
{
    return [
        'error' => 0,
        'data' => [
            'oa_id' => '123',
            'name' => 'CSKH',
            'package_name' => $package,
            'is_verified' => true,
            'linked_ZCA' => 'zca-1',
        ],
    ];
}

/** @return array<string, mixed> */
function oaQuotaOk(int $csRemain = 1990, int $csTotal = 2000): array
{
    return [
        'error' => 0,
        'data' => [
            'asset' => [
                [
                    'asset_id' => 'a-cs',
                    'product_type' => 'cs',
                    'quota_type' => 'sub_quota',
                    'total' => $csTotal,
                    'remain' => $csRemain,
                    'valid_through' => '10/10/2024',
                ],
                [
                    'asset_id' => 'a-tx',
                    'product_type' => 'transaction',
                    'quota_type' => 'purchase',
                    'total' => 100,
                    'remain' => 80,
                    'valid_through' => '10/10/2024',
                ],
            ],
        ],
    ];
}

/** @return array<string, mixed> */
function zbsQuotaOk(int $remain = 12, int $daily = 1000): array
{
    return [
        'error' => 0,
        'data' => [
            'dailyQuota' => $daily,
            'remainingQuota' => $remain,
            'dailyQuotaPromotion' => 50,
            'remainingQuotaPromotion' => 40,
            'monthlyPromotionQuota' => 500,
            'remainingMonthlyPromotionQuota' => 480,
            'estimatedNextMonthPromotionQuota' => 500,
        ],
    ];
}

/** @return array<string, mixed> */
function zbsTemplatesOk(string $status = 'ENABLE'): array
{
    return [
        'error' => 0,
        'data' => [
            [
                'templateId' => '433555',
                'templateName' => 'OTP',
                'status' => $status,
                'quality' => 'HIGH',
                'listParams' => [['name' => 'otp']],
            ],
            [
                'templateId' => '433556',
                'templateName' => 'Pending',
                'status' => 'PENDING_REVIEW',
                'quality' => 'UNKNOWN',
                'listParams' => [],
            ],
        ],
    ];
}

function pushOaQuotaPair(ZaloFake $fake, int $csRemain = 1990, int $csTotal = 2000): ZaloFake
{
    return $fake
        ->push(oaQuotaOk($csRemain, $csTotal))
        ->push(['error' => 0, 'data' => ['asset' => []]]);
}

it('cs_outside_48h remain 1990/limit 2000 available true', function (): void {
    $fake = Zalo::fake()
        ->push(oaInfoOk(), 200, ['X-RateLimit-Limit' => '2000', 'X-RateLimit-Remain' => '1999']);
    pushOaQuotaPair($fake);

    $cs = Zalo::oa('cskh')->capabilities()->check(OaCapability::CsOutside48h)->get(OaCapability::CsOutside48h);

    expect($cs)->not->toBeNull()
        ->and($cs->available)->toBeTrue()
        ->and($cs->remain)->toBe(1990)
        ->and($cs->limit)->toBe(2000)
        ->and($cs->value)->toBe(1990)
        ->and($cs->meta['product_type'])->toBe('cs')
        ->and($cs->meta['quota_type'])->toBe('sub_quota')
        ->and($cs->meta['valid_through'])->toBe('10/10/2024');
});

it('cs_outside_48h remain 0 vẫn trả số, available false', function (): void {
    $fake = Zalo::fake()->push(oaInfoOk());
    pushOaQuotaPair($fake, 0, 2000);

    $cs = Zalo::oa('cskh')->capabilities()->check(OaCapability::CsOutside48h)->get(OaCapability::CsOutside48h);

    expect($cs->available)->toBeFalse()
        ->and($cs->remain)->toBe(0)
        ->and($cs->limit)->toBe(2000);
});

it('oa_quota trả đủ asset cs + transaction', function (): void {
    $fake = Zalo::fake()->push(oaInfoOk());
    pushOaQuotaPair($fake);

    $quota = Zalo::oa('cskh')->capabilities()->check(OaCapability::OaQuota)->get(OaCapability::OaQuota);

    expect($quota->remain)->toBe(2070)
        ->and($quota->limit)->toBe(2100)
        ->and($quota->meta['assets'])->toHaveCount(2)
        ->and(collect($quota->meta['assets'])->pluck('product_type')->all())->toBe(['cs', 'transaction']);
});

it('zbs_quota trả đủ daily + monthlyPromotion', function (): void {
    Zalo::fake()
        ->push(oaInfoOk())
        ->push(zbsQuotaOk());

    $q = Zalo::oa('cskh')->capabilities()->check(OaCapability::ZbsQuota)->get(OaCapability::ZbsQuota);

    expect($q->remain)->toBe(12)
        ->and($q->limit)->toBe(1000)
        ->and($q->meta['dailyQuota'])->toBe(1000)
        ->and($q->meta['remainingQuota'])->toBe(12)
        ->and($q->meta['dailyQuotaPromotion'])->toBe(50)
        ->and($q->meta['remainingQuotaPromotion'])->toBe(40)
        ->and($q->meta['monthlyPromotionQuota'])->toBe(500)
        ->and($q->meta['remainingMonthlyPromotionQuota'])->toBe(480)
        ->and($q->meta['estimatedNextMonthPromotionQuota'])->toBe(500);
});

it('matrix package_name → Open API / ZBS / CS quota', function (): void {
    expect(OaPackageMatrix::entitlements('OA Cơ bản'))->toMatchArray([
        'open_api' => false,
        'zbs' => false,
        'cs_outside_48h' => 0,
        'authorized_apps' => 1,
    ])
        ->and(OaPackageMatrix::entitlements('OA Tiêu chuẩn')['open_api'])->toBeFalse()
        ->and(OaPackageMatrix::entitlements('OA Tăng trưởng'))->toMatchArray([
            'open_api' => true,
            'zbs' => true,
            'cs_outside_48h' => 500,
            'rate_limit' => 100,
            'authorized_apps' => 3,
        ])
        ->and(OaPackageMatrix::entitlements('OA Nâng cao')['zbs'])->toBeTrue()
        ->and(OaPackageMatrix::entitlements('OA Premium')['open_api'])->toBeTrue()
        ->and(OaPackageMatrix::entitlements('OA Dùng thử')['cs_outside_48h'])->toBe(500)
        ->and(OaPackageMatrix::entitlements('OA Toàn diện'))->toMatchArray([
            'open_api' => true,
            'zbs' => true,
            'cs_outside_48h' => 2000,
            'rate_limit' => 2000,
            'authorized_apps' => null,
        ]);
});

it('check() mixed OA+ZBS', function (): void {
    $fake = Zalo::fake()
        ->push(oaInfoOk('OA Toàn diện'), 200, ['X-RateLimit-Limit' => '2000', 'X-RateLimit-Remain' => '50']);
    pushOaQuotaPair($fake, 1990, 2000);
    $fake->push(zbsQuotaOk())->push(zbsTemplatesOk());

    $report = Zalo::oa('cskh')->capabilities()->check(
        OaCapability::OpenApi,
        OaCapability::CsOutside48h,
        OaCapability::Zbs,
        OaCapability::ZbsQuota,
        OaCapability::ZbsTemplates,
    );

    expect($report->available(OaCapability::OpenApi))->toBeTrue()
        ->and($report->available(OaCapability::Zbs))->toBeTrue()
        ->and($report->get(OaCapability::ZbsQuota)->remain)->toBe(12)
        ->and($report->get(OaCapability::ZbsTemplates)->value)->toBe(1)
        ->and($report->get(OaCapability::ZbsTemplates)->meta['pending'])->toBe(1);
});

it('-135 trên quota → zbs false, không throw', function (): void {
    Zalo::fake()
        ->push(oaInfoOk())
        ->push(['error' => -135, 'message' => 'No permission']);

    $report = Zalo::oa('cskh')->capabilities()->check('open_api', 'zbs', 'zbs_quota');

    expect($report->available(OaCapability::OpenApi))->toBeTrue()
        ->and($report->available(OaCapability::Zbs))->toBeFalse()
        ->and($report->get(OaCapability::Zbs)->meta['error_code'])->toBe(-135)
        ->and($report->get(OaCapability::ZbsQuota)->available)->toBeFalse();
});

it('zbs_send khi -135 không gọi /template/all', function (): void {
    $fake = Zalo::fake()
        ->push(oaInfoOk())
        ->push(['error' => -135, 'message' => 'No permission']);

    $send = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::zbsSend(templateId: '433555'))
        ->get(OaCapability::ZbsSend);

    expect($send->available)->toBeFalse()
        ->and($send->meta['error_code'])->toBe(-135);

    $fake->assertNotSent(fn (RecordedRequest $r): bool => str_contains($r->url, '/template/all'));
});

it('check zbs không gọi /template/all', function (): void {
    $fake = Zalo::fake()
        ->push(oaInfoOk())
        ->push(zbsQuotaOk());

    Zalo::oa('cskh')->capabilities()->check(OaCapability::Zbs);

    $fake->assertNotSent(fn (RecordedRequest $r): bool => str_contains($r->url, '/template/all'));
    $fake->assertSent(fn (RecordedRequest $r): bool => str_contains($r->url, '/message/quota'));
});

it('template ENABLE available, PENDING thì false', function (): void {
    Zalo::fake()->push(oaInfoOk())->push(zbsTemplatesOk('ENABLE'));

    $enable = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::zbsTemplate(id: '433555'))
        ->get(OaCapability::ZbsTemplate);

    expect($enable->available)->toBeTrue()
        ->and($enable->value)->toBe('ENABLE')
        ->and($enable->meta['listParams'])->toBe([['name' => 'otp']]);

    Zalo::fake()->push(oaInfoOk())->push(zbsTemplatesOk('PENDING_REVIEW'));

    $pending = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::zbsTemplate(id: '433555'))
        ->get(OaCapability::ZbsTemplate);

    expect($pending->available)->toBeFalse()
        ->and($pending->hint)->toContain('ENABLE');
});

it('thiếu template id thì hint, không gọi list nếu check defaults skip', function (): void {
    Zalo::fake()->push(oaInfoOk());

    $result = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::ZbsTemplate)
        ->get(OaCapability::ZbsTemplate);

    expect($result->available)->toBeFalse()
        ->and($result->hint)->toContain('Thiếu template id');
});

it('zbs_send không gọi POST /message/template', function (): void {
    $fake = Zalo::fake()
        ->push(oaInfoOk())
        ->push(zbsQuotaOk())
        ->push(zbsTemplatesOk());

    $send = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::zbsSend(templateId: '433555'))
        ->get(OaCapability::ZbsSend);

    expect($send->available)->toBeTrue()
        ->and($send->remain)->toBe(12)
        ->and($send->meta['status'])->toBe('ENABLE');

    $fake->assertNotSent(fn (RecordedRequest $r): bool => str_contains($r->url, '/message/template') && $r->method === 'POST');
});

it('zbs_send false khi remainingQuota = 0', function (): void {
    Zalo::fake()
        ->push(oaInfoOk())
        ->push(zbsQuotaOk(0, 1000))
        ->push(zbsTemplatesOk());

    $send = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::zbsSend(templateId: '433555'))
        ->get(OaCapability::ZbsSend);

    expect($send->available)->toBeFalse()
        ->and($send->remain)->toBe(0)
        ->and($send->limit)->toBe(1000);
});

it('rate_limit lấy header X-RateLimit-*', function (): void {
    Zalo::fake()->push(oaInfoOk('OA Toàn diện'), 200, [
        'X-RateLimit-Limit' => '2000',
        'X-RateLimit-Remain' => '42',
    ]);

    $rate = Zalo::oa('cskh')->capabilities()->check(OaCapability::RateLimit)->get(OaCapability::RateLimit);

    expect($rate->remain)->toBe(42)
        ->and($rate->limit)->toBe(2000)
        ->and($rate->meta['oa_limit'])->toBe(2000)
        ->and($rate->meta['app_limit'])->toBe(2000);
});

it('authorized_apps used=null, Toàn diện limit null', function (): void {
    Zalo::fake()->push(oaInfoOk('OA Toàn diện'));

    $apps = Zalo::oa('cskh')->capabilities()->check(OaCapability::AuthorizedApps)->get(OaCapability::AuthorizedApps);

    expect($apps->limit)->toBeNull()
        ->and($apps->meta['used'])->toBeNull()
        ->and($apps->available)->toBeTrue();
});

it('user_quota cần user_id và trả promotion', function (): void {
    Zalo::fake()
        ->push(oaInfoOk())
        ->push([
            'error' => 0,
            'data' => [
                'last_interaction' => '2024-09-01',
                'promotion' => [
                    'daily_remain' => 1,
                    'daily_total' => 1,
                    'monthly_remain' => 8,
                    'monthly_total' => 8,
                ],
            ],
        ]);

    $u = Zalo::oa('cskh')->capabilities()
        ->check(OaCapability::userQuota(userId: 'u-1'))
        ->get(OaCapability::UserQuota);

    expect($u->available)->toBeTrue()
        ->and($u->remain)->toBe(8)
        ->and($u->limit)->toBe(8)
        ->and($u->meta['promotion']['daily_remain'])->toBe(1);
});

it('CapabilityRegistry::extend thêm checker', function (): void {
    CapabilityRegistry::extend('my_key', function (OaSnapshot $snapshot, array $options): CapabilityResult {
        return new CapabilityResult(
            key: 'my_key',
            available: true,
            value: $snapshot->packageName(),
        );
    });

    Zalo::fake()->push(oaInfoOk());

    $report = Zalo::oa('cskh')->capabilities()->check('my_key');

    expect($report->available('my_key'))->toBeTrue()
        ->and($report->get('my_key')->value)->toBe('OA Tăng trưởng');
});

it('token -216 trên getoa vẫn throw', function (): void {
    Zalo::fake()->push(['error' => -216, 'message' => 'invalid token']);

    Zalo::oa('cskh')->capabilities()->check(OaCapability::OpenApi);
})->throws(ApiException::class);

it('HTTP 5xx trên getoa throw TransportException, không available:false', function (): void {
    Zalo::fake()->push(['error' => 0, 'data' => []], 502);

    Zalo::oa('cskh')->capabilities()->check(OaCapability::OpenApi);
})->throws(TransportException::class);
