@props([
    'user' => null,
    'size' => 'default',
])

@php
    $name = trim((string) ($user?->full_name ?: 'Gia sư'));
    $nameParts = collect(preg_split('/\s+/', $name))->filter();
    $initial = mb_substr((string) $nameParts->last(), 0, 1) ?: 'G';
    $sizeClass = $size === 'large' ? ' tutor-avatar-large' : '';
    $dimension = $size === 'large' ? 116 : 82;
    $avatarUrl = trim((string) $user?->avatar_url);
    $avatarScheme = strtolower((string) parse_url($avatarUrl, PHP_URL_SCHEME));
    $isExternalAvatar = in_array($avatarScheme, ['http', 'https'], true)
        || str_starts_with($avatarUrl, '//');
    $shouldRenderImage = $avatarUrl !== '' && $isExternalAvatar;

    if ($avatarUrl !== '' && ! $isExternalAvatar) {
        $avatarPath = parse_url($avatarUrl, PHP_URL_PATH);
        $publicRoot = realpath(public_path());
        $avatarFile = is_string($avatarPath)
            ? realpath(public_path(ltrim(rawurldecode($avatarPath), '/\\')))
            : false;
        $normalizedRoot = $publicRoot
            ? rtrim(str_replace('\\', '/', $publicRoot), '/') . '/'
            : null;
        $normalizedFile = $avatarFile
            ? str_replace('\\', '/', $avatarFile)
            : null;

        $shouldRenderImage = $normalizedRoot
            && $normalizedFile
            && str_starts_with($normalizedFile, $normalizedRoot)
            && is_file($avatarFile);
    }
@endphp

@if ($shouldRenderImage)
    <img
        class="avatar tutor-avatar-image{{ $sizeClass }}"
        src="{{ $avatarUrl }}"
        alt="Ảnh đại diện {{ $name }}"
        width="{{ $dimension }}"
        height="{{ $dimension }}"
        loading="lazy"
        decoding="async"
        onerror="this.hidden = true; this.nextElementSibling.hidden = false;"
    >
    <div
        class="avatar avatar-database{{ $sizeClass }}"
        aria-label="Avatar chữ cái {{ $initial }} của {{ $name }}"
        hidden
    >
        {{ $initial }}
    </div>
@else
    <div
        class="avatar avatar-database{{ $sizeClass }}"
        aria-label="Avatar chữ cái {{ $initial }} của {{ $name }}"
    >
        {{ $initial }}
    </div>
@endif
