<?php

namespace App\View\Components;

use App\Models\User;
use App\Services\NotificationPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

class NotificationCenter extends Component
{
    public readonly int $unreadCount;

    /** @var Collection<int, array<string, mixed>> */
    public readonly Collection $items;

    public function __construct(public string $variant = 'site')
    {
        $user = Auth::user();

        if (! $user instanceof User || ! Schema::hasTable('notifications')) {
            $this->unreadCount = 0;
            $this->items = collect();

            return;
        }

        $this->unreadCount = $user->systemNotifications()
            ->where('is_read', false)
            ->count();
        $notifications = $user->systemNotifications()
            ->latest('created_at')
            ->latest('notification_id')
            ->limit(6)
            ->get();
        $this->items = app(NotificationPresenter::class)->present($notifications);
    }

    public function render(): View
    {
        return view('components.notification-center');
    }
}
