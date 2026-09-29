<section class="notifications-page" aria-labelledby="notifications-title">
    <header class="notifications-page__header">
        <div>
            <p class="notifications-page__eyebrow">Trung tâm cập nhật</p>
            <h1 id="notifications-title">Tất cả thông báo</h1>
            <p>Theo dõi các cập nhật quan trọng trong quá trình học và dạy của bạn.</p>
        </div>

        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <button class="notifications-page__read-all" type="submit">
                <x-directory-icon name="circle-check" />
                Đánh dấu tất cả đã đọc
            </button>
        </form>
    </header>

    <nav class="notifications-page__tabs" aria-label="Lọc danh sách thông báo">
        <a @class(['is-active' => $filter === 'all']) href="{{ route('notifications.index') }}">Tất cả</a>
        <a @class(['is-active' => $filter === 'unread']) href="{{ route('notifications.index', ['filter' => 'unread']) }}">Chưa đọc</a>
    </nav>

    <div class="notifications-page__list">
        @forelse ($notifications as $item)
            <x-notification-item :item="$item" />
        @empty
            <div class="notifications-page__empty">
                <x-directory-icon name="bell" />
                <h2>{{ $filter === 'unread' ? 'Bạn đã đọc tất cả thông báo.' : 'Bạn chưa có thông báo nào.' }}</h2>
            </div>
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="notifications-page__pagination">
            {{ $notifications->links() }}
        </div>
    @endif
</section>
