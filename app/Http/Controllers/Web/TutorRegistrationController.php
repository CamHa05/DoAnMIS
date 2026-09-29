<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTutorAvailability;
use App\Http\Requests\SaveTutorBasicInformation;
use App\Http\Requests\SaveTutorSpecialization;
use App\Http\Requests\SaveTutorTeachingPreferences;
use App\Http\Requests\StoreTutorDocument;
use App\Http\Requests\SubmitTutorRegistration;
use App\Models\Province;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorSubjectLevel;
use App\Models\TutorTeachingArea;
use App\Services\TutorRegistrationCompletionChecker;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TutorRegistrationController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('tutor-registration.basic-information', [
            'user' => $user,
            'tutorProfile' => $user->tutorProfile()->first(),
        ]);
    }

    public function update(SaveTutorBasicInformation $request): RedirectResponse
    {
        $userId = (int) $request->user()->user_id;
        $validated = $request->validated();

        DB::transaction(function () use ($request, $userId, $validated): void {
            $tutorProfile = TutorProfile::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($tutorProfile === null) {
                $request->user()->tutorProfile()->create($validated);

                return;
            }

            abort_unless($tutorProfile->canEditTutorRegistration(), 403);

            $tutorProfile->update($validated);
        });

        return redirect()
            ->route('tutor-registration.specialization.edit');
    }

    public function editSpecialization(Request $request): View|RedirectResponse
    {
        $tutorProfile = $request->user()
            ->tutorProfile()
            ->with([
                'tutorSubjects:tutor_subject_id,tutor_profile_id,subject_id',
                'tutorSubjects.tutorSubjectLevels:tutor_subject_level_id,tutor_subject_id,subject_level_id',
            ])
            ->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn chuyên môn.');
        }

        $subjects = Subject::query()
            ->where('status', 'ACTIVE')
            ->whereHas(
                'subjectLevels',
                fn ($query) => $query->where('status', 'ACTIVE')
            )
            ->with([
                'category:category_id,category_name',
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
            ->orderBy('category_id')
            ->orderBy('subject_name')
            ->get([
                'subject_id',
                'category_id',
                'subject_name',
            ]);

        $selectedSubjectIds = $tutorProfile->tutorSubjects
            ->pluck('subject_id')
            ->map(fn ($subjectId) => (int) $subjectId)
            ->values()
            ->all();

        $selectedLevelIdsBySubject = $tutorProfile->tutorSubjects
            ->mapWithKeys(fn ($tutorSubject) => [
                (int) $tutorSubject->subject_id => $tutorSubject->tutorSubjectLevels
                    ->pluck('subject_level_id')
                    ->map(fn ($subjectLevelId) => (int) $subjectLevelId)
                    ->values()
                    ->all(),
            ])
            ->all();

        return view('tutor-registration.specialization', compact(
            'subjects',
            'selectedSubjectIds',
            'selectedLevelIdsBySubject'
        ));
    }

    public function updateSpecialization(SaveTutorSpecialization $request): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn chuyên môn.');
        }

        $specializations = $request->specializations();
        $userId = (int) $request->user()->user_id;
        $tutorProfileId = (int) $tutorProfile->tutor_profile_id;

        DB::transaction(function () use ($specializations, $tutorProfileId, $userId): void {
            $lockedProfile = TutorProfile::query()
                ->where('tutor_profile_id', $tutorProfileId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedProfile->canEditTutorRegistration(), 403);

            $selectedSubjectIds = array_keys($specializations);
            $existingTutorSubjects = $lockedProfile->tutorSubjects()
                ->with('tutorSubjectLevels')
                ->get()
                ->keyBy(fn ($tutorSubject) => (int) $tutorSubject->subject_id);

            foreach ($specializations as $subjectId => $subjectLevelIds) {
                $tutorSubject = $existingTutorSubjects->get($subjectId)
                    ?? $lockedProfile->tutorSubjects()->create(['subject_id' => $subjectId]);

                $tutorSubject->tutorSubjectLevels()
                    ->whereNotIn('subject_level_id', $subjectLevelIds)
                    ->delete();

                $existingLevelIds = $tutorSubject->tutorSubjectLevels()
                    ->whereIn('subject_level_id', $subjectLevelIds)
                    ->pluck('subject_level_id')
                    ->map(fn ($subjectLevelId) => (int) $subjectLevelId)
                    ->all();

                $missingLevelIds = array_values(array_diff($subjectLevelIds, $existingLevelIds));

                if ($missingLevelIds !== []) {
                    $now = now();

                    TutorSubjectLevel::query()->insert(array_map(
                        fn ($subjectLevelId) => [
                            'tutor_subject_id' => $tutorSubject->tutor_subject_id,
                            'subject_level_id' => $subjectLevelId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $missingLevelIds
                    ));
                }
            }

            $lockedProfile->tutorSubjects()
                ->whereNotIn('subject_id', $selectedSubjectIds)
                ->delete();
        });

        return redirect()
            ->route('tutor-registration.teaching-preferences.edit')
            ->with('success', 'Đã lưu chuyên môn');
    }

    public function editTeachingPreferences(Request $request): View|RedirectResponse
    {
        $tutorProfile = $request->user()
            ->tutorProfile()
            ->with([
                'teachingAreas:teaching_area_id,tutor_profile_id,ward_id',
                'teachingAreas.ward:ward_id,province_id,ward_name',
                'teachingAreas.ward.province:province_id,province_name',
            ])
            ->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn hình thức giảng dạy.');
        }

        $provinces = Province::query()
            ->whereHas('wards')
            ->with([
                'wards' => fn ($query) => $query
                    ->orderBy('ward_name')
                    ->select(['ward_id', 'province_id', 'ward_name']),
            ])
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);

        $selectedWardIds = $tutorProfile->teachingAreas
            ->pluck('ward_id')
            ->map(fn ($wardId) => (int) $wardId)
            ->values()
            ->all();

        return view('tutor-registration.teaching-preferences', compact(
            'tutorProfile',
            'provinces',
            'selectedWardIds'
        ));
    }

    public function updateTeachingPreferences(SaveTutorTeachingPreferences $request): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn hình thức giảng dạy.');
        }

        $validated = $request->validated();
        $supportsOnline = (bool) $validated['supports_online'];
        $supportsOffline = (bool) $validated['supports_offline'];
        $selectedWardIds = $request->wardIds();
        $userId = (int) $request->user()->user_id;
        $tutorProfileId = (int) $tutorProfile->tutor_profile_id;

        DB::transaction(function () use (
            $supportsOnline,
            $supportsOffline,
            $selectedWardIds,
            $tutorProfileId,
            $userId
        ): void {
            $lockedProfile = TutorProfile::query()
                ->where('tutor_profile_id', $tutorProfileId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedProfile->canEditTutorRegistration(), 403);

            $lockedProfile->update([
                'supports_online' => $supportsOnline,
                'supports_offline' => $supportsOffline,
            ]);

            if (! $supportsOffline) {
                $lockedProfile->teachingAreas()->delete();

                return;
            }

            $existingAreas = $lockedProfile->teachingAreas()
                ->get(['teaching_area_id', 'ward_id'])
                ->keyBy(fn ($teachingArea) => (int) $teachingArea->ward_id);

            $lockedProfile->teachingAreas()
                ->whereNotIn('ward_id', $selectedWardIds)
                ->delete();

            $missingWardIds = array_values(array_diff(
                $selectedWardIds,
                $existingAreas->keys()->all()
            ));

            if ($missingWardIds !== []) {
                $now = now();

                TutorTeachingArea::query()->insert(array_map(
                    fn ($wardId) => [
                        'tutor_profile_id' => $lockedProfile->tutor_profile_id,
                        'ward_id' => $wardId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $missingWardIds
                ));
            }
        });

        return redirect()
            ->route('tutor-registration.availability.edit')
            ->with('success', 'Đã lưu hình thức & khu vực');
    }

    public function editAvailability(Request $request): View|RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn lịch rảnh.');
        }

        $timeSlots = TimeSlot::query()
            ->where('status', 'ACTIVE')
            ->orderBy('start_time')
            ->orderBy('end_time')
            ->orderBy('time_slot_id')
            ->get([
                'time_slot_id',
                'start_time',
                'end_time',
                'slot_name',
            ]);

        $selectedAvailability = $tutorProfile->availabilities()
            ->where('is_available', true)
            ->whereIn('time_slot_id', $timeSlots->pluck('time_slot_id'))
            ->orderBy('day_of_week')
            ->orderBy('time_slot_id')
            ->get(['day_of_week', 'time_slot_id'])
            ->groupBy('day_of_week')
            ->map(fn ($availabilities) => $availabilities
                ->pluck('time_slot_id')
                ->map(fn ($timeSlotId) => (int) $timeSlotId)
                ->values()
                ->all())
            ->all();

        $dayLabels = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
        ];

        return view('tutor-registration.availability', compact(
            'timeSlots',
            'dayLabels',
            'selectedAvailability'
        ));
    }

    public function updateAvailability(SaveTutorAvailability $request): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi chọn lịch rảnh.');
        }

        $submittedAvailability = $request->availabilities();
        $userId = (int) $request->user()->user_id;
        $tutorProfileId = (int) $tutorProfile->tutor_profile_id;

        DB::transaction(function () use (
            $submittedAvailability,
            $tutorProfileId,
            $userId
        ): void {
            $lockedProfile = TutorProfile::query()
                ->where('tutor_profile_id', $tutorProfileId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedProfile->canEditTutorRegistration(), 403);

            $existingAvailabilities = $lockedProfile->availabilities()
                ->get()
                ->keyBy(fn ($availability) => "{$availability->day_of_week}:{$availability->time_slot_id}");
            $submittedKeys = [];

            foreach ($submittedAvailability as $dayOfWeek => $timeSlotIds) {
                foreach ($timeSlotIds as $timeSlotId) {
                    $key = "$dayOfWeek:$timeSlotId";
                    $submittedKeys[$key] = true;
                    $availability = $existingAvailabilities->get($key);

                    if ($availability) {
                        if (! $availability->is_available) {
                            $availability->update(['is_available' => true]);
                        }

                        continue;
                    }

                    $lockedProfile->availabilities()->create([
                        'day_of_week' => $dayOfWeek,
                        'time_slot_id' => $timeSlotId,
                        'is_available' => true,
                    ]);
                }
            }

            foreach ($existingAvailabilities as $key => $availability) {
                if ($availability->is_available && ! isset($submittedKeys[$key])) {
                    $availability->update(['is_available' => false]);
                }
            }
        });

        return redirect()
            ->route('tutor-registration.documents.edit')
            ->with('success', 'Đã lưu lịch rảnh');
    }

    public function editDocuments(Request $request): View|RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi tải lên minh chứng.');
        }

        $documents = $tutorProfile->documents()
            ->orderByDesc('uploaded_at')
            ->orderByDesc('document_id')
            ->get();
        $disk = Storage::disk('local');
        $downloadableDocumentIds = $documents
            ->mapWithKeys(function (TutorDocument $document) use ($disk, $tutorProfile): array {
                $path = $this->privateDocumentPath(
                    $document,
                    (int) $tutorProfile->tutor_profile_id
                );

                return [
                    (int) $document->document_id => $path !== null && $disk->exists($path),
                ];
            })
            ->all();

        return view('tutor-registration.documents', [
            'documents' => $documents,
            'documentTypes' => TutorDocument::REGISTRATION_TYPE_LABELS,
            'downloadableDocumentIds' => $downloadableDocumentIds,
            'maximumDocuments' => TutorDocument::MAX_DOCUMENTS_PER_PROFILE,
        ]);
    }

    public function storeDocument(StoreTutorDocument $request): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi tải lên minh chứng.');
        }

        if ($tutorProfile->documents()->count() >= TutorDocument::MAX_DOCUMENTS_PER_PROFILE) {
            throw ValidationException::withMessages([
                'document' => 'Bạn đã tải lên tối đa 10 tài liệu.',
            ]);
        }

        $uploadedFile = $request->file('document');

        if (! $uploadedFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document' => 'Tệp minh chứng không hợp lệ.',
            ]);
        }

        $storageDirectory = TutorDocument::STORAGE_PREFIX.'/'.$tutorProfile->tutor_profile_id;
        $storedPath = $uploadedFile->store($storageDirectory, 'local');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'document' => 'Không thể lưu tệp minh chứng. Vui lòng thử lại.',
            ]);
        }

        try {
            DB::transaction(function () use ($request, $tutorProfile, $storedPath): void {
                $lockedProfile = TutorProfile::query()
                    ->where('tutor_profile_id', $tutorProfile->tutor_profile_id)
                    ->where('user_id', $request->user()->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedProfile->canEditTutorRegistration(), 403);

                if ($lockedProfile->documents()->count() >= TutorDocument::MAX_DOCUMENTS_PER_PROFILE) {
                    throw ValidationException::withMessages([
                        'document' => 'Bạn đã tải lên tối đa 10 tài liệu.',
                    ]);
                }

                $document = new TutorDocument([
                    'document_type' => $request->validated('document_type'),
                    'document_name' => $request->validated('document_name'),
                    'file_url' => $storedPath,
                ]);
                $document->verification_status = TutorDocument::STATUS_PENDING;

                $lockedProfile->documents()->save($document);
            });
        } catch (Throwable $exception) {
            if (! Storage::disk('local')->delete($storedPath)) {
                Log::warning('Không thể dọn tệp minh chứng sau khi lưu dữ liệu thất bại.', [
                    'path' => $storedPath,
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('tutor-registration.documents.edit')
            ->with('success', 'Đã tải lên minh chứng.');
    }

    public function continueDocuments(Request $request): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi tải lên minh chứng.');
        }

        if (! $tutorProfile->documents()->exists()) {
            throw ValidationException::withMessages([
                'documents' => 'Vui lòng tải lên ít nhất một tài liệu trước khi tiếp tục.',
            ]);
        }

        return redirect()
            ->route('tutor-registration.confirmation.edit')
            ->with('success', 'Đã lưu minh chứng.');
    }

    public function editConfirmation(
        Request $request,
        TutorRegistrationCompletionChecker $completionChecker
    ): View|RedirectResponse {
        $tutorProfile = $request->user()
            ->tutorProfile()
            ->with([
                'tutorSubjects' => fn ($query) => $query->orderBy('tutor_subject_id'),
                'tutorSubjects.subject',
                'tutorSubjects.tutorSubjectLevels' => fn ($query) => $query->orderBy('tutor_subject_level_id'),
                'tutorSubjects.tutorSubjectLevels.subjectLevel',
                'teachingAreas' => fn ($query) => $query->orderBy('teaching_area_id'),
                'teachingAreas.ward.province',
                'availabilities' => fn ($query) => $query
                    ->where('is_available', true)
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'availabilities.timeSlot',
                'documents' => fn ($query) => $query
                    ->orderByDesc('uploaded_at')
                    ->orderByDesc('document_id'),
            ])
            ->first();

        if ($tutorProfile === null) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi xác nhận hồ sơ.');
        }

        $completionErrors = $completionChecker->errors($tutorProfile);
        $disk = Storage::disk('local');
        $downloadableDocumentIds = $tutorProfile->documents
            ->mapWithKeys(function (TutorDocument $document) use ($disk, $tutorProfile): array {
                $path = $this->privateDocumentPath(
                    $document,
                    (int) $tutorProfile->tutor_profile_id
                );

                return [
                    (int) $document->document_id => $path !== null && $disk->exists($path),
                ];
            })
            ->all();

        return view('tutor-registration.confirmation', [
            'tutorProfile' => $tutorProfile,
            'completionErrors' => $completionErrors,
            'canEdit' => $tutorProfile->canEditTutorRegistration(),
            'canSubmit' => $tutorProfile->canSubmitTutorRegistration() && $completionErrors === [],
            'downloadableDocumentIds' => $downloadableDocumentIds,
            'dayLabels' => [
                1 => 'Thứ 2',
                2 => 'Thứ 3',
                3 => 'Thứ 4',
                4 => 'Thứ 5',
                5 => 'Thứ 6',
                6 => 'Thứ 7',
                7 => 'Chủ nhật',
            ],
        ]);
    }

    public function submitConfirmation(
        SubmitTutorRegistration $request,
        TutorRegistrationCompletionChecker $completionChecker,
        SystemNotificationService $notifications
    ): RedirectResponse {
        $userId = (int) $request->user()->user_id;

        $outcome = DB::transaction(function () use ($completionChecker, $userId, $notifications): string {
            $tutorProfile = TutorProfile::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($tutorProfile === null) {
                return 'missing';
            }

            if ($tutorProfile->hasSubmittedTutorRegistration()) {
                return 'already-submitted';
            }

            abort_unless($tutorProfile->canSubmitTutorRegistration(), 403);

            $completionErrors = $completionChecker->errors($tutorProfile);

            if ($completionErrors !== []) {
                throw ValidationException::withMessages(
                    collect($completionErrors)
                        ->mapWithKeys(fn (string $message, string $step) => [
                            "completion.$step" => $message,
                        ])
                        ->all()
                );
            }

            $tutorProfile->forceFill([
                'approval_status' => TutorProfile::STATUS_PENDING,
                'approved_at' => null,
                'submitted_at' => now(),
            ])->save();

            $notifications->tutorProfileSubmitted($tutorProfile);

            return 'submitted';
        });

        if ($outcome === 'missing') {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi xác nhận hồ sơ.');
        }

        return redirect()
            ->route('tutor-registration.confirmation.edit')
            ->with(
                'success',
                $outcome === 'already-submitted'
                    ? 'Hồ sơ của bạn đã được gửi xét duyệt.'
                    : 'Đã gửi hồ sơ xét duyệt.'
            );
    }

    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        abort_if($tutorProfile === null, 404);

        $ownedDocument = $tutorProfile->documents()
            ->where('document_id', $document)
            ->firstOrFail();
        $path = $this->privateDocumentPath(
            $ownedDocument,
            (int) $tutorProfile->tutor_profile_id
        );

        abort_if($path === null, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $downloadName = $this->documentDownloadName($ownedDocument, $path);
        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->query('disposition') === 'inline') {
            $mimeType = $this->documentPreviewMimeType($path);

            abort_if($mimeType === null, 415, 'Tệp này không hỗ trợ xem trước.');

            return $disk->response(
                $path,
                $downloadName,
                [...$headers, 'Content-Type' => $mimeType],
                'inline'
            );
        }

        return $disk->download(
            $path,
            $downloadName,
            $headers
        );
    }

    public function destroyDocument(Request $request, int $document): RedirectResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->first();

        if (! $tutorProfile) {
            return redirect()
                ->route('tutor-registration.basic.edit')
                ->with('error', 'Vui lòng hoàn tất thông tin cơ bản trước khi quản lý minh chứng.');
        }

        $disk = Storage::disk('local');
        $originalPath = null;
        $stagedPath = null;

        try {
            DB::transaction(function () use (
                $request,
                $tutorProfile,
                $document,
                $disk,
                &$originalPath,
                &$stagedPath
            ): void {
                $lockedProfile = TutorProfile::query()
                    ->where('tutor_profile_id', $tutorProfile->tutor_profile_id)
                    ->where('user_id', $request->user()->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedProfile->canEditTutorRegistration(), 403);
                $ownedDocument = $lockedProfile->documents()
                    ->where('document_id', $document)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $ownedDocument->canBeDeletedByTutor()) {
                    throw ValidationException::withMessages([
                        'documents' => 'Tài liệu đã xác minh hoặc có trạng thái không hợp lệ không thể xóa.',
                    ]);
                }

                $privatePath = $this->privateDocumentPath(
                    $ownedDocument,
                    (int) $lockedProfile->tutor_profile_id
                );

                if ($privatePath !== null && $disk->exists($privatePath)) {
                    $quarantinePath = TutorDocument::STORAGE_PREFIX.'/.trash/'
                        .Str::uuid().'.'.pathinfo($privatePath, PATHINFO_EXTENSION);

                    if (! $disk->move($privatePath, $quarantinePath)) {
                        throw ValidationException::withMessages([
                            'documents' => 'Không thể xóa tệp minh chứng. Vui lòng thử lại.',
                        ]);
                    }

                    $originalPath = $privatePath;
                    $stagedPath = $quarantinePath;
                }

                $ownedDocument->delete();
            });
        } catch (Throwable $exception) {
            if (
                $stagedPath !== null
                && $originalPath !== null
                && $disk->exists($stagedPath)
                && ! $disk->move($stagedPath, $originalPath)
            ) {
                Log::critical('Không thể khôi phục tệp minh chứng sau khi xóa dữ liệu thất bại.', [
                    'staged_path' => $stagedPath,
                    'original_path' => $originalPath,
                ]);
            }

            throw $exception;
        }

        if ($stagedPath !== null && $disk->exists($stagedPath) && ! $disk->delete($stagedPath)) {
            Log::warning('Không thể dọn tệp minh chứng trong vùng tạm sau khi xóa.', [
                'path' => $stagedPath,
            ]);
        }

        return redirect()
            ->route('tutor-registration.documents.edit')
            ->with('success', 'Đã xóa tài liệu.');
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

    private function sanitizeDocumentName(string $name, string $fallback): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim($name);

        if ($name === '' || in_array($name, ['.', '..'], true)) {
            $name = $fallback;
        }

        return Str::limit($name, 255, '');
    }

    private function documentDownloadName(TutorDocument $document, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = $this->sanitizeDocumentName(
            (string) $document->document_name,
            'tai-lieu-minh-chung'
        );

        if (Str::endsWith(strtolower($name), '.'.$extension)) {
            return $name;
        }

        return Str::limit($name, 254 - strlen($extension), '').'.'.$extension;
    }

    private function documentPreviewMimeType(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
    }
}
