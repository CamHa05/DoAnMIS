<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\IdentityVerification;
use App\Models\Notification;
use App\Models\TutorApplication;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationPresenter
{
    /**
     * @param  Collection<int, Notification>  $notifications
     * @return Collection<int, array{notification: Notification, actor: ?User, icon: string, time: string}>
     */
    public function present(Collection $notifications): Collection
    {
        $applications = $this->relatedModels($notifications, 'TUTOR_APPLICATION', TutorApplication::class, ['tutorProfile.user']);
        $requests = $this->relatedModels($notifications, 'TUTORING_REQUEST', TutoringRequest::class, ['user', 'targetTutor.user']);
        $contracts = $this->relatedModels($notifications, 'CONTRACT', Contract::class, ['tutoringRequest.user', 'tutorProfile.user']);
        $profiles = $this->relatedModels($notifications, 'TUTOR_PROFILE', TutorProfile::class, ['user']);
        $verifications = $this->relatedModels($notifications, 'IDENTITY_VERIFICATION', IdentityVerification::class, ['user']);

        return $notifications->map(function (Notification $notification) use (
            $applications,
            $requests,
            $contracts,
            $profiles,
            $verifications
        ): array {
            $type = (string) $notification->notification_type;
            $relatedId = (int) $notification->related_id;
            $actor = match ($type) {
                SystemNotificationService::PUBLIC_APPLICATION_CREATED => $applications->get($relatedId)?->tutorProfile?->user,
                SystemNotificationService::DIRECT_REQUEST_CREATED => $requests->get($relatedId)?->user,
                SystemNotificationService::DIRECT_REQUEST_REJECTED => $requests->get($relatedId)?->targetTutor?->user,
                SystemNotificationService::DIRECT_REQUEST_ACCEPTED => $contracts->get($relatedId)?->tutorProfile?->user,
                SystemNotificationService::CONTRACT_LEARNER_CONFIRMED => $contracts->get($relatedId)?->tutoringRequest?->user,
                SystemNotificationService::PROFILE_SUBMITTED => $profiles->get($relatedId)?->user,
                SystemNotificationService::PROFILE_CHANGE_SUBMITTED => $profiles->get($relatedId)?->user,
                SystemNotificationService::IDENTITY_SUBMITTED => $verifications->get($relatedId)?->user,
                default => null,
            };

            return [
                'notification' => $notification,
                'actor' => $actor,
                'icon' => $this->icon($type),
                'time' => $this->timeLabel($notification),
            ];
        });
    }

    /**
     * @param  Collection<int, Notification>  $notifications
     * @param  array<int, string>  $with
     * @return Collection<int, object>
     */
    private function relatedModels(
        Collection $notifications,
        string $relatedType,
        string $model,
        array $with
    ): Collection {
        $ids = $notifications
            ->where('related_type', $relatedType)
            ->pluck('related_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $model::query()
            ->whereKey($ids->all())
            ->with($with)
            ->get()
            ->keyBy(fn ($item) => (int) $item->getKey());
    }

    private function icon(string $type): string
    {
        return match ($type) {
            SystemNotificationService::PUBLIC_APPLICATION_CREATED,
            SystemNotificationService::PUBLIC_TUTOR_SELECTED => 'users',
            SystemNotificationService::DIRECT_REQUEST_CREATED,
            SystemNotificationService::DIRECT_REQUEST_ACCEPTED,
            SystemNotificationService::DIRECT_REQUEST_REJECTED => 'request',
            SystemNotificationService::CONTRACT_LEARNER_CONFIRMED => 'file-check',
            SystemNotificationService::CLASS_CREATED => 'calendar',
            SystemNotificationService::PROFILE_SUBMITTED,
            SystemNotificationService::PROFILE_APPROVED,
            SystemNotificationService::PROFILE_REJECTED => 'education',
            SystemNotificationService::PROFILE_CHANGE_SUBMITTED => 'file-check',
            SystemNotificationService::IDENTITY_SUBMITTED,
            SystemNotificationService::IDENTITY_VERIFIED,
            SystemNotificationService::IDENTITY_REJECTED => 'shield',
            default => 'bell',
        };
    }

    private function timeLabel(Notification $notification): string
    {
        $createdAt = $notification->created_at;

        if ($createdAt === null) {
            return '';
        }

        if ($createdAt->isToday()) {
            if ($createdAt->diffInSeconds(now()) < 60) {
                return 'Vừa xong';
            }

            return $createdAt->locale('vi')->diffForHumans();
        }

        if ($createdAt->isYesterday()) {
            return 'Hôm qua, '.$createdAt->format('H:i');
        }

        return $createdAt->locale('vi')->translatedFormat('d/m/Y, H:i');
    }
}
