<?php

declare(strict_types=1);

use FieldVn\Zalo\Core\Channels\OA\Messages\Button;
use FieldVn\Zalo\Core\Channels\OA\Messages\TextMessage;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Laravel\Facades\Zalo;
use FieldVn\Zalo\Laravel\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;

it('không cần OA trong DB và không cần token', function (): void {
    // Đây là điểm mấu chốt: người dùng package không phải seed dữ liệu hay
    // giả lập OAuth chỉ để test một đoạn gửi tin nhắn.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'Xin chào');

    Zalo::assertSentTo('user-1', 'Xin chào');
});

it('chặn mọi lời gọi mạng', function (): void {
    $fake = Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'a');
    Zalo::oa('cskh')->users()->profile('user-1');

    expect($fake->sent())->toHaveCount(2);
});

it('assertNothingSent khi chưa gửi gì', function (): void {
    Zalo::fake();

    Zalo::assertNothingSent();
});

it('assertSentCount đếm đúng', function (): void {
    Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'a');
    Zalo::oa('cskh')->messages()->text('user-2', 'b');

    Zalo::assertSentCount(2);
});

it('phân biệt được OA nào đã gửi', function (): void {
    // Quan trọng với dự án nhiều OA: gửi nhầm OA là lỗi nghiệp vụ thật.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'a');

    Zalo::assertSentVia('cskh');
    expect(fn () => Zalo::assertSentVia('marketing'))->toThrow(AssertionFailedError::class);
});

it('assertNotSentTo bắt được khi gửi nhầm người', function (): void {
    Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'a');

    Zalo::assertNotSentTo('user-2');
    expect(fn () => Zalo::assertNotSentTo('user-1'))->toThrow(AssertionFailedError::class);
});

it('vẫn chạy message builder thật, không giả lập nó', function (): void {
    // Fake chỉ thay tầng MẠNG. Nếu TextMessage dựng payload sai thì test này
    // phải bắt được — đó là khác biệt so với việc mock cả facade.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->send(
        TextMessage::to('user-1')
            ->text('Đơn #123 đang giao')
            ->button(Button::url('Theo dõi', 'https://shop.vn/123'))
    );

    Zalo::assertSent(function (RecordedRequest $r): bool {
        $buttons = $r->get('message.attachment.payload.buttons');

        return $r->get('recipient.user_id') === 'user-1'
            && $r->get('message.attachment.type') === 'template'
            && $r->get('message.attachment.payload.template_type') === 'button'
            && $r->get('message.attachment.payload.text') === 'Đơn #123 đang giao'
            && is_array($buttons)
            && $buttons[0]['title'] === 'Theo dõi'
            && $buttons[0]['type'] === 'oa.open.url';
    });
});

it('tin có nút KHÔNG lặp text ở message.text', function (): void {
    // Nội dung chỉ nằm trong payload của template. Gửi cả hai là dạng lai mà
    // Zalo không mô tả ở đâu cả.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->send(
        TextMessage::to('user-1')
            ->text('Đơn #123')
            ->button(Button::url('Xem', 'https://shop.vn/123'))
    );

    Zalo::assertSent(
        fn (RecordedRequest $r): bool => $r->get('message.text') === null
            && $r->get('message.attachment.payload.text') === 'Đơn #123'
    );
});

it('assertSentTo vẫn đọc được nội dung của tin có nút', function (): void {
    // RecordedRequest::text() phải nhìn được cả hai vị trí, nếu không người
    // dùng sẽ tưởng tin không gửi được chỉ vì nó có nút bấm.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->send(
        TextMessage::to('user-1')
            ->text('Đơn #123')
            ->button(Button::url('Xem', 'https://shop.vn/123'))
    );

    Zalo::assertSentTo('user-1', 'Đơn #123');
});

it('vẫn validate payload như code thật', function (): void {
    Zalo::fake();

    // Zalo yêu cầu URL của nút là HTTPS — fake không được nới lỏng ràng buộc,
    // nếu không test sẽ xanh trong khi production hỏng.
    expect(fn () => Button::url('Xem', 'http://khong-an-toan.vn'))
        ->toThrow(InvalidArgumentException::class);
});

it('cho phép đặt response giả', function (): void {
    Zalo::fake()->push(['error' => 0, 'data' => ['message_id' => 'msg-99']]);

    $response = Zalo::oa('cskh')->messages()->text('user-1', 'a');

    expect($response->get('data.message_id'))->toBe('msg-99');
});

it('ném lỗi khi response giả báo lỗi nghiệp vụ', function (): void {
    // Zalo trả HTTP 200 kèm error != 0 — test phải mô phỏng được ca này.
    Zalo::fake()->push(['error' => -216, 'message' => 'Token hết hạn']);

    expect(fn () => Zalo::oa('cskh')->messages()->text('user-1', 'a'))
        ->toThrow(ApiException::class, 'Token hết hạn');
});

it('thông báo lỗi liệt kê những gì ĐÃ gửi', function (): void {
    // Assert fail mà không nói đã gửi gì thì rất khó lần ra nguyên nhân.
    Zalo::fake();

    Zalo::oa('cskh')->messages()->text('user-1', 'Xin chào');

    try {
        Zalo::assertSentTo('user-999');
        $this->fail('Lẽ ra phải fail.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())
            ->toContain('oa:cskh')
            ->toContain('user-1')
            ->toContain('Xin chào');
    }
});

it('hoạt động với Bot', function (): void {
    Zalo::fake();

    Zalo::bot('support')->messages()->send('chat-1', 'pong');

    Zalo::assertSent(
        fn (RecordedRequest $r): bool => $r->isBot()
            && $r->slug() === 'support'
            && $r->userId() === 'chat-1'
            && $r->text() === 'pong'
    );
});

it('helper toàn cục cũng bị fake', function (): void {
    Zalo::fake();

    zalo_oa('cskh')->messages()->text('user-1', 'qua helper');

    Zalo::assertSentTo('user-1', 'qua helper');
});
