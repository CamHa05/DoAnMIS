@props([
    'title' => 'Quản trị',
    'admin' => null,
])

<header class="admin-header">
    <div class="admin-header__title">
        <strong>{{ $title }}</strong>
    </div>

    <div class="admin-header__actions">
        <x-notification-center variant="admin" />

        <span class="admin-header__divider" aria-hidden="true"></span>

        <div class="admin-header__identity">
            <x-tutor-avatar :user="$admin" />
            <span class="admin-header__identity-copy">
                <strong>{{ $admin?->full_name }}</strong>
                <small>Admin</small>
            </span>
        </div>
    </div>
</header>
