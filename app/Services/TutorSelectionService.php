<?php

namespace App\Services;

use App\Exceptions\TutorSelectionException;
use App\Models\Contract;
use App\Models\RequestStatusHistory;
use App\Models\TutorApplication;
use App\Models\TutorProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TutorSelectionService
{
    private const MATCH_REASON = 'Người học chọn gia sư từ danh sách ứng tuyển.';

    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {}

    public function select(int $requestId, int $applicationId, int $ownerId): Contract
    {
        return DB::transaction(function () use ($requestId, $applicationId, $ownerId): Contract {
            $tutoringRequest = TutoringRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $tutoringRequest->user_id !== $ownerId) {
                throw new AuthorizationException('Bạn không có quyền chọn gia sư cho yêu cầu này.');
            }

            $application = TutorApplication::query()
                ->whereKey($applicationId)
                ->where('request_id', $tutoringRequest->getKey())
                ->lockForUpdate()
                ->first();

            if (! $application) {
                throw (new ModelNotFoundException)->setModel(TutorApplication::class, [$applicationId]);
            }

            $tutorProfile = TutorProfile::query()
                ->whereKey($application->tutor_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $accountIds = collect([
                (int) $tutoringRequest->user_id,
                (int) $tutorProfile->user_id,
            ])->unique()->sort()->values();
            $accounts = User::query()
                ->whereIn('user_id', $accountIds)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            if ($accounts->get((int) $tutoringRequest->user_id)?->isActive() !== true) {
                throw new TutorSelectionException('Tài khoản người học đã bị vô hiệu hóa nên không thể chọn gia sư.');
            }

            if ($accounts->get((int) $tutorProfile->user_id)?->isActive() !== true) {
                throw new TutorSelectionException('Tài khoản gia sư đã bị vô hiệu hóa nên không thể tạo hợp đồng mới.');
            }

            if (strtoupper((string) $tutoringRequest->request_type) !== 'PUBLIC') {
                throw new TutorSelectionException('Chỉ có thể chọn gia sư từ yêu cầu học công khai.');
            }

            if (strtoupper((string) $tutoringRequest->status) !== TutoringRequest::STATUS_OPEN) {
                throw new TutorSelectionException('Yêu cầu học không còn ở trạng thái chờ chọn gia sư. Vui lòng tải lại trang.');
            }

            if ($tutoringRequest->expires_at?->isPast()) {
                throw new TutorSelectionException('Yêu cầu học đã hết hạn nên không thể chọn gia sư.');
            }

            if (strtoupper((string) $application->status) !== TutorApplication::STATUS_PENDING) {
                throw new TutorSelectionException('Hồ sơ ứng tuyển này không còn ở trạng thái chờ xem xét.');
            }

            if (TutorApplication::query()
                ->where('request_id', $tutoringRequest->getKey())
                ->where('status', TutorApplication::STATUS_ACCEPTED)
                ->exists()) {
                throw new TutorSelectionException('Yêu cầu học này đã có gia sư được chọn. Hệ thống không thể chọn thêm gia sư khác.');
            }

            if (Contract::query()->where('request_id', $tutoringRequest->getKey())->exists()) {
                throw new TutorSelectionException('Yêu cầu học này đã có hợp đồng. Hệ thống không tạo hợp đồng trùng lặp.');
            }

            if ($this->hasScheduleConflict($tutoringRequest, (int) $application->tutor_profile_id)) {
                throw new TutorSelectionException('Khung giờ này không còn khả dụng vì bạn đã có lớp học khác trùng lịch.');
            }

            $effectiveFee = $application->proposed_fee ?? $tutoringRequest->expected_fee;
            if ($effectiveFee === null) {
                throw new TutorSelectionException('Yêu cầu và hồ sơ ứng tuyển chưa có mức học phí để tạo hợp đồng.');
            }

            $changedAt = now();

            $application->status = TutorApplication::STATUS_ACCEPTED;
            $application->save();

            TutorApplication::query()
                ->where('request_id', $tutoringRequest->getKey())
                ->whereKeyNot($application->getKey())
                ->where('status', TutorApplication::STATUS_PENDING)
                ->update([
                    'status' => TutorApplication::STATUS_REJECTED,
                    'updated_at' => $changedAt,
                ]);

            $tutoringRequest->status = TutoringRequest::STATUS_MATCHED;
            $tutoringRequest->save();

            RequestStatusHistory::query()->create([
                'request_id' => $tutoringRequest->getKey(),
                'initiated_by_user_id' => $ownerId,
                'old_status' => TutoringRequest::STATUS_OPEN,
                'new_status' => TutoringRequest::STATUS_MATCHED,
                'reason' => self::MATCH_REASON,
                'changed_at' => $changedAt,
            ]);

            $contract = $this->createContract($tutoringRequest, $application, (string) $effectiveFee);
            $this->notifications->publicTutorSelected($contract, $tutoringRequest, $tutorProfile);

            return $contract;
        }, 3);
    }

    protected function createContract(
        TutoringRequest $tutoringRequest,
        TutorApplication $application,
        string $effectiveFee
    ): Contract {
        $contract = new Contract;
        $contract->request_id = $tutoringRequest->getKey();
        $contract->tutor_profile_id = $application->tutor_profile_id;
        $contract->agreed_fee = $effectiveFee;
        $contract->agreed_fee_type = $tutoringRequest->fee_type;
        $contract->payment_method = null;
        $contract->learning_mode = $tutoringRequest->learning_mode;
        $contract->start_date = null;
        $contract->end_date = null;
        $contract->terms = null;
        $contract->status = Contract::STATUS_PENDING;
        $contract->save();

        return $contract;
    }

    protected function hasScheduleConflict(TutoringRequest $tutoringRequest, int $tutorProfileId): bool
    {
        return app(TutorScheduleAvailabilityService::class)->hasConflictWithRequest(
            $tutorProfileId,
            (int) $tutoringRequest->getKey()
        );
    }
}
