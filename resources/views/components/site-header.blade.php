@php
    $isTutorDirectory = request()->routeIs('tutors.*');
    $isRequestDirectory = request()->routeIs('requests.index', 'requests.show');
    $isRequestCreation = request()->routeIs('requests.create');
    $isAccountArea = request()->routeIs(
        'profile.*',
        'my-requests.*',
        'classes.*',
        'notifications.*',
        'tutor-registration.*',
        'tutor-area.*'
    );
    $isAdmin = (bool) $currentUser?->is_admin;
    $hasTutorProfile = $currentUser?->tutorProfile()->exists() ?? false;
@endphp

<header class="site-header" data-site-header>
    <div class="container nav-wrap">
        <a class="brand site-header-brand" href="{{ route('home') }}" aria-label="GiaSu — trang chủ">
            <img
                class="site-brand-image"
                src="{{ asset('images/logo_giasu.webp') }}"
                alt="GiaSu — Kết nối tri thức, nâng tầm học tập"
                width="768"
                height="512"
                decoding="async"
                fetchpriority="high"
            >
        </a>

        <nav class="desktop-nav" aria-label="Điều hướng chính">
            <a
                @class(['site-nav-link', 'site-header-control', 'is-active' => $isTutorDirectory])
                href="{{ route('tutors.index') }}"
                @if ($isTutorDirectory) aria-current="page" @endif
            >Gia sư</a>
            <a
                @class(['site-nav-link', 'site-header-control', 'is-active' => $isRequestDirectory])
                href="{{ route('requests.index') }}"
                @if ($isRequestDirectory) aria-current="page" @endif
            >Lớp đang tìm gia sư</a>
            @unless ($isAdmin)
                <a class="site-nav-link site-header-control" href="{{ route('tutor-registration.basic.edit') }}">Đăng ký làm gia sư</a>
                <a
                    @class(['nav-create-request', 'site-header-control', 'is-active' => $isRequestCreation])
                    href="{{ route('requests.create') }}"
                    @if ($isRequestCreation) aria-current="page" @endif
                >Tạo yêu cầu học</a>
            @endunless
        </nav>

        <div class="nav-actions">
            @if ($currentUser)
                @unless ($isAdmin)
                    <x-notification-center />
                @endunless

                <div @class(['account-menu', 'is-current' => $isAccountArea]) data-account-menu>
                    <button
                        class="account-menu-trigger site-header-control"
                        type="button"
                        aria-expanded="false"
                        aria-haspopup="menu"
                        aria-controls="account-menu-panel"
                        data-account-menu-trigger
                    >
                        <x-tutor-avatar :user="$currentUser" />
                        <span class="account-menu-name">{{ $displayName }}</span>
                        <x-directory-icon class="account-menu-chevron" name="chevron" />
                    </button>

                    <div
                        class="account-menu-panel"
                        id="account-menu-panel"
                        role="menu"
                        data-account-menu-panel
                        hidden
                    >
                        @if ($isAdmin)
                            <a class="site-header-control" href="{{ route('admin.dashboard') }}" role="menuitem">
                                <x-directory-icon name="home" />
                                Trang quản trị
                            </a>
                        @else
                            <a class="site-header-control" href="{{ route('profile.show') }}" role="menuitem">
                                <x-directory-icon name="user" />
                                Hồ sơ của tôi
                            </a>

                            <a class="site-header-control" href="{{ route('my-requests.index') }}" role="menuitem">
                                <x-directory-icon name="request" />
                                Yêu cầu học của tôi
                            </a>

                            <a class="site-header-control" href="{{ route('classes.index') }}" role="menuitem">
                                <x-directory-icon name="calendar" />
                                Lớp học của tôi
                            </a>

                            @if ($hasTutorProfile)
                                <a class="site-header-control" href="{{ route('tutor-area.profile') }}" role="menuitem">
                                    <x-directory-icon name="education" />
                                    Khu vực gia sư
                                </a>
                            @endif
                        @endif

                        <form method="POST" action="{{ route('logout') }}" role="none">
                            @csrf

                            <button class="site-header-control" type="submit" role="menuitem">
                                <x-directory-icon name="logout" />
                                Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a class="login-link site-header-control" href="{{ route('login') }}">
                    <x-directory-icon name="user" />
                    Đăng nhập
                </a>
            @endif

            <button
                class="menu-button site-header-control"
                type="button"
                aria-label="Mở menu"
                aria-expanded="false"
                aria-controls="mobile-navigation"
                data-mobile-menu-toggle
            >
                <x-directory-icon name="menu" />
            </button>
        </div>
    </div>

    <nav
        class="mobile-navigation"
        id="mobile-navigation"
        aria-label="Điều hướng mobile"
        hidden
    >
        <div class="container mobile-navigation-links">
            <a
                @class(['site-header-control', 'is-active' => $isTutorDirectory])
                href="{{ route('tutors.index') }}"
                @if ($isTutorDirectory) aria-current="page" @endif
            >Gia sư</a>
            <a
                @class(['site-header-control', 'is-active' => $isRequestDirectory])
                href="{{ route('requests.index') }}"
                @if ($isRequestDirectory) aria-current="page" @endif
            >Lớp đang tìm gia sư</a>
            @unless ($isAdmin)
                <a class="site-header-control" href="{{ route('tutor-registration.basic.edit') }}">Đăng ký làm gia sư</a>
                <a
                    @class(['site-header-control', 'is-active' => $isRequestCreation])
                    href="{{ route('requests.create') }}"
                    @if ($isRequestCreation) aria-current="page" @endif
                >Tạo yêu cầu học</a>
            @endunless

            @if (! $currentUser)
                <a class="mobile-login-link site-header-control" href="{{ route('login') }}">
                    <x-directory-icon name="user" />
                    Đăng nhập
                </a>
            @else
                <div class="mobile-account-summary">
                    <x-tutor-avatar :user="$currentUser" />
                    <span>
                        <strong>{{ $displayName }}</strong>
                        <small>{{ $currentUser->email }}</small>
                    </span>
                </div>

                @if ($isAdmin)
                    <a class="mobile-account-action site-header-control" href="{{ route('admin.dashboard') }}">
                        <x-directory-icon name="home" />
                        Trang quản trị
                    </a>
                @else
                    <a class="mobile-account-action site-header-control" href="{{ route('profile.show') }}">
                        <x-directory-icon name="user" />
                        Hồ sơ của tôi
                    </a>

                    <a class="mobile-account-action site-header-control" href="{{ route('my-requests.index') }}">
                        <x-directory-icon name="request" />
                        Yêu cầu học của tôi
                    </a>

                    <a class="mobile-account-action site-header-control" href="{{ route('classes.index') }}">
                        <x-directory-icon name="calendar" />
                        Lớp học của tôi
                    </a>

                    @if ($hasTutorProfile)
                        <a class="mobile-account-action site-header-control" href="{{ route('tutor-area.profile') }}">
                            <x-directory-icon name="education" />
                            Khu vực gia sư
                        </a>
                    @endif
                @endif

                <form class="mobile-account-form" method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="mobile-account-action site-header-control" type="submit">
                        <x-directory-icon name="logout" />
                        Đăng xuất
                    </button>
                </form>
            @endif
        </div>
    </nav>
</header>
