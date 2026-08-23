<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use Illuminate\Console\Command;

/**
 * Liệt kê chat_id đã ghi nhận được từ webhook.
 *
 * Đây là câu trả lời cho "lấy chat_id ở đâu". Zalo không có API liệt kê, nên
 * package ghi lại mỗi lần có người nhắn tới bot. Danh sách rỗng nghĩa là
 * webhook chưa về được — xem `zalo:bot:webhook <slug>`.
 */
class BotChatsCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:bot:chats
        {slug? : Slug của bot. Bỏ trống thì hiện của mọi bot}
        {--limit=20 : Số hội thoại hiện ra, mới nhất trước}';

    protected $description = 'Liệt kê chat_id đã nhắn cho bot (nguồn để gửi tin)';

    public function handle(BotRepository $bots): int
    {
        $slug = $this->stringArgument('slug');

        $query = ZaloBotChat::query()->with('bot')->recent();

        if ($slug !== '') {
            $bot = $bots->find($slug);

            if (! $bot instanceof ZaloBot) {
                $this->components->error("Không tìm thấy bot `{$slug}`.");
                $this->line('  <fg=gray>Xem danh sách: php artisan zalo:bot:list</>');

                return self::FAILURE;
            }

            $query->where('bot_id', $bot->getKey());
        }

        $chats = $query->limit(max(1, (int) $this->option('limit')))->get();

        if ($chats->isEmpty()) {
            return $this->explainEmpty($slug);
        }

        $rows = [];
        $hint = '<slug> <chat_id>';

        foreach ($chats as $chat) {
            // FK cascadeOnDelete: xoá bot là xoá luôn chat, nên quan hệ này
            // không bao giờ rỗng.
            $botSlug = $chat->bot->slug;

            if ($rows === []) {
                $hint = $botSlug.' '.$chat->chat_id;
            }

            $rows[] = [
                $botSlug,
                $chat->chat_id,
                mb_strimwidth((string) ($chat->display_name ?? '—'), 0, 20, '…'),
                (string) $chat->message_count,
                $chat->last_message_at?->format('d/m H:i') ?? '—',
                mb_strimwidth((string) ($chat->last_message ?? '—'), 0, 30, '…'),
            ];
        }

        $this->newLine();
        $this->table(
            ['Bot', 'chat_id', 'Tên', 'Số tin', 'Nhắn lần cuối', 'Nội dung cuối'],
            $rows,
        );
        $this->newLine();
        $this->line('  Gửi thử: <comment>php artisan zalo:bot:send '.$hint.' "Xin chào"</comment>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function explainEmpty(string $slug): int
    {
        $this->newLine();
        $this->components->warn('Chưa ghi nhận hội thoại nào.');
        $this->newLine();
        $this->line('  Danh sách này chỉ đầy lên khi webhook về được. Kiểm theo thứ tự:');
        $this->line('  1. <comment>php artisan zalo:bot:webhook '.($slug ?: '<slug>').'</comment> — đã cắm chưa, secret đủ dài chưa');
        $this->line('  2. Mở Zalo, nhắn cho bot một câu');
        $this->line('  3. Chạy lại lệnh này');
        $this->newLine();
        $this->line('  <fg=gray>Vẫn rỗng: xem log. Sai secret thì webhook bị trả 401 và</>');
        $this->line('  <fg=gray>package ghi cảnh báo trong laravel.log.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
