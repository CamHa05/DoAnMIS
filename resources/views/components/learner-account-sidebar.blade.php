<nav class="tutor-account-sidebar learner-account-sidebar" aria-label="Tài khoản của tôi">
    <p class="tutor-account-sidebar-label">Tài khoản của tôi</p>

    <div class="tutor-account-sidebar-links">
        @foreach ([
            ['label' => 'Yêu cầu học của tôi', 'route' => 'my-requests.index', 'pattern' => 'my-requests.*', 'icon' => 'request'],
            ['label' => 'Lớp học của tôi', 'route' => 'classes.index', 'pattern' => 'classes.*', 'icon' => 'calendar'],
        ] as $item)
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