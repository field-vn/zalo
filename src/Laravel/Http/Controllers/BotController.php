<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Core\Webhook\BotSecretVerifier;
use FieldVn\Zalo\Laravel\Models\ZaloAuditLog;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloBotChat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotController
{
    public function index(BotRepository $bots): View
    {
        return view('zalo::bots', ['bots' => $bots->all()]);
    }

    public function store(Request $request, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100'],
            'token' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($data['slug'] ?? '') ?: Str::slug($data['name']);

        if (ZaloBot::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->with('zalo.error', "Slug `{$slug}` đã tồn tại.");
        }

        $bot = ZaloBot::create([
            'name' => $data['name'],
            'slug' => $slug,
            'token' => $data['token'],
            'is_active' => true,
        ]);

        // Token bot kiểm tra được ngay — tận dụng để bắt lỗi copy nhầm.
        try {
            $info = $zalo->bot($bot->slug)->me();
        } catch (ZaloException $e) {
            // Không giữ lại bản ghi hỏng: nó chỉ làm danh sách nhiễu và khiến
            // người dùng tưởng đã thêm thành công.
            $bot->forceDelete();

            return back()->withInput()->with('zalo.error', 'Token không dùng được: '.$e->getMessage());
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $info->payload();

        // getMe trả `account_name` (vd bot.lgevMLAT). Giữ cả `username` phòng
        // khi Zalo đổi tên trường — đây là API chưa ổn định.
        $name = $payload['username'] ?? $payload['account_name'] ?? null;

        if ($name !== null) {
            $bot->forceFill(['username' => (string) $name])->save();
        }

        ZaloAuditLog::record('bot.created', $bot);

        return redirect()->route('zalo.bots.index')
            ->with('zalo.success', "Đã thêm bot `{$bot->slug}`.");
    }

    public function show(ZaloBot $bot): View
    {
        $secret = (string) config('zalo.bot.webhook_secret', '');

        return view('zalo::bot-show', [
            'bot' => $bot,
            'chats' => ZaloBotChat::query()
                ->where('bot_id', $bot->getKey())
                ->recent()
                ->limit(50)
                ->get(),
            'webhookUrl' => route('zalo.webhook.bot', ['bot' => $bot->slug]),
            // KHÔNG truyền secret sang view. Chỉ truyền trạng thái của nó —
            // màn hình này hay bị chia sẻ lúc gỡ lỗi.
            'secretOk' => BotSecretVerifier::isValidLength($secret),
            'secretLength' => strlen($secret),
            'suggestedSecret' => BotSecretVerifier::generate(),
        ]);
    }

    /**
     * Sửa bot đã thêm.
     *
     * Token để trống nghĩa là GIỮ NGUYÊN. Bắt nhập lại token mỗi lần đổi tên
     * là cách chắc chắn khiến người ta dán nhầm token khác vào.
     */
    public function update(Request $request, ZaloBot $bot, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        $newToken = trim((string) ($data['token'] ?? ''));
        $original = $bot->token;

        $bot->forceFill(['name' => $data['name']]);

        if ($newToken !== '') {
            $bot->forceFill(['token' => $newToken]);
        }

        $bot->save();

        // Token mới phải kiểm NGAY: lưu một token hỏng nghĩa là bot chết im
        // lặng cho tới lần gửi tin tiếp theo, có thể là nhiều ngày sau.
        if ($newToken !== '' && $newToken !== $original) {
            try {
                $info = $zalo->bot($bot->slug)->me();
            } catch (ZaloException $e) {
                $bot->forceFill(['token' => $original])->save();

                return back()->with('zalo.error', 'Token mới không dùng được nên đã hoàn lại token cũ: '.$e->getMessage());
            }

            /** @var array<string, mixed> $payload */
            $payload = (array) $info->payload();
            $name = $payload['username'] ?? $payload['account_name'] ?? null;

            if ($name !== null) {
                $bot->forceFill(['username' => (string) $name])->save();
            }
        }

        ZaloAuditLog::record('bot.updated', $bot);

        return back()->with('zalo.success', "Đã cập nhật bot `{$bot->slug}`.");
    }

    /** Gửi một tin thật để xác nhận luồng chạy — công cụ chẩn đoán, không phải công cụ vận hành. */
    public function send(Request $request, ZaloBot $bot, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'chat_id' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'url', 'max:2000'],
        ]);

        try {
            $channel = $zalo->bot($bot->slug);
            $photo = trim((string) ($data['photo'] ?? ''));

            $response = $photo === ''
                ? $channel->text($data['chat_id'], $data['text'])
                : $channel->photo($data['chat_id'], $photo, $data['text']);
        } catch (ApiException $e) {
            // In cả mã lỗi: tài liệu Zalo tra theo mã, không tra theo câu chữ.
            return back()->with('zalo.error', "Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}");
        } catch (ZaloException $e) {
            return back()->with('zalo.error', $e->getMessage());
        }

        /** @var array<string, mixed> $result */
        $result = $response->get('result', []);

        return back()->with('zalo.success', sprintf(
            'Zalo đã nhận (message_id %s). Mở Zalo kiểm tra tin đã tới thật chưa — API báo ok không đảm bảo máy người nhận hiện được tin.',
            $result['message_id'] ?? '—',
        ));
    }

    public function setWebhook(ZaloBot $bot, Factory $zalo): RedirectResponse
    {
        $secret = (string) config('zalo.bot.webhook_secret', '');
        $url = route('zalo.webhook.bot', ['bot' => $bot->slug]);

        if (! BotSecretVerifier::isValidLength($secret)) {
            return back()->with('zalo.error', 'Chưa có ZALO_BOT_WEBHOOK_SECRET hợp lệ (8-256 ký tự) trong .env.');
        }

        if (! str_starts_with($url, 'https://')) {
            // Secret đi nguyên văn trong header — HTTP là để lộ nó.
            return back()->with('zalo.error', 'Webhook phải là HTTPS. Zalo gửi secret nguyên văn ở header nên HTTP là để lộ.');
        }

        try {
            $zalo->bot($bot->slug)->updates()->setWebhook($url, $secret);
        } catch (ZaloException $e) {
            return back()->with('zalo.error', $e->getMessage());
        }

        ZaloAuditLog::record('bot.webhook.set', $bot, ['url' => $url]);

        return back()->with('zalo.success', 'Đã cắm webhook. Nhắn cho bot một câu rồi tải lại trang để thấy chat_id.');
    }

    public function deleteWebhook(ZaloBot $bot, Factory $zalo): RedirectResponse
    {
        try {
            $zalo->bot($bot->slug)->updates()->deleteWebhook();
        } catch (ZaloException $e) {
            return back()->with('zalo.error', $e->getMessage());
        }

        ZaloAuditLog::record('bot.webhook.deleted', $bot);

        return back()->with('zalo.error', 'Đã gỡ webhook — bot NGỪNG đẩy tin về ứng dụng cho tới khi cắm lại.');
    }

    public function test(ZaloBot $bot, Factory $zalo): RedirectResponse
    {
        try {
            $zalo->bot($bot->slug)->me();
        } catch (ZaloException $e) {
            return back()->with('zalo.error', "Bot `{$bot->slug}`: {$e->getMessage()}");
        }

        return back()->with('zalo.success', "Bot `{$bot->slug}` còn dùng được.");
    }

    public function toggle(ZaloBot $bot): RedirectResponse
    {
        $bot->forceFill(['is_active' => ! $bot->is_active])->save();
        ZaloAuditLog::record($bot->is_active ? 'bot.enabled' : 'bot.disabled', $bot);

        return back()->with(
            'zalo.success',
            'Đã '.($bot->is_active ? 'bật' : 'tắt')." bot `{$bot->slug}`."
        );
    }

    public function destroy(ZaloBot $bot): RedirectResponse
    {
        $slug = $bot->slug;

        ZaloAuditLog::record('bot.deleted', $bot, ['slug' => $slug]);
        $bot->forceDelete();

        return redirect()->route('zalo.bots.index')
            ->with('zalo.success', "Đã xoá bot `{$slug}`.");
    }
}
