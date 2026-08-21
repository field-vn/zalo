<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloAuditLog;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
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

        if (isset($payload['username'])) {
            $bot->forceFill(['username' => (string) $payload['username']])->save();
        }

        ZaloAuditLog::record('bot.created', $bot);

        return redirect()->route('zalo.bots.index')
            ->with('zalo.success', "Đã thêm bot `{$bot->slug}`.");
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
