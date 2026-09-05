<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Listeners;

use FieldVn\Zalo\Laravel\Events\ZaloFollowerAdded;
use FieldVn\Zalo\Laravel\Events\ZaloFollowerRemoved;
use FieldVn\Zalo\Laravel\Events\ZaloMessageReceived;
use FieldVn\Zalo\Laravel\Models\ZaloContact;
use FieldVn\Zalo\Laravel\Models\ZaloOa;

/**
 * Đồng bộ bảng contacts từ webhook follow / unfollow / tin người dùng gửi.
 *
 * `last_interaction_at` chỉ cập nhật khi người dùng thật sự tương tác
 * (follow, `user_send_*`). Không đụng biên nhận `user_received_message` /
 * `user_seen_message` — đó là OA→user, không gia hạn cửa sổ CS 7 ngày của
 * OpenAPI. Nếu cứ touch theo biên nhận, notifier sẽ gọi CS sau khi Zalo đã
 * từ chối, rồi không được fallback ZBS.
 */
final class UpdateContactOnWebhookEvent
{
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
