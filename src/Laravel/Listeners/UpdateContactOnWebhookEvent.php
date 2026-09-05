<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Listeners;

use FieldVn\Zalo\Laravel\Events\ZaloFollowerAdded;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerRemoved;
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;
use FieldVn\Zalo\Laravel\Events\ZaloWebhookReceived;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use FieldVn\Zalo\Laravel\Models\ZaloOa;

/**
 * Đồng bộ bảng contacts từ webhook follow / message / received / seen.
 *
 * handleWebhookReceived chỉ xử lý user_received_message và user_seen_message
 * (touch last_interaction_at, không đổi is_following) — tránh đua với
 * follow/unfollow vì ZaloWebhookReceived được bắn trước event cụ thể.
 */
final class UpdateContactOnWebhookEvent
{
    /** Sự kiện generic: chỉ touch tương tác cho received/seen. */
    public function handleWebhookReceived(ZaloWebhookReceived $e): void
    {
        if ($e->oa === null) {
            return;
        }

        $name = $e->event->name;

        if ($name !== 'user_received_message' && $name !== 'user_seen_message') {
            return;
        }

        $userId = $e->event->userId();

        if ($userId === null || $userId === '') {
            return;
        }

        $this->touchInteraction($e->oa, $userId);
    }

    /** Người dùng quan tâm OA. */
    public function handleFollow(ZaloFollowerAdded $e): void
    {
        if ($e->oa === null) {
            return;
        }

        $userId = $e->userId;

        if ($userId === null || $userId === '') {
            return;
        }

        $now = now();
        $contact = ZaloContact::query()->firstOrNew([
            'oa_id' => $e->oa->getKey(),
            'zalo_user_id' => $userId,
        ]);

        if (! $contact->exists) {
            $contact->first_seen_at = $now;
        }

        $contact->is_following = true;
        $contact->last_interaction_at = $now;
        $contact->save();
    }

    /** Người dùng bỏ quan tâm — giữ row, chỉ đánh dấu. */
    public function handleUnfollow(ZaloFollowerRemoved $e): void
    {
        if ($e->oa === null) {
            return;
        }

        $userId = $e->userId;

        if ($userId === null || $userId === '') {
            return;
        }

        ZaloContact::query()
            ->where('oa_id', $e->oa->getKey())
            ->where('zalo_user_id', $userId)
            ->update(['is_following' => false]);
    }

    /** Tin nhắn do người dùng gửi (user_send_*). */
    public function handleMessage(ZaloMessageReceived $e): void
    {
        if ($e->oa === null || ! $e->event->isFromUser()) {
            return;
        }

        $userId = $e->userId;

        if ($userId === null || $userId === '') {
            return;
        }

        $this->upsertInteraction($e->oa, $userId);
    }

    /** Cập nhật last_interaction_at nếu contact đã tồn tại — không tạo mới. */
    private function touchInteraction(ZaloOa $oa, string $userId): void
    {
        ZaloContact::query()
            ->where('oa_id', $oa->getKey())
            ->where('zalo_user_id', $userId)
            ->update(['last_interaction_at' => now()]);
    }

    /** Upsert tương tác: tạo mới thì ghi first_seen_at, không đổi is_following khi update. */
    private function upsertInteraction(ZaloOa $oa, string $userId): void
    {
        $now = now();
        $contact = ZaloContact::query()->firstOrNew([
            'oa_id' => $oa->getKey(),
            'zalo_user_id' => $userId,
        ]);

        if (! $contact->exists) {
            $contact->first_seen_at = $now;
            $contact->is_following = true;
        }

        $contact->last_interaction_at = $now;
        $contact->save();
    }
}
