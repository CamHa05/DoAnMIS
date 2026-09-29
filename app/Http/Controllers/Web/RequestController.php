<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicRequest;
use App\Http\Requests\StoreDirectRequest;
use App\Http\Requests\StoreTutorApplication;
use App\Models\EducationLevel;
use App\Models\IdentityVerification;
use App\Models\Province;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\TimeSlot;
use App\Models\TutoringRequest;
use App\Models\TutorApplication;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\DirectTutoringRequestService;
use App\Services\TutorScheduleAvailabilityService;
use App\Services\IdentityVerificationRequirement;
use App\Services\SystemNotificationService;
use App\Services\TutoringRequestExpirationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function __construct(
        private readonly IdentityVerificationRequirement $identityRequirement
    ) {}

    public function show(Request $request, TutoringRequest $tutoringRequest): View
    {
        abort_unless(
            strtoupper((string) $tutoringRequest->request_type) === 'PUBLIC',
            404
        );

        $tutoringRequest->load([
            'user:user_id,full_name,avatar_url,status',
            'subjectLevel:subject_level_id,subject_id,level_name',
            'subjectLevel.subject:subject_id,subject_name',
            'ward:ward_id,province_id,ward_name',
            'ward.province:province_id,province_name',
            'schedules:request_schedule_id,request_id,time_slot_id,day_of_week',
            'schedules.timeSlot:time_slot_id,start_time,end_time',
        ])->loadCount('applications');

        $viewer = $request->user();
        $viewerContext = ! $viewer && $this->acceptsApplications($tutoringRequest)
            ? 'guest_eligible'
            : 'readonly';
        $currentApplication = null;
        $tutorProfile = null;
        $identityVerificationMessage = null;

        if ($viewer && (int) $viewer->getAuthIdentifier() === (int) $tutoringRequest->user_id) {
            $viewerContext = 'owner';
        } elseif ($viewer && ! $viewer->is_admin) {
            $tutorProfile = $viewer->tutorProfile()->first();
            $identityVerification = $this->identityRequirement->verificationFor($viewer);

            if ($tutorProfile) {
                $currentApplication = $tutoringRequest->applications()
                    ->where('tutor_profile_id', $tutorProfile->getKey())
                    ->first([
                        'application_id',
                        'request_id',
                        'tutor_profile_id',
                        'message',
                        'proposed_fee',
                        'status',
                        'applied_at',
                    ]);

                if ($currentApplication) {
                    $viewerContext = 'tutor_applied';
                } elseif ($this->canTutorApply($tutorProfile, $tutoringRequest, $identityVerification)) {
                    $viewerContext = 'tutor_eligible';
                } elseif (
                    $this->canTutorApplyWithoutIdentity($tutorProfile, $tutoringRequest)
                    && ! $this->identityRequirement->isVerified($identityVerification)
                ) {
                    $viewerContext = 'tutor_identity_required';
                    $identityVerificationMessage = $this->identityRequirement->blockingMessage(
                        $identityVerification,
                        IdentityVerificationRequirement::ACTION_TUTOR_APPLICATION
                    );
                } else {
                    $viewerContext = 'readonly';
                }
            }
        }

        return view('requests.show', compact(
            'tutoringRequest',
            'viewerContext',
            'currentApplication',
            'identityVerificationMessage'
        ));
    }

    public function directCreate(
        Request $request,
        TutorProfile $tutor,
        TutorScheduleAvailabilityService $scheduleAvailability
    ): View {
        abort_unless(
            $tutor->approval_status === TutorProfile::STATUS_APPROVED
                && $tutor->user?->isActive(),
            404
        );
        $tutor->load([
            'user',
            'tutorSubjects.subject',
            'tutorSubjects.tutorSubjectLevels.subjectLevel',
            'teachingAreas.ward.province',
            'availabilities.timeSlot',
        ]);
        $provinces = Province::query()
            ->whereHas('wards')
            ->with(['wards' => fn ($query) => $query->orderBy('ward_name')->select(['ward_id', 'province_id', 'ward_name'])])
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);
        $availabilityKeys = $scheduleAvailability->availableSlotKeys((int) $tutor->getKey());
        $availableAvailabilities = $tutor->availabilities
            ->filter(fn ($availability) =>
                $availability->is_available
                && $availability->timeSlot
                && isset($availabilityKeys[$scheduleAvailability->slotKey(
                    (int) $availability->day_of_week,
                    (int) $availability->time_slot_id
                )])
            )
            ->sortBy(fn ($availability) => sprintf('%02d-%s', $availability->day_of_week, $availability->timeSlot->start_time));
        $dayLabels = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
        $subjects = $tutor->tutorSubjects->filter(fn ($subject) => $subject->subject);

        return view('requests.direct-create', compact('tutor', 'availableAvailabilities', 'dayLabels', 'subjects', 'provinces'));
    }

    public function storeDirect(
        StoreDirectRequest $request,
        TutorProfile $tutor,
        SystemNotificationService $notifications
    ): RedirectResponse {
        abort_unless(
            $tutor->approval_status === TutorProfile::STATUS_APPROVED
                && $tutor->user?->isActive(),
            404
        );
        $validated = $request->validated();
        $tutoringRequest = DB::transaction(function () use ($request, $tutor, $validated, $notifications): TutoringRequest {
            $lockedTutor = TutorProfile::query()
                ->whereKey($tutor->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedTutorUser = User::query()
                ->whereKey($lockedTutor->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedTutor->approval_status === TutorProfile::STATUS_APPROVED
                    && $lockedTutorUser->isActive(),
                404
            );

            $model = new TutoringRequest;
            $model->user_id = $request->user()->getAuthIdentifier();
            $model->target_tutor_profile_id = $lockedTutor->getKey();
            $model->subject_level_id = $validated['subject_level_id'];
            $model->ward_id = $validated['learning_mode'] === 'OFFLINE' ? $validated['ward_id'] : null;
            $model->request_type = 'DIRECT';
            $model->learning_mode = $validated['learning_mode'];
            $model->preferred_tutor_gender = 'ANY';
            $model->address_detail = $validated['learning_mode'] === 'OFFLINE' ? $validated['address_detail'] : null;
            $model->expected_fee = $validated['expected_fee'];
            $model->fee_type = $validated['fee_type'];
            $model->description = $validated['description'];
            $model->status = 'PENDING';
            $model->expires_at = $validated['expires_at'] ?? now()->addDay();
            $model->save();
            $now = now();
            $model->schedules()->insert(array_map(fn (array $schedule): array => [
                'request_id' => $model->getKey(),
                'time_slot_id' => $schedule['time_slot_id'],
                'day_of_week' => $schedule['day_of_week'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $validated['schedules']));

            $notifications->directRequestCreated($model, $lockedTutor);

            return $model;
        });

        return redirect()->route('my-requests.show', $tutoringRequest)->with('success', 'Đã gửi yêu cầu học trực tiếp.');
    }

    public function directAccept(Request $request, TutoringRequest $tutoringRequest, DirectTutoringRequestService $service): RedirectResponse
    {
        try {
            $contract = $service->accept((int) $tutoringRequest->getKey(), (int) $request->user()->getAuthIdentifier(), $request->boolean('terms_accepted'));
        } catch (\Throwable $exception) {
            if ($exception instanceof \App\Exceptions\TutorSelectionException) {
                return back()->with('error', $exception->getMessage());
            }
            throw $exception;
        }

        return redirect()->route('contracts.show', $contract)->with('success', 'Đã nhận yêu cầu. Vui lòng xem và xác nhận thỏa thuận.');
    }

    public function directReject(Request $request, TutoringRequest $tutoringRequest, DirectTutoringRequestService $service): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        try {
            $service->reject((int) $tutoringRequest->getKey(), (int) $request->user()->getAuthIdentifier(), $request->input('reason'));
        } catch (\App\Exceptions\TutorSelectionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tutor-area.direct-requests.show', $tutoringRequest)->with('success', 'Đã từ chối yêu cầu học.');
    }

    public function redirectGuestToLogin(
        Request $request,
        TutoringRequest $tutoringRequest
    ): RedirectResponse {
        abort_unless($this->acceptsApplications($tutoringRequest), 404);

        $request->session()->put(
            'url.intended',
            route('requests.show', $tutoringRequest)
        );

        return redirect()->route('login');
    }

    private function acceptsApplications(TutoringRequest $tutoringRequest): bool
    {
        return strtoupper((string) $tutoringRequest->request_type) === 'PUBLIC'
            && strtoupper((string) $tutoringRequest->status) === 'OPEN'
            && ($tutoringRequest->expires_at === null || $tutoringRequest->expires_at->isFuture())
            && $this->accountIsActive((int) $tutoringRequest->user_id);
    }

    private function canTutorApply(
        TutorProfile $tutorProfile,
        TutoringRequest $tutoringRequest,
        ?IdentityVerification $identityVerification
    ): bool {
        return $this->canTutorApplyWithoutIdentity($tutorProfile, $tutoringRequest)
            && $this->identityRequirement->isVerified($identityVerification);
    }

    private function canTutorApplyWithoutIdentity(
        TutorProfile $tutorProfile,
        TutoringRequest $tutoringRequest
    ): bool {
        if (
            $tutorProfile->approval_status !== TutorProfile::STATUS_APPROVED
            || $tutorProfile->submitted_at === null
            || strtoupper((string) $tutoringRequest->request_type) !== 'PUBLIC'
            || strtoupper((string) $tutoringRequest->status) !== 'OPEN'
            || (int) $tutorProfile->user_id === (int) $tutoringRequest->user_id
            || ($tutoringRequest->expires_at && ! $tutoringRequest->expires_at->isFuture())
            || ! $this->accountIsActive((int) $tutorProfile->user_id)
            || ! $this->accountIsActive((int) $tutoringRequest->user_id)
        ) {
            return false;
        }

        return true;
    }

    public function storeApplication(
        StoreTutorApplication $request,
        TutoringRequest $tutoringRequest,
        SystemNotificationService $notifications
    ): RedirectResponse {
        $user = $request->user();

        abort_if(
            (bool) $user->is_admin,
            403,
            'Tài khoản quản trị không thể gửi ứng tuyển.'
        );

        $tutorProfile = TutorProfile::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        abort_unless(
            $tutorProfile,
            403,
            'Bạn cần có hồ sơ gia sư đã được duyệt để ứng tuyển.'
        );

        $validated = $request->validated();

        try {
            $created = DB::transaction(function () use (
                $tutoringRequest,
                $tutorProfile,
                $validated,
                $notifications
            ): bool {
                $lockedRequest = TutoringRequest::query()
                    ->whereKey($tutoringRequest->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedTutorProfile = TutorProfile::query()
                    ->whereKey($tutorProfile->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $accountIds = collect([
                    (int) $lockedRequest->user_id,
                    (int) $lockedTutorProfile->user_id,
                ])->unique()->sort()->values();
                $lockedAccounts = User::query()
                    ->whereIn('user_id', $accountIds)
                    ->orderBy('user_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('user_id');

                abort_unless(
                    $accountIds->every(
                        fn (int $userId): bool => $lockedAccounts->get($userId)?->isActive() === true
                    ),
                    403,
                    'Bạn không thể ứng tuyển yêu cầu học này.'
                );
                $lockedIdentityVerification = $this->identityRequirement->verificationForUserId(
                    (int) $lockedTutorProfile->user_id,
                    true
                );

                abort_unless(
                    $this->canTutorApply(
                        $lockedTutorProfile,
                        $lockedRequest,
                        $lockedIdentityVerification
                    ),
                    403,
                    'Bạn không thể ứng tuyển yêu cầu học này.'
                );

                $alreadyApplied = TutorApplication::query()
                    ->where('request_id', $lockedRequest->getKey())
                    ->where('tutor_profile_id', $lockedTutorProfile->getKey())
                    ->exists();

                if ($alreadyApplied) {
                    return false;
                }

                $application = new TutorApplication;
                $application->request_id = $lockedRequest->getKey();
                $application->tutor_profile_id = $lockedTutorProfile->getKey();
                $application->message = $validated['message'];
                $application->proposed_fee = $validated['fee_option'] === 'custom'
                    ? $validated['proposed_fee']
                    : null;
                $application->status = TutorApplication::STATUS_PENDING;
                $application->applied_at = now();
                $application->save();

                $notifications->publicApplicationCreated(
                    $application,
                    $lockedRequest,
                    $lockedTutorProfile
                );

                return true;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateApplicationException($exception)) {
                throw $exception;
            }

            $created = false;
        }

        if (! $created) {
            return redirect()
                ->route('requests.show', $tutoringRequest)
                ->with('error', 'Bạn đã ứng tuyển yêu cầu học này trước đó.');
        }

        return redirect()
            ->route('requests.show', $tutoringRequest)
            ->with('success', 'Ứng tuyển thành công.');
    }

    private function isDuplicateApplicationException(QueryException $exception): bool
    {
        $driverMessage = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return str_contains($driverMessage, 'uq_tutor_application')
            || (
                str_contains($driverMessage, 'tutor_applications.request_id')
                && str_contains($driverMessage, 'tutor_applications.tutor_profile_id')
            );
    }

    public function create(): View
    {
        $subjects = Subject::query()
            ->where('status', 'ACTIVE')
            ->whereHas(
                'subjectLevels',
                fn ($query) => $query->where('status', 'ACTIVE')
            )
            ->with([
                'subjectLevels' => fn ($query) => $query
                    ->where('status', 'ACTIVE')
                    ->orderBy('sort_order')
                    ->orderBy('level_name')
                    ->select([
                        'subject_level_id',
                        'subject_id',
                        'level_name',
                        'sort_order',
                    ]),
            ])
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);

        $timeSlots = TimeSlot::query()
            ->where('status', 'ACTIVE')
            ->orderBy('start_time')
            ->orderBy('end_time')
            ->get([
                'time_slot_id',
                'start_time',
                'end_time',
                'slot_name',
            ]);

        $provinces = Province::query()
            ->whereHas('wards')
            ->with([
                'wards' => fn ($query) => $query
                    ->orderBy('ward_name')
                    ->select(['ward_id', 'province_id', 'ward_name']),
            ])
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);

        $dayLabels = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
        ];
        $defaultExpiresAt = now()->addDays(7);

        return view('requests.create', compact(
            'subjects',
            'timeSlots',
            'provinces',
            'dayLabels',
            'defaultExpiresAt'
        ));
    }

    public function store(StorePublicRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isOffline = $validated['learning_mode'] === 'OFFLINE';

        DB::transaction(function () use ($request, $validated, $isOffline): void {
            $tutoringRequest = new TutoringRequest;
            $tutoringRequest->user_id = $request->user()->getAuthIdentifier();
            $tutoringRequest->target_tutor_profile_id = null;
            $tutoringRequest->subject_level_id = $validated['subject_level_id'];
            $tutoringRequest->ward_id = $isOffline ? $validated['ward_id'] : null;
            $tutoringRequest->request_type = 'PUBLIC';
            $tutoringRequest->learning_mode = $validated['learning_mode'];
            $tutoringRequest->preferred_tutor_gender = $validated['preferred_tutor_gender'];
            $tutoringRequest->address_detail = $isOffline ? $validated['address_detail'] : null;
            $tutoringRequest->expected_fee = $validated['expected_fee'];
            $tutoringRequest->fee_type = $validated['fee_type'];
            $tutoringRequest->description = $validated['description'];
            $tutoringRequest->status = 'OPEN';
            $tutoringRequest->expires_at = $validated['expires_at'];
            $tutoringRequest->save();

            $timestamp = now();
            $scheduleRows = array_map(
                fn (array $schedule): array => [
                    'request_id' => $tutoringRequest->getKey(),
                    'time_slot_id' => $schedule['time_slot_id'],
                    'day_of_week' => $schedule['day_of_week'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $validated['schedules']
            );

            $tutoringRequest->schedules()->insert($scheduleRows);
        });

        return redirect()
            ->route('my-requests.index')
            ->with('success', 'Yêu cầu học đã được đăng thành công.');
    }

    public function index(
        Request $request,
        TutoringRequestExpirationService $expirationService
    ): View {
        $expirationService->expirePublicRequests();

        $search = trim((string) $request->input('q', ''));
        $subjectId = $request->integer('subject');
        $educationLevelId = $request->integer('education_level');
        $subjectLevelId = $request->integer('subject_level');
        $provinceId = $request->integer('province');
        $minFee = $request->integer('min_fee');
        $maxFee = $request->integer('max_fee');
        $learningMode = strtoupper((string) $request->input('mode'));
        $timeOfDay = (string) $request->input('time');
        $sort = (string) $request->input('sort', 'newest');

        $subjectId = $subjectId > 0 ? $subjectId : null;
        $educationLevelId = $educationLevelId > 0 ? $educationLevelId : null;
        $subjectLevelId = $subjectLevelId > 0 ? $subjectLevelId : null;
        $provinceId = $provinceId > 0 ? $provinceId : null;
        $minFee = $minFee > 0 ? $minFee : null;
        $maxFee = $maxFee > 0 ? $maxFee : null;
        $learningMode = in_array($learningMode, ['ONLINE', 'OFFLINE'], true)
            ? $learningMode
            : null;
        $timeOfDay = in_array($timeOfDay, ['morning', 'afternoon', 'evening'], true)
            ? $timeOfDay
            : null;
        $sort = in_array($sort, ['newest', 'oldest', 'fee_desc'], true)
            ? $sort
            : 'newest';

        $subjects = Subject::query()
            ->where('status', 'ACTIVE')
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);

        $educationLevels = EducationLevel::query()
            ->where('status', 'ACTIVE')
            ->orderBy('sort_order')
            ->orderBy('level_name')
            ->get(['education_level_id', 'level_name']);

        $levels = SubjectLevel::query()
            ->where('status', 'ACTIVE')
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($educationLevelId, fn ($query) => $query->where('education_level_id', $educationLevelId))
            ->with('subject:subject_id,subject_name')
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->orderBy('level_name')
            ->get(['subject_level_id', 'subject_id', 'education_level_id', 'level_name']);

        $selectedLevel = $subjectLevelId
            ? $levels->firstWhere('subject_level_id', $subjectLevelId)
            : null;

        if ($subjectLevelId && ! $selectedLevel) {
            $subjectLevelId = null;
        }

        $locations = Province::query()
            ->whereHas('wards')
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);

        $publicRequests = TutoringRequest::query()
            ->where('request_type', 'PUBLIC')
            ->where('status', 'OPEN')
            ->whereHas(
                'user',
                fn ($query) => $query->where('status', User::STATUS_ACTIVE)
            )
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when(
                $search !== '',
                fn ($query) => $query->whereHas(
                    'subjectLevel.subject',
                    fn ($subjectQuery) => $subjectQuery->where('subject_name', 'like', "%{$search}%")
                )
            )
            ->when(
                $subjectId,
                fn ($query) => $query->whereHas(
                    'subjectLevel',
                    fn ($levelQuery) => $levelQuery->where('subject_id', $subjectId)
                )
            )
            ->when($educationLevelId, fn ($query) => $query->whereHas(
                'subjectLevel',
                fn ($levelQuery) => $levelQuery->where('education_level_id', $educationLevelId)
            ))
            ->when($subjectLevelId, fn ($query) => $query->where('subject_level_id', $subjectLevelId))
            ->when($learningMode, fn ($query) => $query->where('learning_mode', $learningMode))
            ->when($provinceId, fn ($query) => $query->whereHas(
                'ward',
                fn ($wardQuery) => $wardQuery->where('province_id', $provinceId)
            ))
            ->when($minFee, fn ($query) => $query->where('expected_fee', '>=', $minFee))
            ->when($maxFee, fn ($query) => $query->where('expected_fee', '<=', $maxFee))
            ->when($timeOfDay, function ($query) use ($timeOfDay) {
                $query->whereHas('schedules.timeSlot', function ($timeQuery) use ($timeOfDay) {
                    match ($timeOfDay) {
                        'morning' => $timeQuery->whereTime('start_time', '<', '12:00:00'),
                        'afternoon' => $timeQuery
                            ->whereTime('start_time', '>=', '12:00:00')
                            ->whereTime('start_time', '<', '18:00:00'),
                        'evening' => $timeQuery->whereTime('start_time', '>=', '18:00:00'),
                    };
                });
            })
            ->with([
                'subjectLevel:subject_level_id,subject_id,level_name',
                'subjectLevel.subject:subject_id,subject_name',
                'ward:ward_id,province_id,ward_name',
                'ward.province:province_id,province_name',
                'schedules:request_schedule_id,request_id,time_slot_id,day_of_week',
                'schedules.timeSlot:time_slot_id,start_time,end_time',
            ])
            ->when(
                $sort === 'oldest',
                fn ($query) => $query->orderBy('created_at')
            )
            ->when(
                $sort === 'fee_desc',
                fn ($query) => $query->orderByDesc('expected_fee')->orderByDesc('created_at')
            )
            ->when(
                $sort === 'newest',
                fn ($query) => $query->orderByDesc('created_at')
            )
            ->paginate(9)
            ->withQueryString();

        $activeFilterCount = collect([
            $search,
            $subjectId,
            $educationLevelId,
            $subjectLevelId,
            $provinceId,
            $minFee,
            $maxFee,
            $learningMode,
            $timeOfDay,
        ])->filter(fn ($value) => filled($value))->count();

        return view('requests.index', [
            'publicRequests' => $publicRequests,
            'subjects' => $subjects,
            'educationLevels' => $educationLevels,
            'levels' => $levels,
            'locations' => $locations,
            'search' => $search,
            'subjectId' => $subjectId,
            'educationLevelId' => $educationLevelId,
            'subjectLevelId' => $subjectLevelId,
            'provinceId' => $provinceId,
            'minFee' => $minFee,
            'maxFee' => $maxFee,
            'learningMode' => $learningMode,
            'timeOfDay' => $timeOfDay,
            'sort' => $sort,
            'selectedLevel' => $selectedLevel,
            'activeFilterCount' => $activeFilterCount,
        ]);
    }

    private function accountIsActive(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->where('status', User::STATUS_ACTIVE)
            ->exists();
    }
}
