<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Resources\TagResource;
use FieldVn\Zalo\Core\Http\PendingRequest;
use FieldVn\Zalo\Laravel\Facades\Zalo;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| Zalo chỉ chuyển Message API sang v3.0. Các API quản lý OA/nhãn vẫn ở v2.0.
| Gọi nhầm /v3.0/oa/getoa thì Zalo trả "You are accessing an empty or invalid
| API" — đúng lỗi nút Kiểm tra trên UI đang gặp.
*/

function tags(FakeTransport $t): TagResource
{
    return new TagResource(new PendingRequest(
        $t,
        'https://openapi.zalo.me',
        static fn (): array => ['access_token' => 'tok'],
    ));
}

it('info() gọi getoa v2.0 — v3.0 không tồn tại', function (): void {
    $fake = Zalo::fake();

    Zalo::oa('org-6')->info();

    expect($fake->sent()->first()->url)->toBe('https://openapi.zalo.me/v2.0/oa/getoa')
        ->and($fake->sent()->first()->method)->toBe('GET');
});

it('danh sách nhãn đi endpoint v2.0', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    tags($t)->all();

    expect($t->lastRequest()['url'])->toBe('https://openapi.zalo.me/v2.0/oa/tag/gettagsofoa');
});

it('gắn và gỡ nhãn đi endpoint v2.0', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);
    $t->push(['error' => 0, 'data' => []]);

    tags($t)->assign('u-1', 'VIP');
    tags($t)->remove('u-1', 'VIP');

    expect($t->requests[0]['url'])->toBe('https://openapi.zalo.me/v2.0/oa/tag/tagfollower')
        ->and($t->requests[0]['data'])->toBe(['user_id' => 'u-1', 'tag_name' => 'VIP'])
        ->and($t->requests[1]['url'])->toBe('https://openapi.zalo.me/v2.0/oa/tag/rmfollowerfromtag');
});
