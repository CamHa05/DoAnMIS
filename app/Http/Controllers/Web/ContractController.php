<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ContractConfirmationException;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractConfirmation;
use App\Services\ContractConfirmationService;
use App\Services\ContractTermsGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function show(
        Request $request,
        Contract $contract,
        ContractTermsGenerator $termsGenerator
    ): View
    {
        $contract->load([
            'tutoringRequest.user:user_id,full_name,avatar_url',
            'tutoringRequest.subjectLevel:subject_level_id,subject_id,level_name',
            'tutoringRequest.subjectLevel.subject:subject_id,subject_name',
            'tutoringRequest.ward:ward_id,province_id,ward_name',
            'tutoringRequest.ward.province:province_id,province_name',
            'tutoringRequest.schedules' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'tutoringRequest.schedules.timeSlot:time_slot_id,start_time,end_time',
            'tutorProfile:tutor_profile_id,user_id,headline',
            'tutorProfile.user:user_id,full_name,avatar_url',
            'tutoringClass:class_id,contract_id',
            'confirmations:confirmation_id,contract_id,user_id,confirmation_status,confirmed_at',
        ]);

        $tutoringRequest = $contract->tutoringRequest;
        $tutorProfile = $contract->tutorProfile;
        $tutoringClass = $contract->tutoringClass;
        $learnerId = (int) $tutoringRequest?->user_id;
        $tutorId = (int) $tutorProfile?->user_id;
        $userId = (int) $request->user()->getAuthIdentifier();

        abort_unless(in_array($userId, [$learnerId, $tutorId], true), 403);

        $confirmations = $contract->confirmations->keyBy('user_id');
        $learnerConfirmation = $confirmations->get($learnerId);
        $tutorConfirmation = $confirmations->get($tutorId);
        $currentConfirmation = $confirmations->get($userId);
        $isLearner = $userId === $learnerId;
        $hasCurrentUserConfirmed = strtoupper((string) $currentConfirmation?->confirmation_status)
            === ContractConfirmation::STATUS_CONFIRMED;
        $hasLearnerConfirmed = strtoupper((string) $learnerConfirmation?->confirmation_status)
            === ContractConfirmation::STATUS_CONFIRMED;
        $shouldShowTermsPreview = $isLearner
            && strtoupper((string) $contract->status) === Contract::STATUS_PENDING
            && ! $hasCurrentUserConfirmed;
        $termsPreview = null;
        $termsPreviewTemplate = null;

        if ($shouldShowTermsPreview) {
            $previewPaymentMethod = $request->old('payment_method', $contract->payment_method);
            $previewStartDate = $request->old('start_date', $contract->start_date?->format('Y-m-d'));
            $previewEndDate = $request->old('end_date', $contract->end_date?->format('Y-m-d'));
            $termsPreview = $termsGenerator->preview(
                $contract,
                $previewPaymentMethod,
                $previewStartDate,
                $previewEndDate
            );
            $termsPreviewTemplate = $termsGenerator->previewTemplate($contract);
        }

        $requestDetailRoute = $isLearner
            ? route('my-requests.show', $tutoringRequest)
            : (strtoupper((string) $tutoringRequest?->request_type) === 'PUBLIC'
                ? route('requests.show', $tutoringRequest)
                : null);

        return view('contracts.show', compact(
            'contract',
            'tutoringRequest',
            'tutorProfile',
            'learnerConfirmation',
            'tutorConfirmation',
            'currentConfirmation',
            'isLearner',
            'hasCurrentUserConfirmed',
            'hasLearnerConfirmed',
            'shouldShowTermsPreview',
            'termsPreview',
            'termsPreviewTemplate',
            'requestDetailRoute',
            'tutoringClass'
        ));
    }

    public function confirm(
        Request $request,
        Contract $contract,
        ContractConfirmationService $confirmationService
    ): RedirectResponse {
        $this->authorizeParticipant($request, $contract);

        try {
            $contractCompleted = $confirmationService->confirm(
                (int) $contract->getKey(),
                (int) $request->user()->getAuthIdentifier(),
                $request->input('payment_method'),
                $request->input('start_date'),
                $request->input('end_date')
            );
        } catch (ContractConfirmationException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (QueryException $exception) {
            report($exception);

            return back()->with(
                'error',
                'Không thể xác nhận hợp đồng lúc này. Dữ liệu không thay đổi, vui lòng thử lại.'
            );
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with(
                'success',
                $contractCompleted
                    ? 'Hợp đồng đã được xác nhận đầy đủ và lớp học đã được tạo.'
                    : 'Xác nhận hợp đồng thành công.'
            );
    }

    private function authorizeParticipant(Request $request, Contract $contract): void
    {
        $contract->loadMissing([
            'tutoringRequest:request_id,user_id',
            'tutorProfile:tutor_profile_id,user_id',
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();

        abort_unless(
            in_array($userId, [
                (int) $contract->tutoringRequest?->user_id,
                (int) $contract->tutorProfile?->user_id,
            ], true),
            403
        );
    }
}
