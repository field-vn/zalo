<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\WebhookEvent;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Bắn cho MỌI sự kiện webhook, kể cả loại package chưa bọc riêng.
 *
 * Zalo có hàng chục event_name và còn thêm nữa. Tạo một class event cho từng
 * cái là bảo trì không nổi, và mỗi lần Zalo thêm loại mới thì người dùng lại
 * phải chờ package cập nhật. Event chung này đảm bảo không sự kiện nào rơi
 * vào khoảng trống.
 */
class ZaloWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $event,
        /** null nếu OA gửi webhook chưa được thêm vào hệ thống. */
        public readonly ?ZaloOa $oa = null,
    ) {}
}
