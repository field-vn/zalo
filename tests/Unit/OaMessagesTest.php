<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Messages\Button;
use FieldVn\Zalo\Core\Channels\OA\Messages\ImageMessage;
use FieldVn\Zalo\Core\Channels\OA\Messages\RawMessage;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;

/*
| Payload là thứ duy nhất Zalo nhìn thấy. Test ở đây khoá đúng hình dạng đó,
| vì sai một khoá thì Zalo trả mã lỗi không nói được sai ở đâu.
*/

it('ảnh dùng media template với attachment_id', function (): void {
    $payload = ImageMessage::to('u-1')->attachment('att-9')->toPayload();

    expect($payload)->toBe([
        'recipient' => ['user_id' => 'u-1'],
        'message' => [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'media',
                    'elements' => [['media_type' => 'image', 'attachment_id' => 'att-9']],
                ],
            ],
        ],
    ]);
});

it('KHÔNG gửi khoá text khi không có chú thích', function (): void {
    // Gửi text rỗng là thừa, và Zalo có thể từ chối payload có khoá rỗng.
    $payload = ImageMessage::to('u-1')->attachment('att-9')->toPayload();

    expect($payload['message']['attachment']['payload'])->not->toHaveKey('text');
});

it('chú thích nằm trong payload của template, không nằm ngoài', function (): void {
    $payload = ImageMessage::to('u-1')->attachment('att-9')->caption('Ảnh sản phẩm')->toPayload();

    expect($payload['message']['attachment']['payload']['text'])->toBe('Ảnh sản phẩm')
        ->and($payload['message'])->not->toHaveKey('text');
});

it('attachment và url loại trừ nhau — gọi cái sau xoá cái trước', function (): void {
    // Gửi cả hai thì Zalo không biết lấy cái nào; để lẫn là mầm lỗi khó tìm.
    $byUrl = ImageMessage::to('u-1')->attachment('att-9')->url('https://a.test/x.jpg');
    $element = $byUrl->toPayload()['message']['attachment']['payload']['elements'][0];

    expect($element)->toBe(['media_type' => 'image', 'url' => 'https://a.test/x.jpg']);

    $byId = ImageMessage::to('u-1')->url('https://a.test/x.jpg')->attachment('att-9');
    $element = $byId->toPayload()['message']['attachment']['payload']['elements'][0];

    expect($element)->toBe(['media_type' => 'image', 'attachment_id' => 'att-9']);
});

it('từ chối URL ảnh không phải HTTPS', function (): void {
    expect(fn () => ImageMessage::to('u-1')->url('http://a.test/x.jpg'))
        ->toThrow(InvalidArgumentException::class);
});

it('báo lỗi rõ khi quên đặt ảnh', function (): void {
    expect(fn () => ImageMessage::to('u-1')->toPayload())
        ->toThrow(InvalidArgumentException::class, 'Chưa có ảnh');
});

it('message object là immutable — mỗi lần gọi trả bản sao mới', function (): void {
    // Quan trọng khi dựng sẵn một tin mẫu rồi đổi từng chút cho nhiều người:
    // nếu mutate tại chỗ thì tin của người sau dính dữ liệu của người trước.
    $base = TextMessage::to('u-1')->text('gốc');
    $withButton = $base->button(Button::url('Xem', 'https://a.test'));

    expect($base->toPayload()['message'])->toBe(['text' => 'gốc'])
        ->and($withButton->toPayload()['message'])->toHaveKey('attachment');
});

/*
| RawMessage — lối thoát cho dạng tin package chưa bọc.
*/

it('RawMessage gửi được payload tự dựng', function (): void {
    $payload = RawMessage::to('u-1')->message([
        'attachment' => ['type' => 'template', 'payload' => ['template_type' => 'list']],
    ])->toPayload();

    expect($payload)->toBe([
        'recipient' => ['user_id' => 'u-1'],
        'message' => [
            'attachment' => ['type' => 'template', 'payload' => ['template_type' => 'list']],
        ],
    ]);
});

it('RawMessage::payload() thay được cả hình dạng recipient', function (): void {
    $payload = RawMessage::payload([
        'recipient' => ['message_id' => 'm-1'],
        'message' => ['text' => 'trả lời'],
    ])->toPayload();

    expect($payload['recipient'])->toBe(['message_id' => 'm-1']);
});

it('RawMessage báo lỗi khi thiếu message thay vì gửi payload cụt', function (): void {
    expect(fn () => RawMessage::to('u-1')->toPayload())
        ->toThrow(InvalidArgumentException::class, 'Thiếu khoá `message`');
});
