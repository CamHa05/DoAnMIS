@props([
    'title' => 'Quản trị',
    'activeSection' => null,
    'activeSubsection' => null,
])

<!DOCTYPE html>
<html class="admin-document" lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Khu vực quản trị GiaSu.">
    <title>{{ $title }} · GiaSu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    <a class="admin-skip-link" href="#admin-main">Bỏ qua đến nội dung chính</a>

    <div class="admin-frame">
        <x-admin-sidebar :active-section="$activeSection" :active-subsection="$activeSubsection" />

        <div class="admin-column">
            <x-admin-header :title="$title" :admin="auth()->user()" />

            <main class="admin-content" id="admin-main">
                {{ $slot }}
            </main>

            <x-admin-footer />
        </div>
    </div>
</body>
</html>
