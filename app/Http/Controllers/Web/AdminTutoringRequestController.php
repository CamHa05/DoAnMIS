<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TutorApplication;
use App\Models\TutoringRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTutoringRequestController extends Controller
{
    public function index(Request $request): View
    {
        $search = $this->searchValue($request->query('q'));
        $requestType = $this->allowedValue(
            $request->query('type'),
            ['public', 'direct']
        );
        $status = $this->allowedValue(
            $request->query('status'),
            ['pending', 'open', 'matched', 'expired']
        );
        $learningMode = $this->allowedValue(
            $request->query('mode'),
            ['online', 'offline']
        );

        $countRow = TutoringRequest::query()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count',
                ['PENDING']
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count',
                ['OPEN']
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as matched_count',
                ['MATCHED']
            )
            ->first();

        $statistics = [
            'total' => (int) ($countRow?->total_count ?? 0),
            'pending' => (int) ($countRow?->pending_count ?? 0),
            'open' => (int) ($countRow?->open_count ?? 0),
            'matched' => (int) ($countRow?->matched_count ?? 0),
        ];

        $formattedRequestId = $this->formattedRequestId($search);

        $requestsQuery = TutoringRequest::query()
            ->with([
                'user:user_id,full_name,avatar_url',
                'subjectLevel:subject_level_id,subject_id,education_level_id,level_name',
                'subjectLevel.subject:subject_id,subject_name',
                'subjectLevel.educationLevel:education_level_id,level_name',
                'ward:ward_id,province_id,ward_name,ward_type',
                'ward.province:province_id,province_name',
            ])
            ->withCount('applications')
            ->when(
                $search !== null,
                function (Builder $query) use ($formattedRequestId, $search): void {
                    $query->where(function (Builder $searchQuery) use ($formattedRequestId, $search): void {
                        if ($formattedRequestId !== null) {
                            $searchQuery->whereKey($formattedRequestId);

                            return;
                        }

                        if (ctype_digit($search)) {
                            $searchQuery->whereKey((int) $search)
                                ->orWhereHas(
                                    'user',
                                    fn (Builder $userQuery): Builder => $userQuery->where(
                                        'full_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                );

                            return;
                        }

                        $searchQuery->whereHas(
                            'user',
                            fn (Builder $userQuery): Builder => $userQuery->where(
                                'full_name',
                                'like',
                                "%{$search}%"
                            )
                        );
                    });
                }
            )
            ->when(
                $requestType !== null,
                fn (Builder $query): Builder => $query->where(
                    'request_type',
                    strtoupper($requestType)
                )
            )
            ->when(
                $status !== null,
                fn (Builder $query): Builder => $query->where('status', strtoupper($status))
            )
            ->when(
                $learningMode !== null,
                fn (Builder $query): Builder => $query->where(
                    'learning_mode',
                    strtoupper($learningMode)
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('request_id');

        $filters = array_filter([
            'q' => $search,
            'type' => $requestType,
            'status' => $status,
            'mode' => $learningMode,
        ], fn ($value): bool => $value !== null && $value !== '');

        $requests = $requestsQuery
            ->paginate(10, [
                'request_id',
                'user_id',
                'subject_level_id',
                'ward_id',
                'request_type',
                'learning_mode',
                'expected_fee',
                'fee_type',
                'status',
                'expires_at',
                'created_at',
            ])
            ->appends($filters);

        return view('admin.requests.index', compact(
            'filters',
            'learningMode',
            'requests',
            'requestType',
            'search',
            'statistics',
            'status'
        ));
    }

    public function show(TutoringRequest $tutoringRequest): View
    {
        $tutoringRequest->load([
            'user:user_id,full_name,email,avatar_url,status',
            'subjectLevel:subject_level_id,subject_id,education_level_id,level_name',
            'subjectLevel.subject:subject_id,subject_name',
            'subjectLevel.educationLevel:education_level_id,level_name',
            'ward:ward_id,province_id,ward_name,ward_type',
            'ward.province:province_id,province_name',
            'schedules' => fn ($query) => $query
                ->with('timeSlot:time_slot_id,start_time,end_time')
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
            'applications' => fn ($query) => $query
                ->with('tutorProfile.user:user_id,full_name,avatar_url')
                ->orderByDesc('applied_at')
                ->orderByDesc('application_id'),
            'targetTutor.user:user_id,full_name,avatar_url',
            'contract.tutorProfile.user:user_id,full_name,avatar_url',
            'contract.tutoringClass',
        ]);

        $orderedSchedules = $tutoringRequest->schedules
            ->sortBy(function ($schedule): string {
                $timeSlot = $schedule->timeSlot;

                return sprintf(
                    '%d-%s-%s-%d',
                    (int) $schedule->day_of_week,
                    substr((string) $timeSlot?->start_time, 0, 5),
                    substr((string) $timeSlot?->end_time, 0, 5),
                    (int) $schedule->request_schedule_id
                );
            })
            ->values();

        $tutoringRequest->setRelation('schedules', $orderedSchedules);

        $acceptedApplication = $tutoringRequest->applications->first(
            fn (TutorApplication $application): bool => strtoupper((string) $application->status)
                === TutorApplication::STATUS_ACCEPTED
        );
        $contractTutor = $tutoringRequest->contract?->tutorProfile;
        $requestType = strtoupper((string) $tutoringRequest->request_type);
        $selectedTutor = $requestType === 'DIRECT'
            ? ($contractTutor ?? $tutoringRequest->targetTutor)
            : ($contractTutor ?? $acceptedApplication?->tutorProfile);
        $directTutor = $tutoringRequest->targetTutor ?? $contractTutor;

        return view('admin.requests.show', compact(
            'acceptedApplication',
            'directTutor',
            'selectedTutor',
            'tutoringRequest'
        ));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedValue(mixed $value, array $allowed): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true)
            ? $normalized
            : null;
    }

    private function searchValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim(mb_substr($value, 0, 100));

        return $search !== '' ? $search : null;
    }

    private function formattedRequestId(?string $search): ?int
    {
        if ($search === null || preg_match('/^#?REQ-(\d+)$/i', $search, $matches) !== 1) {
            return null;
        }

        $requestId = (int) $matches[1];

        return $requestId > 0 ? $requestId : null;
    }
}
