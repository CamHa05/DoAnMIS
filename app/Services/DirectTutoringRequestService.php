<?php

namespace App\Services;

use App\Exceptions\TutorSelectionException;
use App\Models\Contract;
use App\Models\RequestStatusHistory;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DirectTutoringRequestService
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {}

    public function accept(int $requestId, int $tutorUserId, bool $termsAccepted): Contract
    {
        if (! $termsAccepted) {
            throw new TutorSelectionException('Bạn cần đồng ý với điều khoản nhận lớp.');
        }

        return DB::transaction(function () use ($requestId, $tutorUserId): Contract {
            $request = TutoringRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();
            $tutorProfile = TutorProfile::query()
                ->where('user_id', $tutorUserId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeTarget($request, $tutorProfile);
            $this->ensureParticipantsAreActive($request, $tutorProfile);

            if (strtoupper((string) $request->status) !== 'PENDING') {
                throw new TutorSelectionException('Yêu cầu này không còn chờ phản hồi.');
            }

            if ($request->expires_at?->isPast()) {
                throw new TutorSelectionException('Yêu cầu học đã hết hạn.');
            }

            if (app(TutorScheduleAvailabilityService::class)->hasConflictWithRequest(
                (int) $tutorProfile->getKey(),
                (int) $request->getKey()
            )) {
                throw new TutorSelectionException('Khung giờ này không còn khả dụng vì bạn đã có lớp học khác trùng lịch.');
            }

            $request->status = TutoringRequest::STATUS_MATCHED;
            $request->save();
            RequestStatusHistory::query()->create([
                'request_id' => $request->getKey(),
                'initiated_by_user_id' => $tutorUserId,
                'old_status' => 'PENDING',
                'new_status' => TutoringRequest::STATUS_MATCHED,
                'reason' => 'Gia sư đã nhận yêu cầu học trực tiếp.',
                'changed_at' => now(),
            ]);

            $contract = new Contract;
            $contract->request_id = $request->getKey();
            $contract->tutor_profile_id = $tutorProfile->getKey();
            $contract->agreed_fee = $request->expected_fee;
            $contract->agreed_fee_type = $request->fee_type;
            $contract->payment_method = null;
            $contract->learning_mode = $request->learning_mode;
            $contract->start_date = null;
            $contract->end_date = null;
            $contract->terms = null;
            $contract->status = Contract::STATUS_PENDING;
            $contract->save();

            $this->notifications->directRequestAccepted($contract, $request);

            return $contract;
        }, 3);
    }

    public function reject(int $requestId, int $tutorUserId, ?string $reason): void
    {
        DB::transaction(function () use ($requestId, $tutorUserId, $reason): void {
            $request = TutoringRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            $tutorProfile = TutorProfile::query()
                ->where('user_id', $tutorUserId)
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorizeTarget($request, $tutorProfile);
            $this->ensureParticipantsAreActive($request, $tutorProfile);

            if (strtoupper((string) $request->status) !== 'PENDING') {
                throw new TutorSelectionException('Yêu cầu này không còn chờ phản hồi.');
            }

            $this->changeStatus(
                $request,
                TutoringRequest::STATUS_REJECTED,
                $tutorUserId,
                trim((string) $reason) !== '' ? trim((string) $reason) : 'Gia sư đã từ chối yêu cầu học trực tiếp.'
            );
            $this->notifications->directRequestRejected($request);
        }, 3);
    }

    private function authorizeTarget(TutoringRequest $request, TutorProfile $tutorProfile): void
    {
        if (
            strtoupper((string) $request->request_type) !== 'DIRECT'
            || (int) $request->target_tutor_profile_id !== (int) $tutorProfile->getKey()
        ) {
            throw new AuthorizationException('Bạn không có quyền xử lý yêu cầu này.');
        }
    }

    private function ensureParticipantsAreActive(
        TutoringRequest $request,
        TutorProfile $tutorProfile
    ): void {
        $accountIds = collect([
            (int) $request->user_id,
            (int) $tutorProfile->user_id,
        ])->unique()->sort()->values();
        $accounts = User::query()
            ->whereIn('user_id', $accountIds)
            ->orderBy('user_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');

        if ($accounts->get((int) $request->user_id)?->isActive() !== true) {
            throw new TutorSelectionException('Tài khoản người học đã bị vô hiệu hóa nên yêu cầu không thể được xử lý.');
        }

        if ($accounts->get((int) $tutorProfile->user_id)?->isActive() !== true) {
            throw new TutorSelectionException('Tài khoản gia sư đã bị vô hiệu hóa nên không thể xử lý yêu cầu mới.');
        }
    }

    private function changeStatus(
        TutoringRequest $request,
        string $newStatus,
        int $userId,
        string $reason
    ): void {
        $oldStatus = (string) $request->status;
        $request->status = $newStatus;
        $request->save();
        RequestStatusHistory::query()->create([
            'request_id' => $request->getKey(),
            'initiated_by_user_id' => $userId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_at' => now(),
        ]);
    }
}
