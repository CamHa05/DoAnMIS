<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GiaSu — tìm gia sư phù hợp cho mục tiêu học tập của bạn.">
    <title>{{ $title ?? 'GiaSu' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body @if (filled($bodyClass)) class="{{ $bodyClass }}" @endif>
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <x-site-header />

    <main
        id="main-content"
        class="site-page-canvas"
        style="--site-page-background: url('{{ asset('images/nen.webp') }}')"
    >
        {{ $slot }}
    </main>

    <x-site-footer />
</body>

</html>
