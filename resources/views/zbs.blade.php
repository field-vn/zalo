@extends('zalo::layout')

@section('title', 'ZBS '.$oa->slug)

@section('content')
    <h1>ZBS Template Message</h1>
    <p class="zl-sub">
        OA <span class="zl-mono">{{ $oa->slug }}</span>
        · <a href="{{ route('zalo.oas.show', $oa) }}">← Chi tiết OA</a>
    </p>

    <p class="zl-hint" style="margin-top:0">
        Đây là kênh duy nhất gửi được tới người <strong>chưa từng tương tác</strong>
        với OA. Đổi lại, chỉ gửi được theo mẫu đã đăng ký và mỗi tin đều tính phí.
    </p>

    @if ($mode === 'production')
        <div class="zl-alert zl-alert-err">
            <code>ZALO_ZBS_MODE=production</code> — mọi tin gửi từ code đều
            <strong>tính phí</strong>. Form dưới đây vẫn mặc định development.
        </div>
    @endif

    @if ($error !== null)
        <div class="zl-alert zl-alert-err">{{ $error }}</div>
    @endif

    @if ($quota !== null && $quota !== [])
        <div class="zl-grid">
            <div class="zl-card">
                <div class="zl-stat-label">Hạn mức hôm nay</div>
                <div class="zl-stat-value">{{ $quota['dailyQuota'] ?? '—' }}</div>
            </div>
            <div class="zl-card">
                <div class="zl-stat-label">Còn lại</div>
                <div class="zl-stat-value">{{ $quota['remainingQuota'] ?? '—' }}</div>
            </div>
        </div>
    @endif

    <h2>Mẫu tin</h2>

    <div class="zl-card">
        @if ($templates === [])
            <div class="zl-alert zl-alert-warn" style="margin:0">
                OA này chưa có mẫu tin nào. Tạo mẫu trong tài khoản ZBS rồi gửi duyệt.
            </div>
        @else
            <div class="zl-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>template_id</th>
                        <th>Tên</th>
                        <th>Trạng thái</th>
                        <th>Chất lượng</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $t)
                        @php
                            $tid = (string) ($t['templateId'] ?? $t['template_id'] ?? '');
                            $st = (string) ($t['status'] ?? '—');
                            $isCurrent = $selected !== null
                                && $tid === (string) ($selected['templateId'] ?? $selected['template_id'] ?? '');
                        @endphp
                        <tr @class(['zl-row-active' => $isCurrent])>
                            <td class="zl-mono">{{ $tid }}</td>
                            <td>{{ $t['templateName'] ?? $t['template_name'] ?? '—' }}</td>
                            <td>
                                <span @class([
                                    'zl-badge',
                                    'zl-badge-ok' => $st === 'ENABLE',
                                    'zl-badge-warn' => $st === 'PENDING_REVIEW',
                                    'zl-badge-err' => in_array($st, ['REJECT', 'DISABLE', 'DELETE'], true),
                                ])>{{ $st }}</span>
                            </td>
                            <td>{{ $t['templateQuality'] ?? '—' }}</td>
                            <td>
                                <div class="zl-actions">
                                    <a class="zl-btn" href="{{ route('zalo.oas.zbs', ['oa' => $oa, 'template' => $tid]) }}">
                                        {{ $isCurrent ? 'Đang chọn' : 'Chọn' }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    @if ($selected !== null)
        @php
            $tid = (string) ($selected['templateId'] ?? $selected['template_id'] ?? '');
            $status = (string) ($selected['status'] ?? '');
        @endphp

        <h2>Gửi thử</h2>

        <div class="zl-card">
            @if ($status === 'PENDING_REVIEW')
                <div class="zl-alert zl-alert-warn">
                    Mẫu đang chờ duyệt. Chế độ <strong>development</strong> vẫn gửi được,
                    nhưng chỉ tới <strong>quản trị viên của OA hoặc của App</strong> đang giữ token.
                </div>
            @endif

            <form method="POST" action="{{ route('zalo.oas.zbs.send', $oa) }}">
                @csrf
                <input type="hidden" name="template_id" value="{{ $tid }}">

                <div class="zl-field">
                    <label for="zl-phone">Số điện thoại người nhận</label>
                    <input type="text" id="zl-phone" name="phone" required
                           value="{{ old('phone') }}" placeholder="0987654321">
                    <div class="zl-hint">
                        Nhận mọi cách viết — <code>0987…</code>, <code>+8498…</code>,
                        <code>8498…</code> — và tự quy về dạng Zalo yêu cầu.
                    </div>
                </div>

                @if ($params !== [])
                    @foreach ($params as $p)
                        @php
                            $name = (string) ($p['name'] ?? '');
                            $max = $p['maxLength'] ?? null;
                            $required = (bool) ($p['require'] ?? false);
                        @endphp
                        <div class="zl-field">
                            <label for="zl-p-{{ $name }}">
                                {{ $name }}
                                @if ($required)<span style="color:#c0392b">*</span>@endif
                            </label>
                            <input type="text" id="zl-p-{{ $name }}"
                                   name="params[{{ $name }}]"
                                   value="{{ old('params.'.$name) }}"
                                   @if ($max) maxlength="{{ $max }}" @endif
                                   @if ($required) required @endif>
                            <div class="zl-hint">
                                Kiểu {{ $p['type'] ?? '—' }}@if ($max), tối đa {{ $max }} ký tự@endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="zl-field">
                        <label for="zl-raw">Tham số (JSON)</label>
                        <textarea id="zl-raw" name="raw" rows="4"
                                  placeholder='{"customer_name":"Nguyễn Văn A"}'>{{ old('raw') }}</textarea>
                        <div class="zl-hint">
                            Zalo chưa trả về danh sách tham số cho mẫu này — thường gặp
                            khi mẫu <strong>chưa được duyệt</strong>. Mở mẫu bên Zalo,
                            chép đúng tên trong cột <em>Tên tham số</em> (bỏ dấu
                            <code>&lt;&gt;</code>) rồi nhập ở đây.
                        </div>
                    </div>
                @endif

                <div class="zl-field">
                    <label style="font-weight:400">
                        <input type="checkbox" name="production" value="1"
                               {{ old('production') ? 'checked' : '' }}>
                        Gửi ở chế độ <strong>production</strong>
                    </label>
                    <div class="zl-hint">
                        Không tick: development — chỉ tới quản trị viên, không trừ số dư chính.
                        Có tick: gửi tới số bất kỳ và <strong>tính phí từng tin</strong>.
                    </div>
                    <label style="font-weight:400;margin-top:6px;display:block">
                        <input type="checkbox" name="confirm" value="1">
                        Tôi xác nhận muốn gửi thật và chịu phí
                    </label>
                </div>

                <button class="zl-btn zl-btn-primary">Gửi</button>
            </form>
        </div>
    @endif

    <h2>Tra trạng thái giao tin</h2>

    <div class="zl-card">
        <p class="zl-hint" style="margin-top:0">
            Gửi xong Zalo trả <code>msg_id</code> — đó là lúc Zalo <strong>nhận</strong>
            tin, chưa phải lúc người dùng <strong>nhận được</strong>. Tin không tới thì
            tra ở đây.
        </p>

        <form method="POST" action="{{ route('zalo.oas.zbs.status', $oa) }}">
            @csrf
            <div class="zl-field">
                <label for="zl-msg">msg_id</label>
                <input type="text" id="zl-msg" name="message_id" required
                       value="{{ session('zalo.msg_id') }}">
            </div>
            <button class="zl-btn">Tra</button>
        </form>
    </div>
@endsection
