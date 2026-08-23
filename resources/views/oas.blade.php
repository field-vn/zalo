@extends('zalo::layout')

@section('title', 'Official Account')

@section('content')
    <h1>Official Account</h1>
    <p class="zl-sub">Thêm OA, cấp quyền và theo dõi trạng thái token.</p>

    @if ($oas->isEmpty())
        <div class="zl-card zl-empty">Chưa có OA nào. Thêm một cái ở form bên dưới.</div>
    @else
        <div class="zl-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>OA</th>
                    <th>OA ID</th>
                    <th>Trạng thái</th>
                    <th style="width:1%"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($oas as $oa)
                    <tr>
                        <td>
                            <a href="{{ route('zalo.oas.show', $oa) }}"><strong>{{ $oa->name }}</strong></a>
                            <div class="zl-hint zl-mono">{{ $oa->slug }}</div>
                        </td>
                        <td class="zl-mono">{{ $oa->oa_id }}</td>
                        <td>{!! $statusBadge($oa) !!}</td>
                        <td>
                            <div class="zl-actions">
                                <a href="{{ route('zalo.oa.authorize', $oa) }}" class="zl-btn zl-btn-primary">
                                    {{ $oa->token === null ? 'Cấp quyền' : 'Cấp lại' }}
                                </a>

                                @if ($oa->token !== null)
                                    <form method="POST" action="{{ route('zalo.oas.test', $oa) }}">
                                        @csrf
                                        <button class="zl-btn">Kiểm tra</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('zalo.oas.toggle', $oa) }}">
                                    @csrf
                                    <button class="zl-btn">{{ $oa->is_active ? 'Tắt' : 'Bật' }}</button>
                                </form>

                                <form method="POST" action="{{ route('zalo.oas.destroy', $oa) }}"
                                      onsubmit="return confirm('Xoá OA {{ $oa->slug }}? Token đã lưu cũng mất theo.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="zl-btn zl-btn-danger">Xoá</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2>Thêm Official Account</h2>
    <div class="zl-card">
        <form method="POST" action="{{ route('zalo.oas.store') }}">
            @csrf
            <div class="zl-form-row">
                <div class="zl-field">
                    <label for="name">Tên</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    <div class="zl-hint">Tên gợi nhớ, chỉ hiển thị ở đây.</div>
                </div>

                <div class="zl-field">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="cskh">
                    <div class="zl-hint">Dùng trong code: <code>Zalo::oa('cskh')</code></div>
                </div>

                <div class="zl-field">
                    <label for="oa_id">OA ID</label>
                    <input type="text" id="oa_id" name="oa_id" value="{{ old('oa_id') }}" required>
                    <div class="zl-hint">Lấy ở trang quản trị Zalo OA.</div>
                </div>
            </div>

            <div class="zl-field">
                <label for="tags">Tag (tuỳ chọn)</label>
                <input type="text" id="tags" name="tags" value="{{ old('tags') }}" placeholder="cskh, ban-hang">
                <div class="zl-hint">Ngăn cách bởi dấu phẩy. Dùng để lọc khi phân phối tin nhắn.</div>
            </div>

            <button class="zl-btn zl-btn-primary">Thêm OA</button>
            <span class="zl-hint" style="margin-left:10px">Thêm xong sẽ cần bấm Cấp quyền.</span>
        </form>
    </div>
@endsection
