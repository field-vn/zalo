@extends('zalo::layout')

@section('title', 'Tổng quan')

@section('content')
    <h1>Tổng quan</h1>
    <p class="zl-sub">Trạng thái kết nối Zalo của ứng dụng này.</p>

    @foreach ($warnings as $warning)
        <div class="zl-alert zl-alert-warn">{{ $warning }}</div>
    @endforeach

    <div class="zl-grid">
        <div class="zl-card">
            <div class="zl-stat-label">Zalo App</div>
            <div class="zl-stat-value">
                @if ($appId !== '')
                    <span class="zl-badge zl-badge-ok">đã cấu hình</span>
                    <div class="zl-hint zl-mono">{{ $appId }}</div>
                @else
                    <span class="zl-badge zl-badge-err">thiếu ZALO_APP_ID</span>
                @endif
            </div>
        </div>

        <div class="zl-card">
            <div class="zl-stat-label">Official Account</div>
            <div class="zl-stat-value">{{ $oas->count() }} đang hoạt động</div>
        </div>

        <div class="zl-card">
            <div class="zl-stat-label">Bot</div>
            <div class="zl-stat-value">{{ $bots->count() }} đang hoạt động</div>
        </div>

        <div class="zl-card">
            <div class="zl-stat-label">Tự refresh token</div>
            <div class="zl-stat-value">
                @if ($schedulerEnabled)
                    <span class="zl-badge zl-badge-ok">đang bật</span>
                @else
                    <span class="zl-badge zl-badge-warn">đã tắt</span>
                @endif
            </div>
        </div>
    </div>

    <h2>URL phải khai trong Zalo Developers</h2>

    <div class="zl-alert zl-alert-warn">
        Zalo chỉ chấp nhận URL thuộc <strong>domain đã xác thực</strong>. Chưa xác thực thì
        webhook báo <em>"chưa được xác thực domain"</em> và OAuth báo
        <code>-14003 Invalid redirect uri</code>. Xác thực tại
        <strong>Zalo Developers → App → Xác thực domain</strong>: tải file HTML họ cấp,
        đặt vào thư mục <code>public/</code> của dự án, rồi bấm xác thực.
    </div>

    <div class="zl-card">
        <p class="zl-hint" style="margin-top:0">
            <strong>Redirect URI</strong> — App → Official Account → Callback URL.
            Phải khớp <strong>CHÍNH XÁC</strong>, thừa/thiếu dấu <code>/</code> cuối cũng bị từ chối
            (<code>error_code -14003</code>).
        </p>
        <div class="zl-copy">
            <input type="text" readonly value="{{ $redirectUri }}" id="zl-redirect-uri" onclick="this.select()">
            <button type="button" class="zl-btn" onclick="
                document.getElementById('zl-redirect-uri').select();
                document.execCommand('copy');
                this.textContent='Đã copy';
                setTimeout(()=>this.textContent='Copy',1500);
            ">Copy</button>
        </div>

        @unless ($isHttps)
            <div class="zl-alert zl-alert-warn" style="margin:14px 0 0">
                URL đang là <strong>HTTP</strong>. Zalo thường từ chối redirect URI không phải HTTPS
                và domain chưa xác minh — đây là nguyên nhân phổ biến nhất của lỗi
                <code>-14003 Invalid redirect uri</code>.
            </div>
        @endunless
    </div>

    <div class="zl-card">
        <p class="zl-hint" style="margin-top:0">
            <strong>Webhook URL</strong> — App → Official Account → Webhook.
        </p>
        <div class="zl-copy">
            <input type="text" readonly value="{{ $webhookUrl }}" id="zl-webhook-url" onclick="this.select()">
            <button type="button" class="zl-btn" onclick="
                document.getElementById('zl-webhook-url').select();
                document.execCommand('copy');
                this.textContent='Đã copy';
                setTimeout(()=>this.textContent='Copy',1500);
            ">Copy</button>
        </div>

        @if (! $webhookSecretSet)
            <div class="zl-alert zl-alert-err" style="margin:14px 0 0">
                Chưa đặt <code>ZALO_WEBHOOK_SECRET</code> — mọi webhook đang bị từ chối.
            </div>
        @endif
    </div>

    <h2>Sức khoẻ token</h2>

    @if ($oas->isEmpty())
        <div class="zl-card zl-empty">
            Chưa có Official Account nào.
            <div style="margin-top:12px">
                <a href="{{ route('zalo.oas.index') }}" class="zl-btn zl-btn-primary">Thêm OA</a>
            </div>
        </div>
    @else
        <div class="zl-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>OA</th>
                    <th>Access token</th>
                    <th>Refresh token</th>
                    <th>Trạng thái</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($oas as $oa)
                    <tr>
                        <td>
                            <strong>{{ $oa->name }}</strong>
                            <div class="zl-hint zl-mono">{{ $oa->slug }}</div>
                        </td>
                        <td>{{ $tokenSummary($oa)['access'] }}</td>
                        <td>{{ $tokenSummary($oa)['rotate'] }}</td>
                        <td>{!! $statusBadge($oa) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
