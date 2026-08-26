<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Một người (hoặc nhóm) đã nhắn cho bot.
 *
 * Đây là câu trả lời cho "lấy chat_id ở đâu": Zalo không có API liệt kê, nên
 * package tự ghi lại mỗi lần webhook về.
 *
 * @property int $id
 * @property int $bot_id
 * @property string $chat_id
 * @property string|null $display_name
 * @property string|null $last_message
 * @property Carbon|null $last_message_at
 * @property int $message_count
 */
class ZaloBotChat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'message_count' => 'integer',
    ];

    public function getTable(): string
    {
        return Table::name(Table::BOT_CHATS);
    }

    /** @return BelongsTo<ZaloBot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ZaloBot::class, 'bot_id');
    }

    /** @param Builder<ZaloBotChat> $query */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('last_message_at');
    }

    /**
     * Ghi nhận một update. Trả về null nếu update không có chat_id — không
     * phải lỗi, chỉ là loại sự kiện không gắn với hội thoại nào.
     *
     * Dùng updateOrCreate để một người nhắn nhiều lần vẫn chỉ một dòng; ràng
     * buộc unique (bot_id, chat_id) chặn trùng ở tầng DB.
     */
    public static function record(ZaloBot $bot, BotUpdate $update): ?self
    {
        $chatId = $update->chatId();

        if ($chatId === null) {
            return null;
        }

        $chat = static::query()->firstOrNew([
            'bot_id' => $bot->getKey(),
            'chat_id' => $chatId,
        ]);

        // Giữ tên cũ nếu update lần này không kèm tên — mất tên đã biết thì
        // danh sách hội thoại trở nên vô dụng.
        $chat->display_name = $update->senderName() ?? $chat->display_name;
        $chat->last_message = $update->text() ?? $chat->last_message;
        $chat->last_message_at = now();
        $chat->message_count = ($chat->message_count ?? 0) + 1;
        $chat->save();

        return $chat;
    }
}
