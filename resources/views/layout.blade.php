<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Zalo · @yield('title', 'Bảng điều khiển')</title>

    {{--
        CSS nhúng thẳng thay vì publish ra public/vendor.
        Đổi lại việc mất cache trình duyệt (trang quản trị, dùng thi thoảng),
        ta được: không cần bước vendor:publish, và CSS không bao giờ lệch
        phiên bản với package sau khi composer update.
    --}}
    <style>{!! $zaloCss !!}</style>
</head>
<body>
<header class="zl-header">
    <div class="zl-wrap zl-header-inner">
        <a href="{{ route('zalo.dashboard') }}" class="zl-brand">Zalo</a>
        <nav class="zl-nav">
            <a href="{{ route('zalo.dashboard') }}" @class(['zl-active' => request()->routeIs('zalo.dashboard')])>Tổng quan</a>
            <a href="{{ route('zalo.oas.index') }}" @class(['zl-active' => request()->routeIs('zalo.oas.*')])>Official Account</a>
            <a href="{{ route('zalo.bots.index') }}" @class(['zl-active' => request()->routeIs('zalo.bots.*')])>Bot</a>
        </nav>
    </div>
</header>

<main class="zl-wrap zl-main">
    @if (session('zalo.success'))
        <div class="zl-alert zl-alert-ok">{{ session('zalo.success') }}</div>
    @endif

    @if (session('zalo.error'))
        <div class="zl-alert zl-alert-err">{{ session('zalo.error') }}</div>
    @endif

    @if ($errors->any())
        <div class="zl-alert zl-alert-err">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>

<footer class="zl-wrap zl-footer">
    field-vn/zalo · Trang quản trị này chỉ dành cho quản trị viên kỹ thuật.
</footer>
</body>
</html>
