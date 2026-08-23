<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Http\Response;

/*
| OA và Bot dùng hai quy ước báo lỗi khác hẳn nhau, cả hai đều trả HTTP 200
| khi hỏng. Response phải hiểu cả hai — nếu không, lỗi của một kênh sẽ lọt
| qua thành công và code gọi tưởng mọi thứ ổn.
*/

it('OA: error = 0 là thành công', function (): void {
    $r = new Response(200, ['error' => 0, 'message' => 'Success', 'data' => ['id' => 1]]);

    expect($r->successful())->toBeTrue()
        ->and($r->payload())->toBe(['id' => 1]);
});

it('OA: error khác 0 là thất bại dù HTTP 200', function (): void {
    $r = new Response(200, ['error' => -216, 'message' => 'Access token is invalid']);

    expect($r->successful())->toBeFalse()
        ->and($r->errorCode())->toBe(-216)
        ->and($r->errorMessage())->toBe('Access token is invalid');
});

it('BOT: ok = true là thành công', function (): void {
    $r = new Response(200, ['ok' => true, 'result' => ['message_id' => 42]]);

    expect($r->successful())->toBeTrue()
        ->and($r->get('result.message_id'))->toBe(42);
});

it('BOT: ok = false là THẤT BẠI dù không có trường error', function (): void {
    // Đây là ca từng lọt: successful() chỉ xét `error`, mà body của Bot không
    // có `error` nên errorCode() trả 0 -> sendMessage hỏng vẫn báo thành công.
    $r = new Response(200, ['ok' => false, 'error_code' => 400, 'description' => 'chat not found']);

    expect($r->successful())->toBeFalse()
        ->and($r->errorCode())->toBe(400)
        ->and($r->errorMessage())->toBe('chat not found');
});

it('BOT: throwIfFailed ném exception khi ok = false', function (): void {
    $r = new Response(200, ['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']);

    expect(fn () => $r->throwIfFailed())->toThrow(ApiException::class);
});

it('HTTP 5xx luôn là thất bại kể cả body rỗng', function (): void {
    expect((new Response(502))->successful())->toBeFalse();
});
