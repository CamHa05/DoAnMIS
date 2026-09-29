<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\IdentityVerification;
use App\Models\Notification;
use App\Models\TutorApplication;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\User;

class SystemNotificationService
{
    public const PUBLIC_APPLICATION_CREATED = 'PUBLIC_APPLICATION_CREATED';
    public const PUBLIC_TUTOR_SELECTED = 'PUBLIC_TUTOR_SELECTED';
    public const DIRECT_REQUEST_CREATED = 'DIRECT_REQUEST_CREATED';
    public const DIRECT_REQUEST_ACCEPTED = 'DIRECT_REQUEST_ACCEPTED';
    public const DIRECT_REQUEST_REJECTED = 'DIRECT_REQUEST_REJECTED';
    public const CONTRACT_LEARNER_CONFIRMED = 'CONTRACT_LEARNER_CONFIRMED';
    public const CLASS_CREATED = 'CLASS_CREATED';
    public const PROFILE_SUBMITTED = 'PROFILE_SUBMITTED';
    public const PROFILE_APPROVED = 'PROFILE_APPROVED';
    public const PROFILE_REJECTED = 'PROFILE_REJECTED';
    public const PROFILE_CHANGE_SUBMITTED = 'PROFILE_CHANGE_SUBMITTED';
    public const IDENTITY_SUBMITTED = 'IDENTITY_SUBMITTED';
    public const IDENTITY_VERIFIED = 'IDENTITY_VERIFIED';
    public const IDENTITY_REJECTED = 'IDENTITY_REJECTED';

    public function publicApplicationCreated(
        TutorApplication $application,
        TutoringRequest $request,
        TutorProfile $tutor
    ): Notification {
        $tutor->loadMissing('user');

        return $this->create(
            (int) $request->user_id,
            self::PUBLIC_APPLICATION_CREATED,
            'Có gia sư mới ứng tuyển',
            sprintf(
                '%s đã ứng tuyển yêu cầu %s của bạn.',
                $tutor->user?->full_name ?: 'Một gia sư',
                $this->requestCode($request)
            ),
            'TUTOR_APPLICATION',
            (int) $application->getKey()
        );
    }

    public function publicTutorSelected(
        Contract $contract,
        TutoringRequest $request,
        TutorProfile $tutor
    ): Notification {
        return $this->create(
            (int) $tutor->user_id,
            self::PUBLIC_TUTOR_SELECTED,
            'Bạn đã được chọn cho yêu cầu học',
            sprintf('Người học đã chọn bạn cho yêu cầu %s.', $this->requestCode($request)),
            'CONTRACT',
            (int) $contract->getKey()
        );
    }

    public function directRequestCreated(
        TutoringRequest $request,
        TutorProfile $tutor
    ): Notification {
        return $this->create(
            (int) $tutor->user_id,
            self::DIRECT_REQUEST_CREATED,
            'Bạn có yêu cầu nhận lớp mới',
            sprintf('Một người học đã gửi yêu cầu học trực tiếp %s cho bạn.', $this->requestCode($request)),
            'TUTORING_REQUEST',
            (int) $request->getKey()
        );
    }

    public function directRequestAccepted(
        Contract $contract,
        TutoringRequest $request
    ): Notification {
        return $this->create(
            (int) $request->user_id,
            self::DIRECT_REQUEST_ACCEPTED,
            'Gia sư đã nhận yêu cầu học',
            sprintf('Gia sư đã chấp nhận yêu cầu trực tiếp %s của bạn.', $this->requestCode($request)),
            'CONTRACT',
            (int) $contract->getKey()
        );
    }

    public function directRequestRejected(TutoringRequest $request): Notification
    {
        return $this->create(
            (int) $request->user_id,
            self::DIRECT_REQUEST_REJECTED,
            'Gia sư đã từ chối yêu cầu học',
            sprintf('Yêu cầu trực tiếp %s của bạn đã được gia sư từ chối.', $this->requestCode($request)),
            'TUTORING_REQUEST',
            (int) $request->getKey()
        );
    }

    public function learnerConfirmedContract(
        Contract $contract,
        TutorProfile $tutor
    ): Notification {
        return $this->create(
            (int) $tutor->user_id,
            self::CONTRACT_LEARNER_CONFIRMED,
            'Hợp đồng đang chờ bạn xác nhận',
            sprintf('Người học đã xác nhận hợp đồng %s.', $this->contractCode($contract)),
            'CONTRACT',
            (int) $contract->getKey()
        );
    }

    public function classCreated(
        TutoringClass $class,
        Contract $contract,
        int $learnerId,
        int $tutorId
    ): void {
        $message = sprintf(
            'Hợp đồng %s đã được hai bên xác nhận và lớp học đã được tạo.',
            $this->contractCode($contract)
        );

        foreach (array_unique([$learnerId, $tutorId]) as $recipientId) {
            $this->create(
                (int) $recipientId,
                self::CLASS_CREATED,
                'Lớp học đã được hình thành',
                $message,
                'TUTORING_CLASS',
                (int) $class->getKey()
            );
        }
    }

    public function tutorProfileSubmitted(TutorProfile $profile): void
    {
        $profile->loadMissing('user');

        $this->createForActiveAdmins(
            self::PROFILE_SUBMITTED,
            'Có hồ sơ gia sư mới chờ xét duyệt',
            sprintf('%s đã gửi hồ sơ đăng ký gia sư.', $profile->user?->full_name ?: 'Một người dùng'),
            'TUTOR_PROFILE',
            (int) $profile->getKey()
        );
    }

    public function tutorProfileApproved(TutorProfile $profile): Notification
    {
        return $this->create(
            (int) $profile->user_id,
            self::PROFILE_APPROVED,
            'Hồ sơ gia sư đã được duyệt',
            'Hồ sơ gia sư của bạn đã được quản trị viên phê duyệt.',
            'TUTOR_PROFILE',
            (int) $profile->getKey()
        );
    }

    public function tutorProfileRejected(TutorProfile $profile): Notification
    {
        return $this->create(
            (int) $profile->user_id,
            self::PROFILE_REJECTED,
            'Hồ sơ gia sư chưa được duyệt',
            'Hồ sơ gia sư của bạn chưa được chấp thuận. Vui lòng xem chi tiết.',
            'TUTOR_PROFILE',
            (int) $profile->getKey()
        );
    }

    public function tutorProfileChangeSubmitted(
        TutorProfile $profile,
        TutorProfileChangeRequest $change
    ): void {
        $profile->loadMissing('user');

        $this->createForActiveAdmins(
            self::PROFILE_CHANGE_SUBMITTED,
            'Có minh chứng gia sư chờ xét duyệt',
            sprintf(
                '%s đã gửi yêu cầu %s minh chứng.',
                $profile->user?->full_name ?: 'Một gia sư',
                mb_strtolower($change->actionLabel())
            ),
            'TUTOR_PROFILE',
            (int) $profile->getKey()
        );
    }

    public function identitySubmitted(IdentityVerification $verification): void
    {
        $verification->loadMissing('user');

        $this->createForActiveAdmins(
            self::IDENTITY_SUBMITTED,
            'Có yêu cầu xác minh danh tính mới',
            sprintf('%s đã gửi thông tin xác minh danh tính.', $verification->user?->full_name ?: 'Một người dùng'),
            'IDENTITY_VERIFICATION',
            (int) $verification->getKey()
        );
    }

    public function identityVerified(IdentityVerification $verification): Notification
    {
        return $this->create(
            (int) $verification->user_id,
            self::IDENTITY_VERIFIED,
            'Xác minh danh tính thành công',
            'Thông tin xác minh danh tính của bạn đã được chấp thuận.',
            'IDENTITY_VERIFICATION',
            (int) $verification->getKey()
        );
    }

    public function identityRejected(IdentityVerification $verification): Notification
    {
        return $this->create(
            (int) $verification->user_id,
            self::IDENTITY_REJECTED,
            'Xác minh danh tính chưa được chấp nhận',
            'Thông tin xác minh của bạn chưa được chấp thuận. Vui lòng kiểm tra lại.',
            'IDENTITY_VERIFICATION',
            (int) $verification->getKey()
        );
    }

    private function createForActiveAdmins(
        string $type,
        string $title,
        string $message,
        string $relatedType,
        int $relatedId
    ): void {
        User::query()
            ->where('is_admin', true)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->each(fn ($adminId) => $this->create(
                (int) $adminId,
                $type,
                $title,
                $message,
                $relatedType,
                $relatedId
            ));
    }

    private function create(
        int $recipientId,
        string $type,
        string $title,
        string $message,
        string $relatedType,
        int $relatedId
    ): Notification {
        return Notification::query()->create([
            'user_id' => $recipientId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    private function requestCode(TutoringRequest $request): string
    {
        return '#YC'.str_pad((string) $request->getKey(), 5, '0', STR_PAD_LEFT);
    }

    private function contractCode(Contract $contract): string
    {
        return '#HĐ-'.str_pad((string) $contract->getKey(), 3, '0', STR_PAD_LEFT);
    }
}
