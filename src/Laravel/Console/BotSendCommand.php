<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

/**
 * Gửi một tin thật qua Zalo Bot.
 *
 * `zalo:bot:test` chỉ gọi getMe — đủ để biết token đúng, KHÔNG đủ để biết bot
 * gửi được tin. Hai chuyện đó hỏng độc lập nhau: token còn sống nhưng sai
 * chat_id, bot bị chặn, hay vượt giới hạn đều cho ra kết quả khác nhau.
 */
class BotSendCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:bot:send
        {slug : Slug của bot}
        {chat : chat_id người nhận (xem: php artisan zalo:bot:chats)}
        {text? : Nội dung. Bỏ trống thì gửi một câu kiểm thử có kèm thời điểm}
        {--photo= : Gửi ảnh từ URL thay vì text. `text` thành chú thích}
        {--sticker= : Gửi sticker theo id (lấy ở stickers.zaloapp.com)}';

    protected $description = 'Gửi tin nhắn thật qua Zalo Bot để xác nhận luồng chạy được';

    public function handle(Factory $zalo): int
    {
        $slug = $this->stringArgument('slug');
        $chatId = $this->stringArgument('chat');

        try {
            $bot = $zalo->bot($slug);
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());
            $this->line('  <fg=gray>Xem danh sách: php artisan zalo:bot:list</>');

            return self::FAILURE;
        }

        $text = $this->stringArgument('text')
            ?: 'Tin kiểm thử lúc '.now()->format('H:i:s d/m/Y');

        $photo = $this->stringOption('photo');
        $sticker = $this->stringOption('sticker');

        try {
            $response = match (true) {
                $sticker !== '' => $bot->messages()->sendSticker($chatId, $sticker),
                $photo !== '' => $bot->messages()->sendPhoto($chatId, $photo, $text),
                default => $bot->messages()->send($chatId, $text),
            };
        } catch (ApiException $e) {
            $this->newLine();
            $this->components->error("Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");

            if ($e->isTokenError()) {
                $this->line('  <fg=gray>Token bot sai hoặc bị thu hồi — lấy lại trong Zalo Bot Studio.</>');
            }

            $this->newLine();

            return self::FAILURE;
        }

        /** @var array<string, mixed> $result */
        $result = $response->get('result', []);

        $this->newLine();
        $this->components->info('Zalo đã nhận.');
        $this->components->twoColumnDetail('<fg=gray>message_id</>', (string) ($result['message_id'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>chat_id</>', $chatId);
        $this->newLine();
        $this->line('  <fg=gray>Mở Zalo kiểm tra tin đã tới thật chưa — API báo ok không</>');
        $this->line('  <fg=gray>đảm bảo máy người nhận hiện được tin.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
