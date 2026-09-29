<x-app-layout title="Đăng nhập | GiaSu" body-class="login-page">
    <section class="login-stage" aria-labelledby="login-title">
        <div class="login-stage__orb login-stage__orb--top" aria-hidden="true"></div>
        <div class="login-stage__orb login-stage__orb--bottom" aria-hidden="true"></div>
        <div class="login-stage__dots" aria-hidden="true"></div>
        <p class="login-stage__message" aria-hidden="true">
            <span>Tri thức</span>
            <span>mở lối</span>
            <span>tương lai</span>
        </p>

        <div class="login-card">
            <div class="login-card__brand" aria-label="GiaSu">
                <img
                    src="{{ asset('images/logo_giasu.webp') }}"
                    alt="GiaSu"
                    width="768"
                    height="512"
                >
            </div>

            <header class="login-card__header">
                <h1 id="login-title">Đăng nhập</h1>
                <p>Tiếp tục với tài khoản Google để sử dụng GiaSu.</p>
            </header>

            @if (session('error'))
                <div class="login-alert" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <a
                class="login-google"
                href="{{ route('auth.google.redirect') }}"
                aria-label="Tiếp tục đăng nhập bằng Google"
            >
                <svg class="login-google__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z"/>
                    <path fill="#34A853" d="M12 22c2.7 0 4.98-.9 6.64-2.36l-3.24-2.54c-.9.6-2.05.96-3.4.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"/>
                    <path fill="#FBBC05" d="M6.39 13.93A6.02 6.02 0 0 1 6.07 12c0-.67.11-1.32.32-1.93V7.45H3.04A10 10 0 0 0 2 12c0 1.61.39 3.13 1.04 4.55l3.35-2.62Z"/>
                    <path fill="#EA4335" d="M12 5.94c1.47 0 2.79.51 3.83 1.5l2.88-2.88A9.66 9.66 0 0 0 12 2a10 10 0 0 0-8.96 5.45l3.35 2.62C7.18 7.7 9.39 5.94 12 5.94Z"/>
                </svg>
                <span class="login-google__label">Tiếp tục với Google</span>
            </a>

            <div class="login-card__security">
                <x-directory-icon name="lock" />
                <p>GiaSu sử dụng tài khoản Google để xác thực và bảo vệ tài khoản của bạn.</p>
            </div>

            <div class="login-card__footer">
                <a class="login-home-link" href="{{ route('home') }}">
                    <x-directory-icon name="arrow-left" />
                    <span>Quay lại trang chủ</span>
                </a>
            </div>
        </div>
    </section>
</x-app-layout>
