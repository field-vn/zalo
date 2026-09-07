<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Http\Response;
use Illuminate\Support\Facades\Route;

it('request JSON nhận payload đầy đủ khi ApiException', function (): void {
    Route::get('/__zalo-error-probe', function (): never {
        throw ApiException::fromResponse(new Response(
            200,
            ['error' => -230, 'message' => 'User has not interacted with the OA in the past 7 days'],
        ));
    });

    $this->getJson('/__zalo-error-probe')
        ->assertStatus(400)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('code', -230)
        ->assertJsonPath('category', 'recipient')
        ->assertJsonPath('source', 'zalo_oa')
        ->assertJsonPath('message', 'User has not interacted with the OA in the past 7 days')
        ->assertJsonStructure(['description', 'hint', 'docs', 'http_status']);
});

it('request JSON rate limit trả 429', function (): void {
    Route::get('/__zalo-error-probe-rl', function (): never {
        throw ApiException::fromResponse(new Response(
            200,
            ['error' => -32, 'message' => 'Your application reached limit call api'],
        ));
    });

    $this->getJson('/__zalo-error-probe-rl')
        ->assertStatus(429)
        ->assertJsonPath('category', 'rate_limit');
});
