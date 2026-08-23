<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Exceptions\ApiException;
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

    public function show(ZaloOa $oa): View
    {
        return view('zalo::oa-show', [
            'oa' => $oa,
            'statusBadge' => OaPresenter::statusBadge(...),
            'tokenSummary' => OaPresenter::tokenSummary(...),
        ]);
    }

    /**
     * Sửa OA đã thêm.
     *
     * `oa_id` sửa được nhưng có cảnh báo: token đã lưu gắn với OA cũ, đổi id
     * mà không cấp quyền lại thì mọi lời gọi sẽ đi nhầm chỗ hoặc bị từ chối.
     */
    public function update(Request $request, ZaloOa $oa): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'oa_id' => [
                'required', 'string', 'max:64',
                Rule::unique(Table::name(Table::OAS), 'oa_id')->ignore($oa->getKey()),
            ],
            'tags' => ['nullable', 'string', 'max:255'],
        ], [
            'oa_id.unique' => 'OA ID này đã thuộc về một OA khác.',
        ]);

        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))));
        $oaIdChanged = $data['oa_id'] !== $oa->oa_id;

        $oa->forceFill([
            'name' => $data['name'],
            'oa_id' => $data['oa_id'],
            'tags' => $tags ?: null,
        ])->save();

        ZaloAuditLog::record('oa.updated', $oa);

        if ($oaIdChanged && $oa->token !== null) {
            // Không tự xoá token: quyết định đó thuộc về người dùng. Nhưng
            // phải nói rõ, vì im lặng ở đây đẻ ra lỗi rất khó truy.
            return back()->with(
                'zalo.error',
                "Đã đổi OA ID nhưng token đang lưu vẫn của OA cũ. Bấm Cấp lại quyền, nếu không mọi lời gọi API sẽ sai."
            );
        }

        return back()->with('zalo.success', "Đã cập nhật OA `{$oa->slug}`.");
    }

    /** Gửi một tin thật để xác nhận luồng chạy — công cụ chẩn đoán, không phải công cụ vận hành. */
    public function send(Request $request, ZaloOa $oa, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'string', 'max:64'],
            'text' => ['required', 'string', 'max:2000'],
            'attachment_id' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $messages = $zalo->oa($oa->slug)->messages();
            $attachment = trim((string) ($data['attachment_id'] ?? ''));

            $attachment === ''
                ? $messages->text($data['user_id'], $data['text'])
                : $messages->image($data['user_id'], $attachment, $data['text']);
        } catch (ApiException $e) {
            return back()->with('zalo.error', $this->explain($e));
        } catch (ZaloException $e) {
            return back()->with('zalo.error', $e->getMessage());
        }

        return back()->with(
            'zalo.success',
            'Zalo đã nhận. Mở Zalo kiểm tra tin đã tới thật chưa — API báo ok không đảm bảo máy người nhận hiện được tin.'
        );
    }

    /**
     * Dịch mã lỗi hay gặp sang câu người đọc hiểu được.
     *
     * Zalo trả những câu như "User is not in whitelist" mà không nói phải làm
     * gì. Với tin tư vấn thì nguyên nhân gần như luôn là cửa sổ 48 giờ.
     */
    private function explain(ApiException $e): string
    {
        $hint = match ($e->errorCode) {
            -216, -217, -32, -124 => ' Token hết hạn hoặc bị thu hồi — bấm Cấp lại quyền.',
            -230, -231 => ' Người này chưa nhắn cho OA trong 48 giờ qua, nên chỉ gửi được tin giao dịch hoặc truyền thông.',
            -201 => ' Sai user_id, hoặc người này chưa từng tương tác với OA.',
            default => '',
        };

        return "Zalo từ chối — mã {$e->errorCode}: {$e->getMessage()}.".$hint;
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
