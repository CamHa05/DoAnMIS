@props(['item', 'compact' => false])

@php
    $notification = $item['notification'];
    $actor = $item['actor'];
@endphp

<form
    method="POST"
    action="{{ route('notifications.open', $notification) }}"
    @class([
        'notification-item',
        'notification-item--compact' => $compact,
        'is-unread' => ! $notification->is_read,
    ])
    data-notification-item
    data-notification-unread="{{ $notification->is_read ? 'false' : 'true' }}"
>
    @csrf

    <button class="notification-item__button" type="submit">
        <span class="notification-item__visual" aria-hidden="true">
            @if ($actor)
                <x-tutor-avatar :user="$actor" />
            @else
                <x-directory-icon :name="$item['icon']" />
            @endif
        </span>

        <span class="notification-item__copy">
            <strong>{{ $notification->title }}</strong>
            <span>{{ $notification->message }}</span>
            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $item['time'] }}</time>
        </span>

        @unless ($notification->is_read)
            <span class="notification-item__indicator">
                <span class="notification-visually-hidden">Chưa đọc</span>
            </span>
        @endunless
    </button>
</form>
