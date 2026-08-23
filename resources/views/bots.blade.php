@extends('zalo::layout')

@section('title', 'Bot')

@section('content')
    <h1>Zalo Bot</h1>
    <p class="zl-sub">
        Token tĩnh, không cần cấp quyền — đơn giản hơn OA nhiều.
        Lấy token ở <a href="https://bot.zaloplatforms.com" target="_blank" rel="noopener">bot.zaloplatforms.com</a>.
    </p>

    @if ($bots->isEmpty())
        <div class="zl-card zl-empty">Chưa có bot nào.</div>
    @else
        <div class="zl-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Bot</th>
                    <th>Username</th>
                    <th>Token</th>
                    <th>Trạng thái</th>
                    <th style="width:1%"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($bots as $bot)
                    <tr>
                        <td>
                            <a href="{{ route('zalo.bots.show', $bot) }}"><strong>{{ $bot->name }}</strong></a>
                            <div class="zl-hint zl-mono">{{ $bot->slug }}</div>
                        </td>
                        <td>{{ $bot->username !== null ? '@'.$bot->username : '—' }}</td>
                        {{-- Token không bao giờ hiện đầy đủ, kể cả với người đã đăng nhập. --}}
                        <td class="zl-mono">{{ $bot->maskedToken() }}</td>
                        <td>
                            @if ($bot->is_active)
                                <span class="zl-badge zl-badge-ok">hoạt động</span>
                            @else
                                <span class="zl-badge zl-badge-mute">đã tắt</span>
                            @endif
                        </td>
                        <td>
                            <div class="zl-actions">
                                <a href="{{ route('zalo.bots.show', $bot) }}" class="zl-btn">Chi tiết</a>

                                <form method="POST" action="{{ route('zalo.bots.test', $bot) }}">
                                    @csrf
                                    <button class="zl-btn">Kiểm tra</button>
                                </form>

                                <form method="POST" action="{{ route('zalo.bots.toggle', $bot) }}">
                                    @csrf
                                    <button class="zl-btn">{{ $bot->is_active ? 'Tắt' : 'Bật' }}</button>
                                </form>

                                <form method="POST" action="{{ route('zalo.bots.destroy', $bot) }}"
                                      onsubmit="return confirm('Xoá bot {{ $bot->slug }}?')">
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

    <h2>Thêm Bot</h2>
    <div class="zl-card">
        <form method="POST" action="{{ route('zalo.bots.store') }}">
            @csrf
            <div class="zl-form-row">
                <div class="zl-field">
                    <label for="name">Tên</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="zl-field">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="support">
                    <div class="zl-hint">Dùng trong code: <code>Zalo::bot('support')</code></div>
                </div>
            </div>

            <div class="zl-field">
                <label for="token">Bot token</label>
                {{-- type=password để không lộ token khi chia sẻ màn hình. --}}
                <input type="password" id="token" name="token" required placeholder="123456:abcdef…">
                <div class="zl-hint">Token sẽ được kiểm tra ngay; nếu sai thì bot không được tạo.</div>
            </div>

            <button class="zl-btn zl-btn-primary">Thêm Bot</button>
        </form>
    </div>
@endsection
