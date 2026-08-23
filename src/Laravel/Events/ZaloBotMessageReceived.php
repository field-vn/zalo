<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Core\Webhook\BotUpdate;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Có người nhắn cho bot.
 *
 *     public function handle(ZaloBotMessageReceived $e): void
 *     {
 *         $e->bot->slug;     // bot nào nhận
 *         $e->chatId;        // dùng chính giá trị này để trả lời
 *         $e->text;          // nội dung
 *
 *         zalo_bot($e->bot->slug)->messages()->send($e->chatId, 'Đã nhận!');
 *     }
 *
 * `$chat` là bản ghi đã lưu trong zl_bot_chats, tiện khi cần biết người này
 * đã nhắn bao nhiêu lần hay lần cuối là khi nào.
 */
class ZaloBotMessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly BotUpdate $update,
        public readonly ZaloBot $bot,
        public readonly ?ZaloBotChat $chat,
        public readonly ?string $chatId,
        public readonly ?string $text,
        public readonly ?string $messageId,
    ) {}

    public static function from(BotUpdate $update, ZaloBot $bot, ?ZaloBotChat $chat): self
    {
        return new self(
            update: $update,
            bot: $bot,
            chat: $chat,
            chatId: $update->chatId(),
            text: $update->text(),
            messageId: $update->messageId(),
        );
    }
}
