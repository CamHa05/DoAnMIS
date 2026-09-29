@php
    $items = [
        [
            'label' => 'Hồ sơ gia sư',
            'route' => 'tutor-area.profile',
            'pattern' => 'tutor-area.profile',
            'icon' => 'user',
        ],
        [
            'label' => 'Yêu cầu học của tôi',
            'route' => 'my-requests.index',
            'pattern' => 'my-requests.*',
            'icon' => 'request',
        ],
        [
            'label' => 'Yêu cầu nhận lớp',
            'route' => 'tutor-area.direct-requests',
            'pattern' => 'tutor-area.direct-requests*',
            'icon' => 'send',
        ],
        [
            'label' => 'Ứng tuyển của tôi',
            'route' => 'tutor-area.applications',
            'pattern' => 'tutor-area.applications*',
            'icon' => 'file-check',
        ],
        [
            'label' => 'Lớp học',
            'route' => 'classes.index',
            'pattern' => 'classes.*',
            'icon' => 'calendar',
        ],
    ];

@endphp

<nav class="tutor-account-sidebar" aria-label="Tài khoản gia sư">
    <p class="tutor-account-sidebar-label">Tài khoản gia sư</p>

    <div class="tutor-account-sidebar-links">
        @foreach ($items as $item)
            @php($isActive = request()->routeIs($item['pattern']))
            <a
                @class(['tutor-account-sidebar-link', 'is-active' => $isActive])
                href="{{ route($item['route']) }}"
                @if ($isActive) aria-current="page" @endif
            >
                <x-directory-icon name="{{ $item['icon'] }}" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
