<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Errors\ErrorCatalog;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Exceptions\TransportException;
use FieldVn\Zalo\Core\Http\Response;

it('catalog có đủ mã OA trong tài liệu Zalo', function (): void {
    foreach (ErrorCatalog::OA_DOC_CODES as $code) {
        $info = ErrorCatalog::lookup($code);
        expect(ErrorCatalog::has($code))->toBeTrue()
            ->and($info->description)->not->toBe('');
        if ($code !== 0) {
            expect($info->category)->not->toBe('unknown');
        }
    }
});

it('mã lạ vẫn trả message gốc + unknown + docs', function (): void {
    $info = ErrorCatalog::lookup(-99999, 'Something weird');

    expect($info->category)->toBe('unknown')
        ->and($info->message)->toBe('Something weird')
        ->and($info->docsUrl)->toContain('ma-loi');
});

it('isTokenError chỉ -216 -220 -124 401, không -32 -217', function (): void {
    expect(ApiException::fromResponse(new Response(200, ['error' => -216, 'message' => 'invalid']))->isTokenError())->toBeTrue()
        ->and(ApiException::fromResponse(new Response(200, ['error' => -220, 'message' => 'expired']))->isTokenError())->toBeTrue()
        ->and(ApiException::fromResponse(new Response(200, ['error' => -124, 'message' => 'token']))->isTokenError())->toBeTrue()
        ->and(ApiException::fromResponse(new Response(200, ['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']))->isTokenError())->toBeTrue()
        ->and(ApiException::fromResponse(new Response(200, ['error' => -32, 'message' => 'rate']))->isTokenError())->toBeFalse()
        ->and(ApiException::fromResponse(new Response(200, ['error' => -217, 'message' => 'blocked']))->isTokenError())->toBeFalse();
});

it('toArray giữ message OA và map category recipient cho -230', function (): void {
    $e = ApiException::fromResponse(new Response(
        200,
        ['error' => -230, 'message' => 'User has not interacted with the OA in the past 7 days'],
    ));

    $payload = $e->toArray();

    expect($payload['ok'])->toBeFalse()
        ->and($payload['code'])->toBe(-230)
        ->and($payload['message'])->toBe('User has not interacted with the OA in the past 7 days')
        ->and($payload['category'])->toBe('recipient')
        ->and($payload['source'])->toBe('zalo_oa')
        ->and($payload['hint'])->toContain('ZBS')
        ->and($payload['http_status'])->toBe(400)
        ->and($e->explain())->toContain('-230');
});

it('toArray Bot dùng error_code / description và source zalo_bot', function (): void {
    $e = ApiException::fromResponse(new Response(
        200,
        ['ok' => false, 'error_code' => 400, 'description' => 'chat not found'],
    ));

    $payload = $e->toArray();

    expect($payload['code'])->toBe(400)
        ->and($payload['message'])->toBe('chat not found')
        ->and($payload['source'])->toBe('zalo_bot')
        ->and($payload['category'])->toBe('validation')
        ->and($payload['http_status'])->toBe(422);
});

it('rate limit -32 map HTTP 429, không phải token', function (): void {
    $e = ApiException::fromResponse(new Response(200, ['error' => -32, 'message' => 'Your application reached limit call api']));

    expect($e->toArray()['category'])->toBe('rate_limit')
        ->and($e->httpStatus())->toBe(429)
        ->and($e->isTokenError())->toBeFalse();
});

it('catalog có đủ mã ZBS trong tài liệu', function (): void {
    foreach (ErrorCatalog::ZBS_DOC_CODES as $code) {
        expect(ErrorCatalog::has($code))->toBeTrue()
            ->and(ErrorCatalog::lookup($code)->docsUrl)->toContain('zbs-template-message/bang-ma-loi');
    }
});

it('ZBS -109 và -139 resolve được', function (): void {
    expect(ErrorCatalog::lookup(-109)->description)->toContain('template')
        ->and(ErrorCatalog::lookup(-139)->category)->toBe('recipient')
        ->and(ErrorCatalog::lookup(-139)->hint)->toContain('Không gửi lại');
});

it('ZBS -115 source zalo_zbs category quota', function (): void {
    $e = ApiException::fromResponse(new Response(200, ['error' => -115, 'message' => 'Not enough money']));

    expect($e->source())->toBe('zalo_zbs')
        ->and($e->info()->category)->toBe('quota')
        ->and($e->explain())->toContain('zalo.solutions');
});

it('TransportException và ConfigurationException cùng shape toArray', function (): void {
    $t = new TransportException('timeout');
    $c = ConfigurationException::oaNotFound('cskh');

    expect($t->toArray()['source'])->toBe('transport')
        ->and($t->httpStatus())->toBe(503)
        ->and($c->toArray()['source'])->toBe('config')
        ->and($c->httpStatus())->toBe(422)
        ->and($c->explain())->toContain('cskh');
});
