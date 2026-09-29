<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\TutorSelectionException;
use App\Http\Controllers\Controller;
use App\Models\TutorApplication;
use App\Models\TutoringRequest;
use App\Services\IdentityVerificationRequirement;
use App\Services\TutorSelectionService;
use App\Services\TutoringRequestExpirationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyRequestController extends Controller
{
    public function index(
        Request $request,
        TutoringRequestExpirationService $expirationService,
        IdentityVerificationRequirement $identityRequirement
    ): View {
        $requestedStatus = $request->input('status');
        $statusFilter = is_string($requestedStatus)
            ? strtolower(trim($requestedStatus))
            : null;
        $statusFilter = in_array($statusFilter, ['active', 'matched', 'expired'], true)
            ? $statusFilter
            : null;
        $userId = $request->user()->getAuthIdentifier();
        $expirationService->expireRequestsForUser((int) $userId);

        $hasAnyRequests = $statusFilter !== null
            ? TutoringRequest::query()
                ->where('user_id', $userId)
                ->exists()
            : null;

        $myRequests = TutoringRequest::query()
            ->where('user_id', $userId)
            ->when(
                $statusFilter === 'active',
                fn ($query) => $query->whereIn('status', ['OPEN', 'PENDING'])
            )
            ->when(
                $statusFilter === 'matched',
                fn ($query) => $query->where('status', 'MATCHED')
            )
            ->when(
                $statusFilter === 'expired',
                fn ($query) => $query->where('status', 'EXPIRED')
            )
            ->with([
                'subjectLevel.subject',
                'ward.province',
                'targetTutor.user',
                'schedules' => fn ($query) => $query
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'schedules.timeSlot',
            ])
            ->withCount('applications')
            ->orderByDesc('created_at')
            ->orderByDesc('request_id')
            ->paginate(8)
            ->withQueryString();
        $hasAnyRequests ??= $myRequests->total() > 0;
        $identityVerification = $identityRequirement->verificationFor($request->user());
        $canCreateRequest = $identityRequirement->isVerified($identityVerification);
        $createRequestVerificationMessage = $canCreateRequest
            ? null
            : $identityRequirement->blockingMessage(
                $identityVerification,
                IdentityVerificationRequirement::ACTION_CREATE_REQUEST
            );

        return view('my-requests.index', compact(
            'myRequests',
            'statusFilter',
            'hasAnyRequests',
            'canCreateRequest',
            'createRequestVerificationMessage'
        ));
    }

    public function show(Request $request, TutoringRequest $tutoringRequest): View
    {
        $this->ensureRequestOwner($request, $tutoringRequest);

        if (strtoupper((string) $tutoringRequest->request_type) === 'PUBLIC') {
            $tutoringRequest->load([
                'user:user_id,full_name,avatar_url',
                'subjectLevel:subject_level_id,subject_id,level_name',
                'subjectLevel.subject:subject_id,subject_name',
                'ward:ward_id,province_id,ward_name',
                'ward.province:province_id,province_name',
                'schedules:request_schedule_id,request_id,time_slot_id,day_of_week',
                'schedules.timeSlot:time_slot_id,start_time,end_time',
            ])->loadCount('applications');

            return view('requests.show', [
                'tutoringRequest' => $tutoringRequest,
                'viewerContext' => 'owner',
                'currentApplication' => null,
            ]);
        }

        $tutoringRequest->load([
            'subjectLevel.subject',
            'ward.province',
            'targetTutor.user',
            'contract',
            'schedules' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'schedules.timeSlot',
        ])->loadCount('applications');

        return view('my-requests.show', compact('tutoringRequest'));
    }

    public function applicationsIndex(
        Request $request,
        TutoringRequest $tutoringRequest
    ): View {
        $this->ensureRequestOwner($request, $tutoringRequest);

        $sort = $this->normalizedApplicationSort($request->query('sort'));

        $tutoringRequest->load([
            'subjectLevel.subject',
            'contract:contract_id,request_id',
            'schedules' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'schedules.timeSlot',
        ])->loadCount([
            'applications',
            'applications as pending_applications_count' => fn ($query) => $query
                ->where('status', TutorApplication::STATUS_PENDING),
            'applications as accepted_applications_count' => fn ($query) => $query
                ->where('status', TutorApplication::STATUS_ACCEPTED),
        ]);

        $applications = $tutoringRequest->applications()
            ->with([
                'tutorProfile.user:user_id,full_name,avatar_url,status',
                'tutorProfile.tutorSubjects.subject',
                'tutorProfile.tutorSubjects.tutorSubjectLevels.subjectLevel',
            ]);

        $this->applyApplicationSort($applications, $sort, $tutoringRequest->expected_fee);

        $applications = $applications
            ->paginate(10)
            ->withQueryString();

        return view('my-requests.applications.index', array_merge(
            compact('tutoringRequest', 'applications', 'sort'),
            $this->requestPresentation($tutoringRequest)
        ));
    }

    public function applicationsShow(
        Request $request,
        TutoringRequest $tutoringRequest,
        TutorApplication $tutorApplication
    ): View {
        $this->ensureRequestOwner($request, $tutoringRequest);

        abort_unless(
            (int) $tutorApplication->request_id === (int) $tutoringRequest->getKey(),
            404
        );

        $tutoringRequest->load([
            'subjectLevel.subject',
            'schedules' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'schedules.timeSlot',
        ]);

        $tutorApplication->load([
            'tutorProfile.user:user_id,full_name,avatar_url,status',
            'tutorProfile.tutorSubjects.subject',
            'tutorProfile.tutorSubjects.tutorSubjectLevels.subjectLevel',
            'tutorProfile.teachingAreas.ward.province',
            'tutorProfile.availabilities' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'tutorProfile.availabilities.timeSlot',
        ]);

        return view('my-requests.applications.show', array_merge(
            compact('tutoringRequest', 'tutorApplication'),
            $this->requestPresentation($tutoringRequest)
        ));
    }

    public function selectTutor(
        Request $request,
        TutoringRequest $tutoringRequest,
        TutorApplication $tutorApplication,
        TutorSelectionService $selectionService
    ): RedirectResponse {
        $this->ensureRequestOwner($request, $tutoringRequest);

        abort_unless(
            (int) $tutorApplication->request_id === (int) $tutoringRequest->getKey(),
            404
        );

        try {
            $selectionService->select(
                (int) $tutoringRequest->getKey(),
                (int) $tutorApplication->getKey(),
                (int) $request->user()->getAuthIdentifier()
            );
        } catch (TutorSelectionException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (QueryException $exception) {
            report($exception);

            return back()->with(
                'error',
                'Không thể chọn gia sư lúc này. Dữ liệu không thay đổi, vui lòng thử lại.'
            );
        }

        return redirect()
            ->route('my-requests.applications.index', $tutoringRequest)
            ->with('success', 'Đã chọn gia sư thành công.');
    }

    private function ensureRequestOwner(Request $request, TutoringRequest $tutoringRequest): void
    {
        abort_unless(
            (int) $tutoringRequest->user_id === (int) $request->user()->getAuthIdentifier(),
            403
        );
    }

    private function normalizedApplicationSort(mixed $sort): string
    {
        return is_string($sort) && in_array(
            $sort,
            ['newest', 'oldest', 'fee_low', 'fee_high'],
            true
        ) ? $sort : 'newest';
    }

    private function applyApplicationSort($query, string $sort, mixed $expectedFee): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('applied_at')->orderBy('application_id'),
            'fee_low' => $query->orderByRaw(
                'CAST(COALESCE(proposed_fee, ?) AS DECIMAL(12, 2)) ASC, application_id ASC',
                [$expectedFee]
            ),
            'fee_high' => $query->orderByRaw(
                'CAST(COALESCE(proposed_fee, ?) AS DECIMAL(12, 2)) DESC, application_id DESC',
                [$expectedFee]
            ),
            default => $query->orderByDesc('applied_at')->orderByDesc('application_id'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function requestPresentation(TutoringRequest $tutoringRequest): array
    {
        $subject = trim((string) $tutoringRequest->subjectLevel?->subject?->subject_name);
        $level = trim((string) $tutoringRequest->subjectLevel?->level_name);
        $requestTitle = collect([$subject, $level])
            ->filter(fn (string $value) => $value !== '')
            ->implode(' · ');
        $requestTitle = $requestTitle !== '' ? $requestTitle : 'Yêu cầu tìm gia sư';
        $status = strtoupper(trim((string) $tutoringRequest->status));
        $requestStatusLabel = match ($status) {
            'OPEN' => 'Đang mở',
            'MATCHED' => 'Đã ghép gia sư',
            'EXPIRED' => 'Đã hết hạn',
            default => $tutoringRequest->statusLabel(),
        };
        $feeUnit = match (strtoupper(trim((string) $tutoringRequest->fee_type))) {
            'HOURLY' => '/giờ',
            'MONTHLY' => '/tháng',
            default => '',
        };

        return compact('requestTitle', 'requestStatusLabel', 'feeUnit');
    }
}
