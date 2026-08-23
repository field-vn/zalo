@extends('zalo::layout')

@section('title', 'OA '.$oa->slug)

@section('content')
    <h1>{{ $oa->name }}</h1>
    <p class="zl-sub">
        <span class="zl-mono">{{ $oa->slug }}</span>
        · OA ID <span class="zl-mono">{{ $oa->oa_id }}</span>
        · {!! $statusBadge($oa) !!}
        · <a href="{{ route('zalo.oas.index') }}">← Danh sách OA</a>
    </p>

    <h2>Kết nối</h2>

    <div class="zl-grid">
        <div class="zl-card">
            <div class="zl-stat-label">Access token</div>
            <div class="zl-stat-value">{{ $tokenSummary($oa)['access'] }}</div>
        </div>

        <div class="zl-card">
            <div class="zl-stat-label">Refresh token</div>
            <div class="zl-stat-value">{{ $tokenSummary($oa)['rotate'] }}</div>
        </div>
    </div>

    <div class="zl-card">
        <div class="zl-actions">
            <a href="{{ route('zalo.oa.authorize', $oa) }}" class="zl-btn zl-btn-primary">
                {{ $oa->token === null ? 'Cấp quyền' : 'Cấp lại quyền' }}
            </a>

            @if ($oa->token !== null)
                <form method="POST" action="{{ route('zalo.oas.test', $oa) }}">
                    @csrf
                    <button class="zl-btn">Kiểm tra kết nối</button>
                </form>
            @endif

            <form method="POST" action="{{ route('zalo.oas.toggle', $oa) }}">
                @csrf
                <button class="zl-btn">{{ $oa->is_active ? 'Tắt' : 'Bật' }}</button>
            </form>
        </div>
    </div>

    <h2>Gửi tin thử</h2>

    <div class="zl-card">
        @if ($oa->token === null)
            <div class="zl-alert zl-alert-warn" style="margin:0">
                OA chưa được cấp quyền nên chưa gửi được gì. Bấm <strong>Cấp quyền</strong> ở trên trước.
            </div>
        @else
            <p class="zl-hint" style="margin-top:0">
                Gửi một tin THẬT dạng tư vấn. Zalo chỉ cho gửi trong <strong>48 giờ</strong>
                kể từ tin cuối của người nhận — ngoài khung đó sẽ bị từ chối, và đó là
                giới hạn của Zalo chứ không phải lỗi cấu hình.
            </p>

            <form method="POST" action="{{ route('zalo.oas.send', $oa) }}">
                @csrf
                <div class="zl-field">
                    <label for="zl-user-id">user_id người nhận</label>
                    <input type="text" id="zl-user-id" name="user_id" value="{{ old('user_id') }}" required
                           placeholder="Lấy từ webhook khi người dùng nhắn tới OA">
                    <div class="zl-hint">
                        Đây KHÔNG phải số điện thoại hay tên Zalo. Nó đến từ webhook
                        <code>user_send_text</code>, hoặc từ danh sách người quan tâm.
                    </div>
                </div>

                <div class="zl-field">
                    <label for="zl-text">Nội dung</label>
                    <input type="text" id="zl-text" name="text" required
                           value="{{ old('text', 'Tin kiểm thử lúc '.now()->format('H:i:s d/m/Y')) }}">
                </div>

                <div class="zl-field">
                    <label for="zl-att">attachment_id ảnh (tuỳ chọn)</label>
                    <input type="text" id="zl-att" name="attachment_id" value="{{ old('attachment_id') }}">
                    <div class="zl-hint">
                        OA không nhận URL ảnh: phải upload trước để lấy id.
                        <code>$oa->uploads()->image($path)</code>
                    </div>
                </div>

                <button class="zl-btn zl-btn-primary">Gửi</button>
            </form>
        @endif
    </div>

    <h2>Cấu hình</h2>

    <div class="zl-card">
        <form method="POST" action="{{ route('zalo.oas.update', $oa) }}">
            @csrf
            @method('PUT')

            <div class="zl-field">
                <label for="zl-name">Tên</label>
                <input type="text" id="zl-name" name="name" value="{{ old('name', $oa->name) }}" required>
            </div>

            <div class="zl-field">
                <label for="zl-oa-id">OA ID</label>
                <input type="text" id="zl-oa-id" name="oa_id" value="{{ old('oa_id', $oa->oa_id) }}" required>
                @if ($oa->token !== null)
                    <div class="zl-hint">
                        <strong>Đổi giá trị này là chuyện lớn.</strong> Token đang lưu gắn với
                        OA hiện tại; đổi ID mà không cấp quyền lại thì mọi lời gọi API sẽ sai.
                    </div>
                @endif
            </div>

            <div class="zl-field">
                <label for="zl-tags">Tag</label>
                <input type="text" id="zl-tags" name="tags"
                       value="{{ old('tags', implode(', ', $oa->tags ?? [])) }}"
                       placeholder="cskh, khuyen-mai">
                <div class="zl-hint">
                    Ngăn cách bằng dấu phẩy. Dùng để lọc trong code:
                    <code>Zalo::oas(fn($oa) => in_array('cskh', $oa->tags ?? []))</code>
                </div>
            </div>

            <p class="zl-hint">
                Slug <span class="zl-mono">{{ $oa->slug }}</span> không sửa được —
                code trong dự án đang gọi theo slug.
            </p>

            <button class="zl-btn zl-btn-primary">Lưu</button>
        </form>
    </div>

    <div class="zl-card">
        <form method="POST" action="{{ route('zalo.oas.destroy', $oa) }}"
              onsubmit="return confirm('Xoá OA {{ $oa->slug }}? Token đã lưu cũng mất theo.')">
            @csrf
            @method('DELETE')
            <button class="zl-btn zl-btn-danger">Xoá OA này</button>
        </form>
    </div>
@endsection
