<?php

namespace App\Services;

use App\Models\IdentityVerification;
use App\Models\User;

class IdentityVerificationRequirement
{
    public const ACTION_CREATE_REQUEST = 'create_request';

    public const ACTION_TUTOR_APPLICATION = 'tutor_application';

    public function verificationFor(User $user, bool $lockForUpdate = false): ?IdentityVerification
    {
        return $this->verificationForUserId((int) $user->getAuthIdentifier(), $lockForUpdate);
    }

    public function verificationForUserId(
        int $userId,
        bool $lockForUpdate = false
    ): ?IdentityVerification {
        $query = IdentityVerification::query()
            ->where('user_id', $userId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first([
            'verification_id',
            'user_id',
            'status',
        ]);
    }

    public function isVerified(?IdentityVerification $verification): bool
    {
        return strtoupper((string) $verification?->status)
            === IdentityVerification::STATUS_VERIFIED;
    }

    public function blockingMessage(
        ?IdentityVerification $verification,
        string $action
    ): string {
        $status = strtoupper((string) $verification?->status);

        if ($status === IdentityVerification::STATUS_PENDING) {
            return 'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.';
        }

        if ($status === IdentityVerification::STATUS_REJECTED) {
            return $action === self::ACTION_CREATE_REQUEST
                ? 'Hồ sơ xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật và gửi lại.'
                : 'Xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật hồ sơ.';
        }

        return $action === self::ACTION_CREATE_REQUEST
            ? 'Bạn cần xác minh danh tính trước khi tạo yêu cầu học.'
            : 'Bạn cần xác minh danh tính trước khi ứng tuyển.';
    }
}
