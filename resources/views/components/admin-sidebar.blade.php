@props([
    'activeSection' => null,
])

@php
    $overviewIsActive = $activeSection === 'overview'
        || ($activeSection === null && request()->routeIs('admin.dashboard'));

    $tutorsAreActive = $activeSection === 'tutors'
        || request()->routeIs('admin.tutors.*');

    $usersAreActive = $activeSection === 'users'
        || request()->routeIs('admin.users.*');

    $identityVerificationsAreActive = $activeSection === 'identity-verifications'
        || request()->routeIs('admin.identity-verifications.*');

    $requestsAreActive = $activeSection === 'requests'
        || request()->routeIs('admin.requests.*');

    $classesAreActive = $activeSection === 'classes'
        || request()->routeIs('admin.classes.*');
@endphp

<aside class="admin-sidebar" aria-label="Điều hướng quản trị">
    <a
        class="admin-sidebar__brand"
        href="{{ route('admin.dashboard') }}"
        aria-label="GiaSu — Tổng quan quản trị"
    >
        <img
            src="{{ asset('images/logo_giasu.webp') }}"
            alt="GiaSu — Kết nối tri thức, nâng tầm học tập"
            width="768"
            height="512"
            decoding="async"
            fetchpriority="high"
        >
    </a>

    <nav class="admin-navigation" aria-label="Các phân hệ quản trị">

        {{-- Tổng quan --}}
        <a
            @class([
                'admin-navigation__item',
                'is-active' => $overviewIsActive,
            ])
            href="{{ route('admin.dashboard') }}"
            @if ($overviewIsActive) aria-current="page" @endif
        >
            <x-directory-icon name="home" />
            <span>Tổng quan</span>
        </a>

        {{-- Gia sư --}}
        @if (Route::has('admin.tutors.index'))
            <a
                @class([
                    'admin-navigation__item',
                    'is-active' => $tutorsAreActive,
                ])
                href="{{ route('admin.tutors.index') }}"
                @if ($tutorsAreActive) aria-current="page" @endif
            >
                <x-directory-icon name="education" />
                <span>Gia sư</span>
            </a>
        @else
            <span
                @class([
                    'admin-navigation__item',
                    'is-active' => $tutorsAreActive,
                ])
                aria-disabled="true"
            >
                <x-directory-icon name="education" />
                <span>Gia sư</span>
            </span>
        @endif

        {{-- Người dùng --}}
        @if (Route::has('admin.users.index'))
            <a
                @class([
                    'admin-navigation__item',
                    'is-active' => $usersAreActive,
                ])
                href="{{ route('admin.users.index') }}"
                @if ($usersAreActive) aria-current="page" @endif
            >
                <x-directory-icon name="users" />
                <span>Người dùng</span>
            </a>
        @else
            <span
                @class([
                    'admin-navigation__item',
                    'is-active' => $usersAreActive,
                ])
                aria-disabled="true"
            >
                <x-directory-icon name="users" />
                <span>Người dùng</span>
            </span>
        @endif

        {{-- Xác minh danh tính --}}
        @if (Route::has('admin.identity-verifications.index'))
            <a
                @class([
                    'admin-navigation__item',
                    'is-active' => $identityVerificationsAreActive,
                ])
                href="{{ route('admin.identity-verifications.index') }}"
                @if ($identityVerificationsAreActive) aria-current="page" @endif
            >
                <x-directory-icon name="shield" />
                <span>Xác minh danh tính</span>
            </a>
        @else
            <span
                @class([
                    'admin-navigation__item',
                    'is-active' => $identityVerificationsAreActive,
                ])
                aria-disabled="true"
            >
                <x-directory-icon name="shield" />
                <span>Xác minh danh tính</span>
            </span>
        @endif

        {{-- Yêu cầu học --}}
        @if (Route::has('admin.requests.index'))
            <a
                @class([
                    'admin-navigation__item',
                    'is-active' => $requestsAreActive,
                ])
                href="{{ route('admin.requests.index') }}"
                @if ($requestsAreActive) aria-current="page" @endif
            >
                <x-directory-icon name="request" />
                <span>Yêu cầu học</span>
            </a>
        @else
            <span
                @class([
                    'admin-navigation__item',
                    'is-active' => $requestsAreActive,
                ])
                aria-disabled="true"
            >
                <x-directory-icon name="request" />
                <span>Yêu cầu học</span>
            </span>
        @endif

        {{-- Lớp học --}}
        @if (Route::has('admin.classes.index'))
            <a
                @class([
                    'admin-navigation__item',
                    'is-active' => $classesAreActive,
                ])
                href="{{ route('admin.classes.index') }}"
                @if ($classesAreActive) aria-current="page" @endif
            >
                <x-directory-icon name="book-open" />
                <span>Lớp học</span>
            </a>
        @else
            <span
                @class([
                    'admin-navigation__item',
                    'is-active' => $classesAreActive,
                ])
                aria-disabled="true"
            >
                <x-directory-icon name="book-open" />
                <span>Lớp học</span>
            </span>
        @endif

    </nav>

    <div class="admin-sidebar__footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                class="admin-sidebar__logout"
                type="submit"
            >
                <x-directory-icon name="logout" />
                <span>Đăng xuất</span>
            </button>
        </form>

        <p>
            Kết nối tri thức<br>
            Kiến tạo tương lai
        </p>
    </div>
</aside>
