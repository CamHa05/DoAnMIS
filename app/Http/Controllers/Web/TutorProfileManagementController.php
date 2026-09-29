<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageApprovedTutorDocument;
use App\Http\Requests\SaveTutorAvailabilitySlot;
use App\Http\Requests\UpdateApprovedTutorProfile;
use App\Models\Province;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\TutorAvailability;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\TutorTeachingArea;
use App\Services\TutorProfileChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TutorProfileManagementController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $profile = $this->approvedProfileQuery($request)
            ->with([
                'user:user_id,full_name,avatar_url',
                'tutorSubjects' => fn ($query) => $query->orderBy('subject_id'),
                'tutorSubjects.subject:subject_id,subject_name',
                'tutorSubjects.tutorSubjectLevels' => fn ($query) => $query->orderBy('subject_level_id'),
                'tutorSubjects.tutorSubjectLevels.subjectLevel:subject_level_id,subject_id,level_name,sort_order',
                'teachingAreas:teaching_area_id,tutor_profile_id,ward_id',
                'availabilities' => fn ($query) => $query
                    ->where('is_available', true)
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'availabilities.timeSlot:time_slot_id,start_time,end_time,slot_name',
                'documents' => fn ($query) => $query
                    ->orderByDesc('uploaded_at')
                    ->orderByDesc('document_id'),
                'changeRequests' => fn ($query) => $query
                    ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                    ->whereIn('status', [
                        TutorProfileChangeRequest::STATUS_PENDING,
                        TutorProfileChangeRequest::STATUS_REJECTED,
                    ])
                    ->latest('change_request_id'),
            ])
            ->first();

        if (! $profile) {
            return redirect()
                ->route('tutor-registration.confirmation.edit')
                ->with('info', 'Hồ sơ chưa được duyệt lần đầu nên vẫn sử dụng quy trình đăng ký hiện tại.');
        }

        $subjects = Subject::query()
            ->where('status', 'ACTIVE')
            ->whereHas('subjectLevels', fn ($query) => $query->where('status', 'ACTIVE'))
            ->with([
                'subjectLevels' => fn ($query) => $query
                    ->where('status', 'ACTIVE')
                    ->orderBy('sort_order')
                    ->orderBy('level_name')
                    ->select(['subject_level_id', 'subject_id', 'level_name', 'sort_order']),
            ])
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);
        $provinces = Province::query()
            ->whereHas('wards')
            ->with([
                'wards' => fn ($query) => $query
                    ->orderBy('ward_name')
                    ->select(['ward_id', 'province_id', 'ward_name']),
            ])
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);
        $timeSlots = TimeSlot::query()
            ->where('status', 'ACTIVE')
            ->orderBy('start_time')
            ->orderBy('end_time')
            ->get(['time_slot_id', 'start_time', 'end_time', 'slot_name']);

        $selectedSpecializations = $this->currentSpecializationMap($profile);
        $specialtyCatalog = $subjects
            ->map(fn (Subject $subject): array => [
                'id' => (int) $subject->subject_id,
                'name' => (string) $subject->subject_name,
                'levels' => $subject->subjectLevels
                    ->map(fn ($level): array => [
                        'id' => (int) $level->subject_level_id,
                        'name' => (string) $level->level_name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $teachingAreaCatalog = $provinces
            ->map(fn (Province $province): array => [
                'id' => (int) $province->province_id,
                'name' => (string) $province->province_name,
                'wards' => $province->wards
                    ->map(fn ($ward): array => [
                        'id' => (int) $ward->ward_id,
                        'name' => (string) $ward->ward_name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $documentChanges = $profile->changeRequests
            ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
            ->groupBy(fn ($change) => (int) $change->target_id)
            ->map(fn ($changes) => $changes->firstWhere(
                'status',
                TutorProfileChangeRequest::STATUS_PENDING
            ) ?? $changes->first());

        return view('tutor-area.profile-edit', [
            'dayLabels' => $this->dayLabels(),
            'documentChanges' => $documentChanges,
            'documentTypes' => TutorDocument::REGISTRATION_TYPE_LABELS,
            'profile' => $profile,
            'selectedSpecializations' => $selectedSpecializations,
            'selectedWardIds' => $profile->teachingAreas
                ->pluck('ward_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            'specialtyCatalog' => $specialtyCatalog,
            'teachingAreaCatalog' => $teachingAreaCatalog,
            'timeSlots' => $timeSlots,
        ]);
    }

    public function update(
        UpdateApprovedTutorProfile $request,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $validated = $request->validated();
        $specializations = $this->specializationMapToPayload($request->specializations());
        $wardIds = $request->wardIds();

        DB::transaction(function () use (
            $changes,
            $request,
            $specializations,
            $validated,
            $wardIds
        ): void {
            $profile = $this->approvedProfileQuery($request)
                ->lockForUpdate()
                ->firstOrFail();

            $profile->update([
                'headline' => $validated['headline'],
                'bio' => $validated['bio'],
                'education_summary' => $validated['education_summary'],
                'teaching_experience' => $validated['teaching_experience'],
                'hourly_rate' => $validated['hourly_rate'],
                'supports_online' => (bool) $validated['supports_online'],
                'supports_offline' => (bool) $validated['supports_offline'],
            ]);

            $this->syncTeachingAreas(
                $profile,
                (bool) $validated['supports_offline'] ? $wardIds : []
            );
            $changes->cancelPending(
                $profile,
                TutorProfileChangeRequest::TYPE_EDUCATION
            );
            $changes->cancelPending(
                $profile,
                TutorProfileChangeRequest::TYPE_EXPERIENCE
            );

            $changes->syncSpecializations($profile, $specializations);
            $changes->cancelPending(
                $profile,
                TutorProfileChangeRequest::TYPE_SPECIALIZATION
            );
        });

        return redirect()
            ->route('tutor-area.profile.edit')
            ->with('success', 'Đã lưu thay đổi hồ sơ.');
    }

    public function storeAvailability(
        SaveTutorAvailabilitySlot $request
    ): RedirectResponse {
        $this->saveAvailability($request);

        return back()->with('success', 'Đã thêm khung giờ rảnh.');
    }

    public function updateAvailability(
        SaveTutorAvailabilitySlot $request,
        int $availability
    ): RedirectResponse {
        $this->saveAvailability($request, $availability);

        return back()->with('success', 'Đã cập nhật khung giờ rảnh.');
    }

    public function destroyAvailability(
        Request $request,
        int $availability
    ): RedirectResponse {
        DB::transaction(function () use ($availability, $request): void {
            $profile = $this->approvedProfileQuery($request)
                ->lockForUpdate()
                ->firstOrFail();
            $slot = $profile->availabilities()
                ->whereKey($availability)
                ->lockForUpdate()
                ->firstOrFail();
            $slot->update(['is_available' => false]);
        });

        return back()->with('success', 'Đã xóa khung giờ khỏi lịch rảnh.');
    }

    public function storeDocument(
        ManageApprovedTutorDocument $request,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $profile = $this->approvedProfileQuery($request)->firstOrFail();

        if ($profile->documents()->count() >= TutorDocument::MAX_DOCUMENTS_PER_PROFILE) {
            throw ValidationException::withMessages([
                'document' => 'Bạn đã có tối đa 10 tài liệu. Hãy xóa tài liệu không còn dùng trước khi thêm mới.',
            ]);
        }

        $storedPath = $this->storeUploadedDocument($request, $profile);

        try {
            DB::transaction(function () use ($changes, $profile, $request, $storedPath): void {
                $lockedProfile = $this->approvedProfileQuery($request)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedProfile->documents()->count() >= TutorDocument::MAX_DOCUMENTS_PER_PROFILE) {
                    throw ValidationException::withMessages([
                        'document' => 'Bạn đã có tối đa 10 tài liệu.',
                    ]);
                }

                $document = $lockedProfile->documents()->create([
                    'document_type' => $request->validated('document_type'),
                    'document_name' => $request->validated('document_name'),
                    'file_url' => $storedPath,
                ]);
                $document->forceFill([
                    'verification_status' => TutorDocument::STATUS_PENDING,
                    'uploaded_at' => now(),
                ])->save();

                $changes->queue(
                    $lockedProfile,
                    TutorProfileChangeRequest::TYPE_DOCUMENT,
                    TutorProfileChangeRequest::ACTION_ADD,
                    [
                        'document_type' => $document->document_type,
                        'document_name' => $document->document_name,
                        'file_url' => $document->file_url,
                    ],
                    (int) $document->getKey()
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return back()->with('success', 'Đã thêm minh chứng và gửi Admin xét duyệt.');
    }

    public function updateDocument(
        ManageApprovedTutorDocument $request,
        int $document,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $profile = $this->approvedProfileQuery($request)->firstOrFail();
        $ownedDocument = $profile->documents()->whereKey($document)->firstOrFail();
        $newPath = $request->hasFile('document')
            ? $this->storeUploadedDocument($request, $profile)
            : null;

        try {
            DB::transaction(function () use (
                $changes,
                $document,
                $newPath,
                $request
            ): void {
                $profile = $this->approvedProfileQuery($request)
                    ->lockForUpdate()
                    ->firstOrFail();
                $ownedDocument = $profile->documents()
                    ->whereKey($document)
                    ->lockForUpdate()
                    ->firstOrFail();
                $documentType = (string) $request->validated('document_type');
                $documentName = (string) $request->validated('document_name');

                if (in_array($ownedDocument->verification_status, [
                    TutorDocument::STATUS_PENDING,
                    TutorDocument::STATUS_REJECTED,
                ], true)) {
                    $oldPath = (string) $ownedDocument->file_url;
                    $ownedDocument->forceFill([
                        'document_type' => $documentType,
                        'document_name' => $documentName,
                        'file_url' => $newPath ?? $oldPath,
                        'verification_status' => TutorDocument::STATUS_PENDING,
                        'uploaded_at' => now(),
                    ])->save();

                    $changes->queue(
                        $profile,
                        TutorProfileChangeRequest::TYPE_DOCUMENT,
                        TutorProfileChangeRequest::ACTION_ADD,
                        [
                            'document_type' => $documentType,
                            'document_name' => $documentName,
                            'file_url' => $newPath ?? $oldPath,
                        ],
                        (int) $ownedDocument->getKey()
                    );

                    if ($newPath !== null && $newPath !== $oldPath) {
                        DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                    }

                    return;
                }

                abort_unless(
                    $ownedDocument->verification_status === TutorDocument::STATUS_APPROVED,
                    409
                );

                $latestDocumentChange = $profile->changeRequests()
                    ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                    ->where('change_action', TutorProfileChangeRequest::ACTION_REPLACE)
                    ->where('target_id', $ownedDocument->getKey())
                    ->whereIn('status', [
                        TutorProfileChangeRequest::STATUS_PENDING,
                        TutorProfileChangeRequest::STATUS_REJECTED,
                    ])
                    ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
                    ->latest('change_request_id')
                    ->first();
                $fileUrl = $newPath
                    ?? (string) data_get($latestDocumentChange?->payload, 'file_url', $ownedDocument->file_url);

                $changes->queue(
                    $profile,
                    TutorProfileChangeRequest::TYPE_DOCUMENT,
                    TutorProfileChangeRequest::ACTION_REPLACE,
                    [
                        'document_type' => $documentType,
                        'document_name' => $documentName,
                        'file_url' => $fileUrl,
                    ],
                    (int) $ownedDocument->getKey()
                );
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        return back()->with('success', 'Đã gửi thay đổi minh chứng để Admin xét duyệt.');
    }

    public function destroyDocument(
        Request $request,
        int $document,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $deleteAfterCommit = null;

        DB::transaction(function () use (
            $changes,
            $document,
            $request,
            &$deleteAfterCommit
        ): void {
            $profile = $this->approvedProfileQuery($request)
                ->lockForUpdate()
                ->firstOrFail();
            $ownedDocument = $profile->documents()
                ->whereKey($document)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($ownedDocument->verification_status, [
                TutorDocument::STATUS_PENDING,
                TutorDocument::STATUS_REJECTED,
            ], true)) {
                $deleteAfterCommit = (string) $ownedDocument->file_url;
                $profile->changeRequests()
                    ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                    ->where('target_id', $ownedDocument->getKey())
                    ->whereIn('status', [
                        TutorProfileChangeRequest::STATUS_PENDING,
                        TutorProfileChangeRequest::STATUS_REJECTED,
                    ])
                    ->delete();
                $ownedDocument->delete();

                return;
            }

            abort_unless(
                $ownedDocument->verification_status === TutorDocument::STATUS_APPROVED,
                409
            );
            $changes->queue(
                $profile,
                TutorProfileChangeRequest::TYPE_DOCUMENT,
                TutorProfileChangeRequest::ACTION_DELETE,
                [
                    'document_type' => $ownedDocument->document_type,
                    'document_name' => $ownedDocument->document_name,
                    'file_url' => $ownedDocument->file_url,
                ],
                (int) $ownedDocument->getKey()
            );
        });

        if ($deleteAfterCommit !== null) {
            Storage::disk('local')->delete($deleteAfterCommit);
        }

        return back()->with('success', 'Đã cập nhật yêu cầu xóa minh chứng.');
    }

    public function downloadDocumentChange(
        Request $request,
        int $changeRequest
    ): StreamedResponse {
        $profile = $this->approvedProfileQuery($request)->firstOrFail();
        $change = $profile->changeRequests()
            ->whereKey($changeRequest)
            ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
            ->firstOrFail();
        $path = (string) data_get($change->payload, 'file_url', '');

        abort_unless($this->isPrivateDocumentPath($path, (int) $profile->getKey()), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return $this->documentResponse($request, $path, (string) data_get(
            $change->payload,
            'document_name',
            'minh-chung-cap-nhat'
        ));
    }

    private function saveAvailability(
        SaveTutorAvailabilitySlot $request,
        ?int $availabilityId = null
    ): void {
        DB::transaction(function () use ($availabilityId, $request): void {
            $profile = $this->approvedProfileQuery($request)
                ->lockForUpdate()
                ->firstOrFail();
            $day = (int) $request->validated('day_of_week');
            $timeSlotId = (int) $request->validated('time_slot_id');
            $target = $profile->availabilities()
                ->where('day_of_week', $day)
                ->where('time_slot_id', $timeSlotId)
                ->lockForUpdate()
                ->first();

            if ($availabilityId === null) {
                if ($target) {
                    $target->update(['is_available' => true]);
                } else {
                    $profile->availabilities()->create([
                        'day_of_week' => $day,
                        'time_slot_id' => $timeSlotId,
                        'is_available' => true,
                    ]);
                }

                return;
            }

            $current = $profile->availabilities()
                ->whereKey($availabilityId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target && $target->getKey() !== $current->getKey()) {
                $target->update(['is_available' => true]);
                $current->update(['is_available' => false]);

                return;
            }

            $current->update([
                'day_of_week' => $day,
                'time_slot_id' => $timeSlotId,
                'is_available' => true,
            ]);
        });
    }

    private function approvedProfileQuery(Request $request)
    {
        return TutorProfile::query()
            ->where('user_id', $request->user()->getKey())
            ->where('approval_status', TutorProfile::STATUS_APPROVED);
    }

    private function syncTeachingAreas(TutorProfile $profile, array $wardIds): void
    {
        $existing = $profile->teachingAreas()
            ->get(['teaching_area_id', 'ward_id'])
            ->keyBy(fn ($area) => (int) $area->ward_id);

        $profile->teachingAreas()
            ->when(
                $wardIds === [],
                fn ($query) => $query,
                fn ($query) => $query->whereNotIn('ward_id', $wardIds)
            )
            ->delete();

        $missing = array_values(array_diff($wardIds, $existing->keys()->all()));

        if ($missing !== []) {
            $now = now();
            TutorTeachingArea::query()->insert(array_map(
                fn (int $wardId): array => [
                    'tutor_profile_id' => $profile->getKey(),
                    'ward_id' => $wardId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $missing
            ));
        }
    }

    private function currentSpecializationMap(TutorProfile $profile): array
    {
        if (! $profile->relationLoaded('tutorSubjects')) {
            $profile->load('tutorSubjects.tutorSubjectLevels');
        }

        return $profile->tutorSubjects
            ->mapWithKeys(fn ($subject): array => [
                (int) $subject->subject_id => $subject->tutorSubjectLevels
                    ->pluck('subject_level_id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->sortKeys()
            ->all();
    }

    private function specializationMapToPayload(array $specializations): array
    {
        return collect($specializations)
            ->map(fn (array $levelIds, int|string $subjectId): array => [
                'subject_id' => (int) $subjectId,
                'subject_level_ids' => collect($levelIds)
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->sortBy('subject_id')
            ->values()
            ->all();
    }

    private function storeUploadedDocument(
        ManageApprovedTutorDocument $request,
        TutorProfile $profile
    ): string {
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store(
            TutorDocument::STORAGE_PREFIX.'/'.$profile->getKey(),
            'local'
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'document' => 'Không thể lưu tệp minh chứng. Vui lòng thử lại.',
            ]);
        }

        return $path;
    }

    private function isPrivateDocumentPath(string $path, int $profileId): bool
    {
        return $path !== ''
            && $path === trim($path)
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1
            && dirname($path) === TutorDocument::STORAGE_PREFIX.'/'.$profileId
            && in_array(
                strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                TutorDocument::ALLOWED_EXTENSIONS,
                true
            );
    }

    private function documentResponse(
        Request $request,
        string $path,
        string $name
    ): StreamedResponse {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $safeName = Str::limit(trim(basename(str_replace('\\', '/', $name))), 230, '');
        $downloadName = ($safeName !== '' ? $safeName : 'minh-chung').'.'.$extension;
        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->query('disposition') === 'inline') {
            $mime = match ($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => null,
            };
            abort_if($mime === null, 415);

            return Storage::disk('local')->response(
                $path,
                $downloadName,
                [...$headers, 'Content-Type' => $mime],
                'inline'
            );
        }

        return Storage::disk('local')->download($path, $downloadName, $headers);
    }

    private function dayLabels(): array
    {
        return [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
        ];
    }
}
