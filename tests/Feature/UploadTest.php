<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Resources\UploadResource;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Http\PendingRequest;
use FieldVn\Zalo\Tests\Support\FakeTransport;

/*
| Upload là chỗ duy nhất trong package chạm vào đĩa, nên phần lớn test ở đây
| là về việc CHẶN SỚM: file thiếu, file nặng, sai định dạng. Lỗi Zalo trả cho
| những ca này rất khó hiểu, và mỗi lần thử là một request tốn quota.
*/

function uploads(FakeTransport $t): UploadResource
{
    return new UploadResource(new PendingRequest(
        $t,
        'https://openapi.zalo.me',
        static fn (): array => ['access_token' => 'tok'],
    ));
}

function tempImage(int $bytes = 128): string
{
    $path = tempnam(sys_get_temp_dir(), 'zalo').'.png';

    // Header PNG thật để mime_content_type nhận đúng loại.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGNiAAAABgADNjd8qAAAAABJRU5ErkJggg==');
    file_put_contents($path, $png.str_repeat("\0", max(0, $bytes - strlen($png))));

    return $path;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/zalo*.png') ?: [] as $f) {
        @unlink($f);
    }
});

it('upload trả về attachment_id', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['attachment_id' => 'att-123']]);

    expect(uploads($t)->image(tempImage()))->toBe('att-123');
});

it('gửi multipart đúng endpoint kèm trường file', function (): void {
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => ['attachment_id' => 'att-123']]);
    $path = tempImage();

    uploads($t)->image($path);

    $req = $t->lastRequest();

    expect($req['method'])->toBe('MULTIPART')
        ->and($req['url'])->toBe('https://openapi.zalo.me/v2.0/oa/upload/image')
        ->and($req['data']['__files'])->toBe(['file' => $path]);
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi file không tồn tại', function (): void {
    $t = new FakeTransport;

    expect(fn () => uploads($t)->image('/khong/co/that.png'))
        ->toThrow(ConfigurationException::class);

    expect($t->requests)->toBeEmpty();
});

it('CHẶN TRƯỚC KHI GỌI MẠNG khi ảnh vượt 1MB', function (): void {
    // Zalo giới hạn 1MB; gửi đi rồi mới biết là tốn một request và một thông
    // báo lỗi khó hiểu.
    $t = new FakeTransport;
    $path = tempImage(UploadResource::MAX_IMAGE_BYTES + 1024);

    expect(fn () => uploads($t)->image($path))
        ->toThrow(ConfigurationException::class, 'vượt giới hạn 1 MB');

    expect($t->requests)->toBeEmpty();
});

it('CHẶN khi file rỗng', function (): void {
    $t = new FakeTransport;
    $path = tempnam(sys_get_temp_dir(), 'zalo').'.png';
    file_put_contents($path, '');

    expect(fn () => uploads($t)->image($path))->toThrow(ConfigurationException::class);

    @unlink($path);
});

it('báo lỗi rõ khi Zalo trả về mà thiếu attachment_id', function (): void {
    // Im lặng trả chuỗi rỗng ở đây sẽ đẻ ra lỗi ở bước gửi tin, cách xa
    // nguyên nhân thật.
    $t = new FakeTransport;
    $t->push(['error' => 0, 'data' => []]);

    expect(fn () => uploads($t)->image(tempImage()))
        ->toThrow(ConfigurationException::class, 'không trả về attachment_id');
});
