<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloAuditLog;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\OaPresenter;
use FieldVn\Zalo\Support\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OaController
{
    public function index(OaRepository $oas): View
    {
        return view('zalo::oas', [
            'oas' => $oas->all(),
            'statusBadge' => OaPresenter::statusBadge(...),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100'],
            // Table::name() thay vì dựng model chỉ để hỏi tên bảng.
            'oa_id' => ['required', 'string', 'max:64', Rule::unique(Table::name(Table::OAS), 'oa_id')],
            'tags' => ['nullable', 'string', 'max:255'],
        ], [
            'oa_id.unique' => 'OA ID này đã được thêm rồi.',
        ]);

        $slug = Str::slug($data['slug'] ?? '') ?: Str::slug($data['name']);

        if (ZaloOa::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->with('zalo.error', "Slug `{$slug}` đã tồn tại.");
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))));

        $oa = ZaloOa::create([
            'name' => $data['name'],
            'slug' => $slug,
            'oa_id' => $data['oa_id'],
            'tags' => $tags ?: null,
            // Chưa có token thì chưa dùng được — bật sau khi cấp quyền xong.
            'is_active' => false,
        ]);

        ZaloAuditLog::record('oa.created', $oa);

        return redirect()->route('zalo.oas.index')
            ->with('zalo.success', "Đã thêm OA `{$oa->slug}`. Bấm Cấp quyền để kết nối.");
    }

    public function test(ZaloOa $oa, Factory $zalo): RedirectResponse
    {
        try {
            $info = $zalo->oa($oa->slug)->info();
        } catch (ZaloException $e) {
            return back()->with('zalo.error', "OA `{$oa->slug}`: {$e->getMessage()}");
        }

        /** @var array<string, mixed> $data */
        $data = (array) $info->payload();

        return back()->with(
            'zalo.success',
            "OA `{$oa->slug}` kết nối bình thường".
                (isset($data['name']) ? " — {$data['name']}" : '').'.'
        );
    }

    public function toggle(ZaloOa $oa): RedirectResponse
    {
        // Bật một OA chưa có token chỉ tạo ra lỗi khó hiểu lúc gửi tin.
        if (! $oa->is_active && $oa->token === null) {
            return back()->with('zalo.error', "OA `{$oa->slug}` chưa được cấp quyền nên chưa thể bật.");
        }

        $oa->forceFill(['is_active' => ! $oa->is_active])->save();
        ZaloAuditLog::record($oa->is_active ? 'oa.enabled' : 'oa.disabled', $oa);

        return back()->with(
            'zalo.success',
            'Đã '.($oa->is_active ? 'bật' : 'tắt')." OA `{$oa->slug}`."
        );
    }

    public function destroy(ZaloOa $oa): RedirectResponse
    {
        $slug = $oa->slug;

        ZaloAuditLog::record('oa.deleted', $oa, ['slug' => $slug, 'oa_id' => $oa->oa_id]);

        // Xoá hẳn: soft delete sẽ giữ lại unique slug/oa_id và chặn người dùng
        // thêm lại chính OA đó, mà không có cách nào khôi phục từ UI.
        $oa->token()->delete();
        $oa->forceDelete();

        return redirect()->route('zalo.oas.index')
            ->with('zalo.success', "Đã xoá OA `{$slug}`.");
    }
}
