@extends('zalo::layout')

@section('title', 'Bot '.$bot->slug)

@section('content')
    <h1>{{ $bot->name }}</h1>
    <p class="zl-sub">
        <span class="zl-mono">{{ $bot->slug }}</span>
        @if ($bot->username !== null) · <span class="zl-mono">{{ '@'.$bot->username }}</span> @endif
        @if ($bot->is_active)
            · <span class="zl-badge zl-badge-ok">hoạt động</span>
        @else
            · <span class="zl-badge zl-badge-mute">đã tắt</span>
        @endif
        · <a href="{{ route('zalo.bots.index') }}">← Danh sách bot</a>
    </p>

    <h2>Webhook</h2>

    <div class="zl-card">
        <p class="zl-hint" style="margin-top:0">
            URL riêng của bot này. Mỗi bot một đường dẫn, vì payload Zalo gửi
            không kèm định danh bot nào.
        </p>

        <div class="zl-copy">
            <input type="text" readonly value="{{ $webhookUrl }}" id="zl-bot-hook" onclick="this.select()">
            <button type="button" class="zl-btn" onclick="
                document.getElementById('zl-bot-hook').select();
                document.execCommand('copy');
                this.textContent='Đã copy';
                setTimeout(()=>this.textContent='Copy',1500);
            ">Copy</button>
        </div>

        @if ($secretOk)
            <p class="zl-hint">
                <span class="zl-badge zl-badge-ok">secret hợp lệ</span>
                {{ $secretLength }} ký tự — do bạn tự đặt, khác OA Secret Key do Zalo cấp.
            </p>

            @unless (str_starts_with($webhookUrl, 'https://'))
                <div class="zl-alert zl-alert-err" style="margin:14px 0 0">
                    URL đang là <strong>HTTP</strong> nên không cắm được. Zalo gửi secret
                    <strong>nguyên văn</strong> ở header, HTTP là để lộ nó.
                    Dùng tunnel HTTPS (cloudflared/ngrok) rồi đặt lại <code>APP_URL</code>.
                </div>
            @endunless

            <div class="zl-actions" style="margin-top:14px">
                <form method="POST" action="{{ route('zalo.bots.webhook.set', $bot) }}">
                    @csrf
                    <button class="zl-btn zl-btn-primary"
                            @disabled(! str_starts_with($webhookUrl, 'https://'))>Cắm webhook</button>
                </form>

                <form method="POST" action="{{ route('zalo.bots.webhook.delete', $bot) }}"
                      onsubmit="return confirm('Gỡ webhook? Bot sẽ ngừng đẩy tin về ứng dụng.')">
                    @csrf
                    @method('DELETE')
                    <button class="zl-btn zl-btn-danger">Gỡ webhook</button>
                </form>
            </div>
        @else
            <div class="zl-alert zl-alert-err" style="margin:14px 0 0">
                Chưa có <code>ZALO_BOT_WEBHOOK_SECRET</code> hợp lệ (cần 8–256 ký tự,
                hiện {{ $secretLength }}). Zalo bắt buộc phải có, và webhook đang bị
                từ chối toàn bộ.
            </div>

            <p class="zl-hint">Thêm dòng này vào <code>.env</code> rồi chạy <code>php artisan config:clear</code>:</p>

            <div class="zl-copy">
                <input type="text" readonly value="ZALO_BOT_WEBHOOK_SECRET={{ $suggestedSecret }}"
                       id="zl-bot-secret" onclick="this.select()">
                <button type="button" class="zl-btn" onclick="
                    document.getElementById('zl-bot-secret').select();
                    document.execCommand('copy');
                    this.textContent='Đã copy';
                    setTimeout(()=>this.textContent='Copy',1500);
                ">Copy</button>
            </div>
            <p class="zl-hint">Chuỗi này sinh ngẫu nhiên mỗi lần tải trang — copy xong dùng luôn.</p>
        @endif
    </div>

    <h2>Hội thoại</h2>

    @if ($chats->isEmpty())
        <div class="zl-card zl-empty">
            Chưa ai nhắn cho bot.
            <div class="zl-hint" style="margin-top:12px">
                Danh sách này chỉ đầy lên khi webhook về được:
                cắm webhook ở trên → mở Zalo nhắn cho bot một câu → tải lại trang.
            </div>
        </div>
    @else
        <div class="zl-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>chat_id</th>
                    <th>Người nhắn</th>
                    <th>Số tin</th>
                    <th>Lần cuối</th>
                    <th>Nội dung cuối</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($chats as $chat)
                    <tr>
                        <td>
                            {{-- Bấm là chọn hết, dán thẳng vào ô gửi thử bên dưới. --}}
                            <span class="zl-mono" style="cursor:pointer" title="Bấm để chọn"
                                  onclick="
                                    const r=document.createRange(); r.selectNodeContents(this);
                                    const s=getSelection(); s.removeAllRanges(); s.addRange(r);
                                    document.execCommand('copy');
                                    document.getElementById('zl-chat-id').value=this.textContent.trim();
                                  ">{{ $chat->chat_id }}</span>
                        </td>
                        <td>{{ $chat->display_name ?? '—' }}</td>
                        <td>{{ $chat->message_count }}</td>
                        <td>{{ $chat->last_message_at?->format('d/m H:i') ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) ($chat->last_message ?? '—'), 40) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="zl-hint">Bấm vào <span class="zl-mono">chat_id</span> để copy và điền sẵn vào ô gửi thử.</p>
    @endif

    <h2>Gửi tin thử</h2>

    <div class="zl-card">
        <p class="zl-hint" style="margin-top:0">
            Gửi một tin THẬT. Dùng để xác nhận token và luồng gửi còn chạy,
            không phải để nhắn tin hằng ngày.
        </p>

        <form method="POST" action="{{ route('zalo.bots.send', $bot) }}">
            @csrf
            <div class="zl-field">
                <label for="zl-chat-id">chat_id</label>
                <input type="text" id="zl-chat-id" name="chat_id" value="{{ old('chat_id') }}" required
                       placeholder="Chọn từ bảng hội thoại ở trên">
            </div>

            <div class="zl-field">
                <label for="zl-text">Nội dung</label>
                <input type="text" id="zl-text" name="text" required
                       value="{{ old('text', 'Tin kiểm thử lúc '.now()->format('H:i:s d/m/Y')) }}">
            </div>

            <div class="zl-field">
                <label for="zl-photo">URL ảnh (tuỳ chọn)</label>
                <input type="url" id="zl-photo" name="photo" value="{{ old('photo') }}"
                       placeholder="https://…">
                <div class="zl-hint">
                    Có URL thì gửi ảnh, nội dung ở trên thành chú thích.
                    Bot nhận thẳng URL — khác OA, vốn bắt upload trước.
                </div>
            </div>

            <button class="zl-btn zl-btn-primary">Gửi</button>
        </form>
    </div>

    <h2>Cấu hình</h2>

    <div class="zl-card">
        <form method="POST" action="{{ route('zalo.bots.update', $bot) }}">
            @csrf
            @method('PUT')

            <div class="zl-field">
                <label for="zl-name">Tên</label>
                <input type="text" id="zl-name" name="name" value="{{ old('name', $bot->name) }}" required>
            </div>

            <div class="zl-field">
                <label for="zl-token">Token mới</label>
                <input type="password" id="zl-token" name="token" placeholder="{{ $bot->maskedToken() }}">
                <div class="zl-hint">
                    Để trống thì giữ nguyên token cũ. Nhập token mới thì nó được
                    kiểm tra ngay; nếu hỏng, token cũ được hoàn lại.
                </div>
            </div>

            <p class="zl-hint">
                Slug <span class="zl-mono">{{ $bot->slug }}</span> không sửa được —
                code trong dự án đang gọi theo slug, và URL webhook cũng dựng từ nó.
            </p>

            <button class="zl-btn zl-btn-primary">Lưu</button>
        </form>
    </div>
@endsection
