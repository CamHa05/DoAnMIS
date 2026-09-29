@php
    $panelId = 'notification-panel-'.$variant;
    $isAdminVariant = $variant === 'admin';
    $notificationLabel = $unreadCount > 0
        ? "Thông báo, {$unreadCount} chưa đọc"
        : 'Thông báo';
@endphp

<div @class(['notification-center', 'notification-center--admin' => $isAdminVariant]) data-notification-center>
    <button
        @class([
            'notification-center__trigger',
            'notification-bell site-header-control' => ! $isAdminVariant,
            'admin-header__notification' => $isAdminVariant,
        ])
        type="button"
        aria-label="{{ $notificationLabel }}"
        aria-expanded="false"
        aria-controls="{{ $panelId }}"
        data-notification-trigger
        @unless ($isAdminVariant) data-notification-bell @endunless
    >
        <x-directory-icon name="bell" />

        @if ($unreadCount > 0)
            <span @class([
                'notification-center__badge',
                'notification-badge' => ! $isAdminVariant,
                'admin-header__notification-badge' => $isAdminVariant,
            ]) aria-hidden="true">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <section
        class="notification-popover"
        id="{{ $panelId }}"
        aria-label="Thông báo"
        data-notification-panel
        hidden
    >
        <div class="notification-popover__header">
            <h2>Thông báo</h2>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button class="notification-popover__read-all" type="submit">
                        <x-directory-icon name="circle-check" />
                        Đánh dấu tất cả đã đọc
                    </button>
                </form>
            @endif
        </div>

        <div class="notification-popover__tabs" role="tablist" aria-label="Lọc thông báo">
            <button
                class="is-active"
                type="button"
                role="tab"
                aria-selected="true"
                data-notification-tab="all"
            >Tất cả <span>({{ $items->count() }})</span></button>
            <button
                type="button"
                role="tab"
                aria-selected="false"
                data-notification-tab="unread"
            >Chưa đọc <span>({{ $unreadCount }})</span></button>
        </div>

        <div class="notification-popover__list" data-notification-list>
            @forelse ($items as $item)
                <x-notification-item :item="$item" compact />
            @empty
                <p class="notification-empty" data-notification-empty-all>Bạn chưa có thông báo nào.</p>
            @endforelse

            <p class="notification-empty" data-notification-empty-unread hidden>Bạn đã đọc tất cả thông báo.</p>
        </div>

        <a class="notification-popover__footer" href="{{ route('notifications.index') }}">
            Xem tất cả thông báo
            <x-directory-icon name="arrow" />
        </a>
    </section>
</div>
