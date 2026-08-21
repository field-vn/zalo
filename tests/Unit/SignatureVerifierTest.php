<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Webhook\SignatureVerifier;

function sign(string $appId, string $body, string $timestamp, string $secret): string
{
    return 'mac='.hash('sha256', $appId.$body.$timestamp.$secret);
}

function verifier(int $tolerance = 300): SignatureVerifier
{
    return new SignatureVerifier('app-1', 'secret-1', $tolerance);
}

function nowMs(int $offsetSeconds = 0): string
{
    return (string) ((time() + $offsetSeconds) * 1000);
}

it('chấp nhận chữ ký đúng', function (): void {
    $body = '{"event_name":"user_send_text"}';
    $ts = nowMs();

    expect(verifier()->verify($body, $ts, sign('app-1', $body, $ts, 'secret-1')))->toBeTrue();
});

it('chấp nhận cả hash trần lẫn dạng mac=', function (): void {
    $body = '{"a":1}';
    $ts = nowMs();
    $hash = hash('sha256', 'app-1'.$body.$ts.'secret-1');

    expect(verifier()->verify($body, $ts, $hash))->toBeTrue()
        ->and(verifier()->verify($body, $ts, 'mac='.$hash))->toBeTrue();
});

it('từ chối khi body bị sửa dù chỉ một ký tự', function (): void {
    $ts = nowMs();
    $signature = sign('app-1', '{"amount":100}', $ts, 'secret-1');

    expect(verifier()->verify('{"amount":900}', $ts, $signature))->toBeFalse();
});

it('từ chối khi sai secret', function (): void {
    $body = '{"a":1}';
    $ts = nowMs();

    expect(verifier()->verify($body, $ts, sign('app-1', $body, $ts, 'secret-khac')))->toBeFalse();
});

it('từ chối khi thiếu chữ ký', function (): void {
    expect(verifier()->verify('{}', nowMs(), null))->toBeFalse()
        ->and(verifier()->verify('{}', nowMs(), ''))->toBeFalse();
});

it('FAIL-CLOSED khi chưa cấu hình secret', function (): void {
    $body = '{"a":1}';
    $ts = nowMs();

    // Secret rỗng thì không có cách nào phân biệt thật/giả — phải từ chối,
    // không được cho qua.
    $noSecret = new SignatureVerifier('app-1', '');

    expect($noSecret->verify($body, $ts, sign('app-1', $body, $ts, '')))->toBeFalse();
});

it('từ chối request quá cũ (chống replay)', function (): void {
    $body = '{"a":1}';
    $ts = nowMs(-3600);

    expect(verifier(300)->verify($body, $ts, sign('app-1', $body, $ts, 'secret-1')))->toBeFalse();
});

it('bỏ qua kiểm tra thời gian khi tolerance = 0', function (): void {
    $body = '{"a":1}';
    $ts = nowMs(-86400);

    expect(verifier(0)->verify($body, $ts, sign('app-1', $body, $ts, 'secret-1')))->toBeTrue();
});

it('từ chối timestamp không phải số', function (): void {
    expect(verifier()->verify('{}', 'khong-phai-so', 'mac=abc'))->toBeFalse();
});

it('CHỮ KÝ TÍNH TRÊN BODY THÔ, không phải JSON encode lại', function (): void {
    // Đây là lỗi phổ biến nhất khi tích hợp webhook Zalo: decode rồi encode
    // lại sẽ đổi khoảng trắng, thứ tự khoá và cách escape unicode.
    $raw = '{"event_name":"user_send_text","message":{"text":"Xin chào"}}';
    $ts = nowMs();
    $signature = sign('app-1', $raw, $ts, 'secret-1');

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($raw, true);
    $reEncoded = (string) json_encode($decoded);   // escape unicode → khác $raw

    expect(verifier()->verify($raw, $ts, $signature))->toBeTrue()
        ->and($reEncoded)->not->toBe($raw)
        ->and(verifier()->verify($reEncoded, $ts, $signature))->toBeFalse();
});
