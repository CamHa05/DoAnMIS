<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TutorApplication;
use App\Models\TutorDocument;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Services\TutoringRequestExpirationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TutorAreaController extends Controller
{
    public function profile(Request $request): View
    {
        $tutorProfile = TutorProfile::query()
            ->where('user_id', $request->user()->getKey())
            ->with([
                'user:user_id,full_name,avatar_url,status',
                'tutorSubjects' => fn ($query) => $query->orderBy('tutor_subject_id'),
                'tutorSubjects.subject:subject_id,subject_name',
                'tutorSubjects.tutorSubjectLevels' => fn ($query) => $query
                    ->orderBy('tutor_subject_level_id'),
                'tutorSubjects.tutorSubjectLevels.subjectLevel:subject_level_id,level_name,sort_order',
                'teachingAreas' => fn ($query) => $query->orderBy('teaching_area_id'),
                'teachingAreas.ward:ward_id,province_id,ward_name',
                'teachingAreas.ward.province:province_id,province_name',
                'availabilities' => fn ($query) => $query
                    ->where('is_available', true)
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'availabilities.timeSlot:time_slot_id,start_time,end_time,slot_name',
                'documents' => fn ($query) => $query
                    ->orderByDesc('uploaded_at')
                    ->orderByDesc('document_id'),
                'changeRequests' => fn ($query) => $query
                    ->where('change_type', '!=', TutorProfileChangeRequest::TYPE_SPECIALIZATION)
                    ->whereIn('status', [
                        TutorProfileChangeRequest::STATUS_PENDING,
                        TutorProfileChangeRequest::STATUS_REJECTED,
                    ])
                    ->latest('change_request_id'),
            ])
            ->firstOrFail();

        $subjectGroups = $tutorProfile->tutorSubjects
            ->filter(fn ($tutorSubject) => $tutorSubject->subject !== null)
            ->map(function ($tutorSubject): array {
                $levels = $tutorSubject->tutorSubjectLevels
                    ->filter(fn ($selection) => $selection->subjectLevel !== null)
                    ->sortBy(fn ($selection) => sprintf(
                        '%06d-%s',
                        (int) ($selection->subjectLevel->sort_order ?? 0),
                        $selection->subjectLevel->level_name
                    ))
                    ->pluck('subjectLevel.level_name')
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'name' => $tutorSubject->subject->subject_name,
                    'levels' => $levels,
                ];
            })
            ->values();

        $teachingModes = collect([
            $tutorProfile->supports_online ? 'Trực tuyến' : null,
            $tutorProfile->supports_offline ? 'Trực tiếp' : null,
        ])->filter()->values();

        $areaLabels = $tutorProfile->teachingAreas
            ->map(fn ($area) => trim(implode(', ', array_filter([
                $area->ward?->ward_name,
                $area->ward?->province?->province_name,
            ]))))
            ->filter()
            ->unique()
            ->values();

        $provinceLabels = $tutorProfile->teachingAreas
            ->pluck('ward.province.province_name')
            ->filter()
            ->unique()
            ->values();

        $dayLabels = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
        ];
        $availabilityGroups = $tutorProfile->availabilities
            ->filter(fn ($availability) => $availability->timeSlot !== null)
            ->sortBy(fn ($availability) => sprintf(
                '%02d-%s',
                (int) $availability->day_of_week,
                $availability->timeSlot->start_time
            ))
            ->groupBy('day_of_week')
            ->map(fn ($availabilities, $day) => [
                'day' => (int) $day,
                'label' => $dayLabels[(int) $day] ?? 'Ngày trong tuần',
                'slots' => $availabilities
                    ->map(fn ($availability) => sprintf(
                        '%s – %s',
                        substr((string) $availability->timeSlot->start_time, 0, 5),
                        substr((string) $availability->timeSlot->end_time, 0, 5)
                    ))
                    ->unique()
                    ->values(),
            ])
            ->values();

        $activeChanges = $tutorProfile->changeRequests
            ->whereNotIn('change_type', [
                TutorProfileChangeRequest::TYPE_DOCUMENT,
                TutorProfileChangeRequest::TYPE_SPECIALIZATION,
            ])
            ->groupBy('change_type')
            ->map(fn ($changes) => $changes->firstWhere(
                'status',
                TutorProfileChangeRequest::STATUS_PENDING
            ) ?? $changes->first());
        $documentChanges = $tutorProfile->changeRequests
            ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
            ->groupBy(fn ($change) => (int) $change->target_id)
            ->map(fn ($changes) => $changes->firstWhere(
                'status',
                TutorProfileChangeRequest::STATUS_PENDING
            ) ?? $changes->first());
        $disk = Storage::disk('local');
        $documents = $tutorProfile->documents
            ->map(function (TutorDocument $document) use (
                $disk,
                $documentChanges,
                $tutorProfile
            ): array {
                $path = $this->privateDocumentPath(
                    $document,
                    (int) $tutorProfile->tutor_profile_id
                );
                $available = $path !== null && $disk->exists($path);
                $change = $documentChanges->get((int) $document->getKey());

                return [
                    'id' => (int) $document->document_id,
                    'name' => filled($document->document_name)
                        ? $document->document_name
                        : $document->typeLabel(),
                    'type_label' => $document->typeLabel(),
                    'status_label' => $document->verificationStatusLabel(),
                    'status_tone' => $document->verificationStatusTone(),
                    'preview_type' => $available ? $this->documentPreviewType($path) : null,
                    'available' => $available,
                    'change_action' => $change?->actionLabel(),
                    'change_status_label' => $change?->statusLabel(),
                    'change_status_tone' => $change?->statusTone(),
                    'change_rejection_reason' => $change?->rejection_reason,
                    'proposed_name' => data_get($change?->payload, 'document_name'),
                ];
            })
            ->values();

        $status = $this->profileStatusView($tutorProfile);
        $canEdit = $tutorProfile->canEditTutorRegistration();
        $canManageApproved = $tutorProfile->approval_status === TutorProfile::STATUS_APPROVED;
        $canViewPublic = $tutorProfile->approval_status === TutorProfile::STATUS_APPROVED
            && strtoupper((string) $tutorProfile->user?->status) === 'ACTIVE';
        $specializationLabel = $subjectGroups
            ->map(fn (array $subject) => $subject['levels']->isNotEmpty()
                ? $subject['name'].' · '.$subject['levels']->join(', ')
                : $subject['name'])
            ->join('; ');

        return view('tutor-area.profile', [
            'tutorProfile' => $tutorProfile,
            'subjectGroups' => $subjectGroups,
            'teachingModes' => $teachingModes,
            'areaLabels' => $areaLabels,
            'provinceLabels' => $provinceLabels,
            'availabilityGroups' => $availabilityGroups,
            'documents' => $documents,
            'profileStatus' => $status,
            'activeChanges' => $activeChanges,
            'canManageApproved' => $canManageApproved,
            'canEdit' => $canEdit,
            'pendingChangeCount' => $tutorProfile->changeRequests
                ->where('status', TutorProfileChangeRequest::STATUS_PENDING)
                ->count(),
            'canViewPublic' => $canViewPublic,
            'hourlyRateLabel' => $tutorProfile->hourly_rate !== null
                ? number_format((float) $tutorProfile->hourly_rate, 0, ',', '.').'đ / giờ'
                : null,
            'specializationLabel' => $specializationLabel !== ''
                ? $specializationLabel
                : null,
            'teachingModeLabel' => $teachingModes->isNotEmpty()
                ? $teachingModes->join(', ')
                : null,
            'areaSummary' => $provinceLabels->isNotEmpty()
                ? $provinceLabels->join(', ')
                : ($areaLabels->isNotEmpty() ? $areaLabels->join('; ') : null),
        ]);
    }

    public function directRequests(
        Request $request,
        TutoringRequestExpirationService $expirationService
    ): View {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();
        $expirationService->expireDirectRequestsForTutor((int) $tutorProfile->getKey());
        $status = strtoupper((string) $request->query('status'));
        $allowedStatuses = ['PENDING', 'MATCHED', 'REJECTED', 'EXPIRED'];
        $status = in_array($status, $allowedStatuses, true) ? $status : null;
        $statusCounts = TutoringRequest::query()
            ->where('request_type', 'DIRECT')
            ->where('target_tutor_profile_id', $tutorProfile->getKey())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $query = TutoringRequest::query()
            ->where('request_type', 'DIRECT')
            ->where('target_tutor_profile_id', $tutorProfile->getKey())
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with([
                'user:user_id,full_name,avatar_url',
                'subjectLevel.subject',
                'ward.province',
                'schedules.timeSlot',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('request_id');

        return view('tutor-area.direct-requests', [
            'requests' => $query->paginate(8)->withQueryString(),
            'status' => $status,
            'statuses' => $allowedStatuses,
            'statusCounts' => $statusCounts,
            'totalRequestCount' => (int) $statusCounts->sum(),
        ]);
    }

    public function directRequestShow(
        Request $request,
        TutoringRequest $tutoringRequest,
        TutoringRequestExpirationService $expirationService
    ): View {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();
        $expirationService->expireDirectRequestsForTutor((int) $tutorProfile->getKey());
        abort_unless(
            strtoupper((string) $tutoringRequest->request_type) === 'DIRECT'
                && (int) $tutoringRequest->target_tutor_profile_id === (int) $tutorProfile->getKey(),
            404
        );
        $tutoringRequest->load([
            'user:user_id,full_name,avatar_url',
            'subjectLevel.subject',
            'ward.province',
            'schedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('time_slot_id'),
            'schedules.timeSlot',
            'contract',
        ]);

        return view('tutor-area.direct-request-show', compact('tutoringRequest'));
    }

    public function applications(Request $request): View
    {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();
        $status = strtoupper((string) $request->query('status'));
        $status = in_array($status, [
            TutorApplication::STATUS_PENDING,
            TutorApplication::STATUS_ACCEPTED,
            TutorApplication::STATUS_REJECTED,
            TutorApplication::STATUS_EXPIRED,
        ], true) ? $status : null;

        $applications = $this->applicationQuery((int) $tutorProfile->getKey())
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('applied_at')
            ->orderByDesc('application_id')
            ->paginate(8)
            ->withQueryString();
        $statusCounts = TutorApplication::query()
            ->where('tutor_profile_id', $tutorProfile->getKey())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $totalApplicationCount = (int) $statusCounts->sum();

        return view('tutor-area.applications', [
            'applications' => $applications,
            'status' => $status,
            'statusCounts' => $statusCounts,
            'totalApplicationCount' => $totalApplicationCount,
        ]);
    }

    public function applicationShow(Request $request, int $application): View
    {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();
        $application = $this->applicationQuery((int) $tutorProfile->getKey())
            ->whereKey($application)
            ->firstOrFail();

        return view('tutor-area.application-show', compact('application'));
    }

    private function applicationQuery(int $tutorProfileId): Builder
    {
        return TutorApplication::query()
            ->where('tutor_profile_id', $tutorProfileId)
            ->with([
                'tutoringRequest.subjectLevel.subject',
                'tutoringRequest.ward.province',
                'tutoringRequest.schedules' => fn ($query) => $query
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'tutoringRequest.schedules.timeSlot',
                'tutoringRequest.contract.tutoringClass.schedules.timeSlot',
            ]);
    }

    /**
     * @return array{tone: string, label: string, message: string}
     */
    private function profileStatusView(TutorProfile $tutorProfile): array
    {
        if ($tutorProfile->approval_status === TutorProfile::STATUS_APPROVED) {
            return [
                'tone' => 'approved',
                'label' => 'Hồ sơ đã được duyệt',
                'message' => 'Hồ sơ đang hiển thị với người học.',
            ];
        }

        if ($tutorProfile->approval_status === TutorProfile::STATUS_REJECTED) {
            return [
                'tone' => 'rejected',
                'label' => 'Hồ sơ cần cập nhật',
                'message' => 'Bạn có thể chỉnh sửa và gửi lại hồ sơ để xét duyệt.',
            ];
        }

        if ($tutorProfile->hasSubmittedTutorRegistration()) {
            return [
                'tone' => 'pending',
                'label' => 'Đang chờ duyệt',
                'message' => 'Hồ sơ đã được gửi và đang chờ quản trị viên xem xét.',
            ];
        }

        if ($tutorProfile->approval_status === TutorProfile::STATUS_PENDING) {
            return [
                'tone' => 'draft',
                'label' => 'Hồ sơ chưa hoàn tất',
                'message' => 'Hoàn thiện các thông tin còn thiếu trước khi gửi xét duyệt.',
            ];
        }

        return [
            'tone' => 'unknown',
            'label' => 'Chưa xác định',
            'message' => 'Trạng thái hồ sơ chưa được hệ thống xác định.',
        ];
    }

    private function privateDocumentPath(TutorDocument $document, int $tutorProfileId): ?string
    {
        $path = (string) $document->file_url;

        if (
            $path === ''
            || $path !== trim($path)
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            return null;
        }

        $expectedDirectory = TutorDocument::STORAGE_PREFIX.'/'.$tutorProfileId;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (
            dirname($path) !== $expectedDirectory
            || ! in_array($extension, TutorDocument::ALLOWED_EXTENSIONS, true)
        ) {
            return null;
        }

        return $path;
    }

    private function documentPreviewType(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'image',
            default => null,
        };
    }
}
