<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Console;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;
use FieldVn\Zalo\Laravel\Console\Concerns\InteractsWithInput;
use Illuminate\Console\Command;

/**
 * Xem và cắm webhook cho Zalo Bot.
 *
 * Không có lệnh này thì việc cắm webhook phải làm bằng curl, và người dùng
 * phải tự biết ba thứ khó đoán: URL đúng của từng bot, secret phải dài
 * 8-256 ký tự, và secret là BẮT BUỘC.
 */
class BotWebhookCommand extends Command
{
    use InteractsWithInput;

    protected $signature = 'zalo:bot:webhook
        {slug : Slug của bot}
        {--set : Cắm webhook về URL của ứng dụng này}
        {--delete : Gỡ webhook (cần thiết nếu muốn dùng getUpdates)}
        {--url= : Ghi đè URL, dùng khi chạy sau tunnel như ngrok/cloudflared}';

    protected $description = 'Xem, cắm hoặc gỡ webhook của Zalo Bot';

    public function handle(Factory $zalo): int
    {
        $slug = $this->stringArgument('slug');

        try {
            $bot = $zalo->bot($slug);
        } catch (ZaloException $e) {
            $this->components->error($e->getMessage());
            $this->line('  <fg=gray>Xem danh sách: php artisan zalo:bot:list</>');

            return self::FAILURE;
        }

        $url = $this->stringOption('url') ?: route('zalo.webhook.bot', ['bot' => $slug]);
        $secret = (string) config('zalo.bot.webhook_secret', '');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Bot</>', $slug);
        $this->components->twoColumnDetail('<fg=gray>Webhook URL</>', $url);
        $this->components->twoColumnDetail(
            '<fg=gray>Secret</>',
            $this->describeSecret($secret),
        );
        $this->newLine();

        try {
            return match (true) {
                (bool) $this->option('delete') => $this->delete($bot),
                (bool) $this->option('set') => $this->set($bot, $url, $secret),
                default => $this->hint($url, $secret),
            };
        } catch (ApiException $e) {
            $this->components->error("Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function describeSecret(string $secret): string
    {
        if ($secret === '') {
            return '<fg=red>chưa đặt</>';
        }

        if (! BotSecretVerifier::isValidLength($secret)) {
            return '<fg=red>sai độ dài ('.strlen($secret).' ký tự, cần 8-256)</>';
        }

        // Không in secret ra terminal — output hay bị dán vào issue công khai.
        return '<fg=green>đã đặt</> <fg=gray>('.strlen($secret).' ký tự)</>';
    }

    /** @param \FieldVn\Zalo\Core\Channels\Bot\BotChannel $bot */
    private function set($bot, string $url, string $secret): int
    {
        if (! BotSecretVerifier::isValidLength($secret)) {
            return $this->missingSecret();
        }

        if (! str_starts_with($url, 'https://')) {
            // Secret đi nguyên văn trong header, HTTP là để lộ nó.
            $this->components->error('Webhook phải là HTTPS. Zalo gửi secret nguyên văn ở header — HTTP là để lộ.');
            $this->line('  <fg=gray>Dev: cloudflared tunnel --url http://localhost:8000 rồi --url=https://...</>');

            return self::FAILURE;
        }

        $bot->updates()->setWebhook($url, $secret);

        $this->components->info('Đã cắm webhook.');
        $this->line('  <fg=gray>Nhắn cho bot một câu rồi chạy: php artisan zalo:bot:chats '.$this->stringArgument('slug').'</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /** @param \FieldVn\Zalo\Core\Channels\Bot\BotChannel $bot */
    private function delete($bot): int
    {
        $bot->updates()->deleteWebhook();

        $this->components->warn('Đã gỡ webhook — bot NGỪNG đẩy tin về ứng dụng cho tới khi cắm lại.');
        $this->line('  <fg=gray>Cắm lại: php artisan zalo:bot:webhook '.$this->stringArgument('slug').' --set</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function hint(string $url, string $secret): int
    {
        if (! BotSecretVerifier::isValidLength($secret)) {
            return $this->missingSecret();
        }

        $this->line('  Cắm webhook:  <comment>php artisan zalo:bot:webhook '.$this->stringArgument('slug').' --set</comment>');
        $this->line('  Gỡ webhook:   <comment>php artisan zalo:bot:webhook '.$this->stringArgument('slug').' --delete</comment>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function missingSecret(): int
    {
        $this->components->error('Cần ZALO_BOT_WEBHOOK_SECRET dài 8-256 ký tự.');
        $this->newLine();
        $this->line('  Thêm dòng này vào <comment>.env</comment>:');
        $this->line('  <fg=green>ZALO_BOT_WEBHOOK_SECRET='.BotSecretVerifier::generate().'</>');
        $this->newLine();
        $this->line('  Rồi chạy <comment>php artisan config:clear</comment> và thử lại.');
        $this->newLine();

        return self::FAILURE;
    }
}
