@extends('zalo::layout')

@section('title', 'Đã liên kết Official Account')

@push('head')
    <meta http-equiv="refresh" content="{{ (int) $redirectSeconds }};url={{ $homeUrl }}">
@endpush

@section('content')
    <div class="zl-result">
        <h1>Đã liên kết Official Account</h1>
        <p class="zl-sub">
            OA <strong>{{ $oaName }}</strong> đã kết nối thành công.
            Ứng dụng được phép gửi tin nhắn thay mặt OA này.
        </p>

        <a href="{{ $homeUrl }}" class="zl-btn zl-btn-primary" id="zl-home">Về trang chủ</a>
        <p class="zl-hint" id="zl-redirect-hint">
            Tự chuyển về trang chủ sau <span id="zl-countdown">{{ $redirectSeconds }}</span> giây.
        </p>
    </div>

    <script>
        (function () {
            var seconds = {{ (int) $redirectSeconds }};
            var home = @json($homeUrl);
            var label = document.getElementById('zl-countdown');
            var timer = window.setInterval(function () {
                seconds -= 1;
                if (label) {
                    label.textContent = String(Math.max(seconds, 0));
                }
                if (seconds <= 0) {
                    window.clearInterval(timer);
                    window.location.href = home;
                }
            }, 1000);
        })();
    </script>
@endsection
