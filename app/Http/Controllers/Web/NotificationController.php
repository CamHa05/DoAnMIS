<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\IdentityVerification;
use App\Models\Notification;
use App\Models\TutorApplication;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Services\NotificationPresenter;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationPresenter $presenter): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = $request->user()->systemNotifications()->latest('created_at')->latest('notification_id');

        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(15)->withQueryString();
        $items = $presenter->present($notifications->getCollection());
        $notifications->setCollection($items);

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'isAdmin' => (bool) $request->user()->is_admin,
        ]);
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $notification = $this->ownedNotification($request, $notification);
        $this->markRead($notification);

        return redirect()->to($this->destination($notification));
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $notification = $this->ownedNotification($request, $notification);
        $this->markRead($notification);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->systemNotifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }

    private function ownedNotification(Request $request, int $notification): Notification
    {
        return $request->user()->systemNotifications()
            ->whereKey($notification)
            ->firstOrFail();
    }

    private function markRead(Notification $notification): void
    {
        if ($notification->is_read) {
            return;
        }

        $notification->forceFill([
            'is_read' => true,
            'read_at' => now(),
        ])->save();
    }

    private function destination(Notification $notification): string
    {
        $id = (int) $notification->related_id;

        return match ((string) $notification->notification_type) {
            SystemNotificationService::PUBLIC_APPLICATION_CREATED => $this->applicationDestination($id),
            SystemNotificationService::PUBLIC_TUTOR_SELECTED,
            SystemNotificationService::DIRECT_REQUEST_ACCEPTED,
            SystemNotificationService::CONTRACT_LEARNER_CONFIRMED => $this->contractDestination($id),
            SystemNotificationService::DIRECT_REQUEST_CREATED => $this->directRequestDestination($id),
            SystemNotificationService::DIRECT_REQUEST_REJECTED => $this->learnerRequestDestination($id),
            SystemNotificationService::CLASS_CREATED => $this->classDestination($id),
            SystemNotificationService::PROFILE_SUBMITTED => route('admin.tutors.show', $id),
            SystemNotificationService::PROFILE_CHANGE_SUBMITTED => route('admin.tutors.show', $id),
            SystemNotificationService::PROFILE_APPROVED,
            SystemNotificationService::PROFILE_REJECTED,
            'PROFILE_CHANGE_APPROVED',
            'PROFILE_CHANGE_REJECTED' => route('tutor-area.profile'),
            SystemNotificationService::IDENTITY_SUBMITTED => route('admin.identity-verifications.show', $id),
            SystemNotificationService::IDENTITY_VERIFIED,
            SystemNotificationService::IDENTITY_REJECTED => route('identity-verification.show'),
            default => route('notifications.index'),
        };
    }

    private function applicationDestination(int $id): string
    {
        $application = TutorApplication::query()->find($id);

        return $application
            ? route('my-requests.applications.index', $application->request_id)
            : route('notifications.index');
    }

    private function contractDestination(int $id): string
    {
        return Contract::query()->whereKey($id)->exists()
            ? route('contracts.show', $id)
            : route('notifications.index');
    }

    private function directRequestDestination(int $id): string
    {
        return TutoringRequest::query()->whereKey($id)->exists()
            ? route('tutor-area.direct-requests.show', $id)
            : route('notifications.index');
    }

    private function learnerRequestDestination(int $id): string
    {
        return TutoringRequest::query()->whereKey($id)->exists()
            ? route('my-requests.show', $id)
            : route('notifications.index');
    }

    private function classDestination(int $id): string
    {
        return TutoringClass::query()->whereKey($id)->exists()
            ? route('classes.show', $id)
            : route('notifications.index');
    }
}
