<?php

namespace App\Services;

use App\Exceptions\ContractConfirmationException;
use App\Models\ClassSchedule;
use App\Models\Contract;
use App\Models\ContractConfirmation;
use App\Models\RequestSchedule;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractConfirmationService
{
    public function __construct(
        private readonly ContractTermsGenerator $termsGenerator,
        private readonly SystemNotificationService $notifications
    ) {}

    public function confirm(
        int $contractId,
        int $userId,
        mixed $paymentMethod,
        mixed $startDate = null,
        mixed $endDate = null
    ): bool {
        return DB::transaction(function () use ($contractId, $userId, $paymentMethod, $startDate, $endDate): bool {
            $contract = Contract::query()
                ->whereKey($contractId)
                ->lockForUpdate()
                ->firstOrFail();

            $tutoringRequest = TutoringRequest::query()
                ->whereKey($contract->request_id)
                ->firstOrFail();
            $tutorProfile = TutorProfile::query()
                ->whereKey($contract->tutor_profile_id)
                ->firstOrFail();

            $learnerId = (int) $tutoringRequest->user_id;
            $tutorId = (int) $tutorProfile->user_id;
            $isLearner = $userId === $learnerId;
            $isTutor = $userId === $tutorId;

            if (! $isLearner && ! $isTutor) {
                throw new AuthorizationException('Bạn không có quyền xác nhận hợp đồng này.');
            }

            $participant = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $participant->isActive()) {
                throw new ContractConfirmationException(
                    'Tài khoản đã bị vô hiệu hóa nên không thể xác nhận hợp đồng.'
                );
            }

            $participantIds = array_values(array_unique([$learnerId, $tutorId]));
            $confirmations = ContractConfirmation::query()
                ->where('contract_id', $contract->getKey())
                ->whereIn('user_id', $participantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');
            $existingConfirmation = $confirmations->get($userId);
            $learnerConfirmation = $confirmations->get($learnerId);

            if (strtoupper((string) $contract->status) !== Contract::STATUS_PENDING) {
                throw new ContractConfirmationException('Hợp đồng này không còn ở trạng thái chờ xác nhận.');
            }

            if (
                $existingConfirmation
                && strtoupper((string) $existingConfirmation->confirmation_status) === ContractConfirmation::STATUS_CONFIRMED
            ) {
                throw new ContractConfirmationException('Bạn đã xác nhận hợp đồng này trước đó.');
            }

            if ($isLearner) {
                $normalizedPaymentMethod = is_string($paymentMethod)
                    ? strtoupper(trim($paymentMethod))
                    : '';

                if (! in_array($normalizedPaymentMethod, Contract::PAYMENT_METHODS, true)) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Vui lòng chọn phương thức thanh toán.',
                    ]);
                }

                [$normalizedStartDate, $normalizedEndDate] = $this->validatedDates($startDate, $endDate);

                $contract->payment_method = $normalizedPaymentMethod;
                $contract->start_date = $normalizedStartDate;
                $contract->end_date = $normalizedEndDate;
                $contract->terms = $this->termsGenerator->generate($contract);
                $contract->save();
            } elseif (
                ! $learnerConfirmation
                || strtoupper((string) $learnerConfirmation->confirmation_status) !== ContractConfirmation::STATUS_CONFIRMED
            ) {
                throw new ContractConfirmationException('Đang chờ người học xác nhận.');
            } elseif (
                ! in_array((string) $contract->payment_method, Contract::PAYMENT_METHODS, true)
                || $contract->start_date === null
                || $contract->end_date === null
                || blank($contract->terms)
            ) {
                throw new ContractConfirmationException(
                    'Hợp đồng chưa có snapshot điều khoản hoàn chỉnh. Vui lòng liên hệ quản trị viên để kiểm tra dữ liệu.'
                );
            }

            $confirmation = $existingConfirmation ?? new ContractConfirmation;
            $confirmation->contract_id = $contract->getKey();
            $confirmation->user_id = $userId;
            $confirmation->confirmation_status = ContractConfirmation::STATUS_CONFIRMED;
            $confirmation->confirmed_at = now();
            $confirmation->save();

            $confirmedParticipantCount = ContractConfirmation::query()
                ->where('contract_id', $contract->getKey())
                ->whereIn('user_id', $participantIds)
                ->where('confirmation_status', ContractConfirmation::STATUS_CONFIRMED)
                ->distinct()
                ->count('user_id');

            if (count($participantIds) !== 2 || $confirmedParticipantCount !== 2) {
                if ($isLearner) {
                    $this->notifications->learnerConfirmedContract($contract, $tutorProfile);
                }

                return false;
            }

            $contract->status = Contract::STATUS_CONFIRMED;
            $contract->save();

            $tutoringClass = $this->createTutoringClass($contract, $tutoringRequest);
            $this->notifications->classCreated(
                $tutoringClass,
                $contract,
                $learnerId,
                $tutorId
            );

            return true;
        }, 3);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function validatedDates(mixed $startDate, mixed $endDate): array
    {
        $normalizedStartDate = is_string($startDate) ? trim($startDate) : '';
        $normalizedEndDate = is_string($endDate) ? trim($endDate) : '';

        if ($normalizedStartDate === '') {
            throw ValidationException::withMessages([
                'start_date' => 'Vui lòng chọn ngày bắt đầu.',
            ]);
        }

        if ($normalizedEndDate === '') {
            throw ValidationException::withMessages([
                'end_date' => 'Vui lòng chọn ngày kết thúc.',
            ]);
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedStartDate);
        $startErrors = DateTimeImmutable::getLastErrors();

        if (
            ! $start
            || ($startErrors !== false && ($startErrors['warning_count'] > 0 || $startErrors['error_count'] > 0))
            || $start->format('Y-m-d') !== $normalizedStartDate
        ) {
            throw ValidationException::withMessages([
                'start_date' => 'Ngày bắt đầu không hợp lệ.',
            ]);
        }

        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedEndDate);
        $endErrors = DateTimeImmutable::getLastErrors();

        if (
            ! $end
            || ($endErrors !== false && ($endErrors['warning_count'] > 0 || $endErrors['error_count'] > 0))
            || $end->format('Y-m-d') !== $normalizedEndDate
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'Ngày kết thúc không hợp lệ.',
            ]);
        }

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'end_date' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            ]);
        }

        return [$normalizedStartDate, $normalizedEndDate];
    }

    protected function createTutoringClass(
        Contract $contract,
        TutoringRequest $tutoringRequest
    ): TutoringClass {
        if ($contract->start_date === null || $contract->end_date === null) {
            throw new ContractConfirmationException(
                'Hợp đồng chưa có đủ thời hạn nên chưa thể tạo lớp học. Vui lòng liên hệ quản trị viên để kiểm tra dữ liệu.'
            );
        }

        $requestSchedules = RequestSchedule::query()
            ->where('request_id', $tutoringRequest->getKey())
            ->orderBy('day_of_week')
            ->orderBy('time_slot_id')
            ->lockForUpdate()
            ->get();

        if ($requestSchedules->isEmpty()) {
            throw new ContractConfirmationException(
                'Yêu cầu học chưa có lịch hợp lệ nên chưa thể tạo lớp học.'
            );
        }

        $tutoringClass = TutoringClass::query()
            ->where('contract_id', $contract->getKey())
            ->lockForUpdate()
            ->first();

        if (! $tutoringClass) {
            $tutoringRequest->loadMissing('subjectLevel.subject');

            $tutoringClass = new TutoringClass;
            $tutoringClass->contract_id = $contract->getKey();
            $generatedClassName = TutoringClass::nameFromSubjectLevel($tutoringRequest->subjectLevel);
            $tutoringClass->class_name = $generatedClassName !== '' ? $generatedClassName : null;
            $tutoringClass->start_date = $contract->start_date;
            $tutoringClass->end_date = $contract->end_date;
            $tutoringClass->status = TutoringClass::STATUS_ACTIVE;
            $tutoringClass->save();
        }

        foreach ($requestSchedules as $requestSchedule) {
            ClassSchedule::query()->firstOrCreate(
                [
                    'class_id' => $tutoringClass->getKey(),
                    'time_slot_id' => $requestSchedule->time_slot_id,
                    'day_of_week' => $requestSchedule->day_of_week,
                ],
                [
                    'effective_from' => $contract->start_date,
                    'effective_to' => $contract->end_date,
                ]
            );
        }

        return $tutoringClass;
    }
}
