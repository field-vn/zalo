<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Channels\OA\Resources\ZbsResource;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Core\Exceptions\ZaloException;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\ZbsPreviewUrl;
use FieldVn\Zalo\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Giao diện ZBS Template Message.
 *
 * Tách khỏi trang OA vì mỗi lần mở là một lời gọi mạng tới Zalo: nhét vào
 * trang OA thì trang đó chậm đi và hỏng theo mỗi khi ZBS trục trặc.
 */
class ZbsController
{
    public function index(Request $request, ZaloOa $oa, Factory $zalo): View
    {
        $templates = [];
        $quota = null;
        $error = null;
        $zbs = null;

        try {
            $zbs = $zalo->oa($oa->slug)->zbs();

            /** @var list<array<string, mixed>> $templates */
            $templates = (array) $zbs->templates()->payload();

            /** @var array<string, mixed> $quota */
            $quota = (array) $zbs->quota()->payload();
        } catch (ApiException $e) {
            $error = $e->explain();
        } catch (ZaloException $e) {
            $error = $e->explain();
        }

        $selected = null;
        $wanted = trim((string) $request->query('template', ''));

        if ($wanted !== '' && $zbs !== null) {
            try {
                $detail = $zbs->info($wanted)->payload();

                if (is_array($detail) && $detail !== []) {
                    /** @var array<string, mixed> $detail */
                    $selected = $detail;
                }
            } catch (ApiException) {
                // Giữ bản từ danh sách nếu info/v2 lỗi — trang vẫn gửi được.
            }
        }

        if ($selected === null && $wanted !== '') {
            foreach ($templates as $t) {
                if ((string) ($t['templateId'] ?? $t['template_id'] ?? '') === $wanted) {
                    $selected = $t;
                    break;
                }
            }
        }

        return view('zalo::zbs', [
            'oa' => $oa,
            'templates' => $templates,
            'quota' => $quota,
            'error' => $error,
            'selected' => $selected,
            // Mẫu chưa duyệt thường trả listParams RỖNG dù giao diện Zalo đã
            // hiện đủ tham số. Khi đó không dựng được form nên phải cho nhập
            // JSON tay, nếu không người dùng kẹt cứng cho tới lúc duyệt xong.
            'params' => $selected === null ? [] : (array) ($selected['listParams'] ?? []),
            'previewUrl' => $selected === null
                ? null
                : ZbsPreviewUrl::from($selected['previewUrl'] ?? $selected['preview_url'] ?? null),
            'mode' => (string) config('zalo.zbs.mode', ZbsResource::MODE_DEVELOPMENT),
        ]);
    }

    public function send(Request $request, ZaloOa $oa, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'template_id' => ['required', 'string', 'max:64'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:500'],
            'raw' => ['nullable', 'string', 'max:5000'],
            'production' => ['nullable', 'in:on,1,true'],
            'confirm' => ['nullable', 'in:on,1,true'],
        ]);

        $production = (bool) ($data['production'] ?? false);

        // Tiền thật thì phải tick thêm một ô nữa. Một cú bấm nhầm không nên
        // đủ để trừ số dư.
        if ($production && ! ($data['confirm'] ?? false)) {
            return back()->withInput()->with(
                'zalo.error',
                'Chế độ production tính phí mỗi tin. Tick ô xác nhận nếu thật sự muốn gửi cho khách.'
            );
        }

        try {
            $payload = $this->buildPayload($data);
        } catch (ZaloException $e) {
            return back()->withInput()->with('zalo.error', $e->getMessage());
        }

        if ($payload === []) {
            return back()->withInput()->with(
                'zalo.error',
                'Chưa có tham số nào. Điền các ô tham số, hoặc nhập JSON nếu mẫu chưa khai tham số.'
            );
        }

        try {
            $response = $zalo->oa($oa->slug)->zbs()->send(
                phone: $data['phone'],
                templateId: $data['template_id'],
                data: $payload,
                mode: $production ? ZbsResource::MODE_PRODUCTION : ZbsResource::MODE_DEVELOPMENT,
            );
        } catch (ApiException $e) {
            return back()->withInput()->with('zalo.error', $e->explain());
        } catch (ZaloException $e) {
            return back()->withInput()->with('zalo.error', $e->getMessage());
        }

        /** @var array<string, mixed> $result */
        $result = (array) $response->payload();
        $msgId = (string) ($result['msg_id'] ?? $result['message_id'] ?? '');

        return back()->with('zalo.success', trim(
            'Zalo đã NHẬN tin'.($msgId === '' ? '' : " (msg_id {$msgId})")
            .'. Nhận không có nghĩa là đã giao — bấm Tra trạng thái để biết tin tới máy chưa.'
        ))->with('zalo.msg_id', $msgId);
    }

    /** Tra trạng thái giao tin của một msg_id. */
    public function status(Request $request, ZaloOa $oa, Factory $zalo): RedirectResponse
    {
        $data = $request->validate([
            'message_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $response = $zalo->oa($oa->slug)->zbs()->status($data['message_id']);
        } catch (ApiException $e) {
            return back()->with('zalo.error', $e->explain());
        } catch (ZaloException $e) {
            return back()->with('zalo.error', $e->getMessage());
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $response->payload();
        $status = isset($payload['status']) ? (int) $payload['status'] : null;

        // Trạng thái 0 KHÔNG phải thành công: Zalo giữ tin nhưng chưa giao
        // được. Báo nó như tin vui thì người dùng ngồi chờ một tin không tới.
        return match ($status) {
            1 => back()->with('zalo.success', 'Đã giao tới thiết bị người nhận.'),
            0 => back()->with('zalo.error', 'Zalo đã nhận nhưng CHƯA giao tới thiết bị. '
                .'Thường do số chưa có tài khoản Zalo, đã tắt nhận tin từ OA, hoặc chưa mở Zalo. '
                .'Chờ vài phút rồi tra lại.'),
            -1 => back()->with('zalo.error', 'Không tìm thấy tin này. msg_id sai, hoặc thuộc OA khác.'),
            default => back()->with('zalo.error', 'Zalo trả trạng thái lạ: '.json_encode($payload)),
        };
    }

    /**
     * Dựng template_data từ các ô tham số, hoặc từ JSON khi mẫu chưa khai tham số.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     *
     * @throws ZaloException khi JSON sai định dạng
     */
    private function buildPayload(array $data): array
    {
        /** @var array<string, string|null> $fields */
        $fields = (array) ($data['params'] ?? []);
        $payload = [];

        foreach ($fields as $name => $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $payload[(string) $name] = $value;
            }
        }

        if ($payload !== []) {
            return $payload;
        }

        $raw = trim((string) ($data['raw'] ?? ''));

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            throw new ConfigurationException(
                'JSON không hợp lệ. Cần một object, ví dụ {"customer_name":"Nguyễn Văn A"}'
            );
        }

        $out = [];

        foreach ($decoded as $k => $v) {
            if (is_scalar($v)) {
                $out[(string) $k] = (string) $v;
            }
        }

        return $out;
    }

    /** Chuẩn hoá số để hiện lại cho người dùng thấy cái sẽ thực sự gửi đi. */
    public static function preview(string $phone): string
    {
        try {
            return PhoneNumber::normalize($phone);
        } catch (ZaloException) {
            return $phone;
        }
    }
}
