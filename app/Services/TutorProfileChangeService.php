<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\TutorProfileReview;
use App\Models\TutorSubjectLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TutorProfileChangeService
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {}

    public function queue(
        TutorProfile $profile,
        string $type,
        string $action,
        array $payload,
        ?int $targetId = null
    ): TutorProfileChangeRequest {
        $query = $profile->changeRequests()
            ->where('change_type', $type)
            ->when(
                $targetId === null,
                fn ($query) => $query->whereNull('target_id'),
                fn ($query) => $query->where('target_id', $targetId)
            )
            ->whereIn('status', [
                TutorProfileChangeRequest::STATUS_PENDING,
                TutorProfileChangeRequest::STATUS_REJECTED,
            ]);

        if ($type !== TutorProfileChangeRequest::TYPE_DOCUMENT || $targetId === null) {
            $query->where('change_action', $action);
        }

        $change = $query
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->latest('change_request_id')
            ->first();

        $attributes = [
            'reviewer_user_id' => null,
            'change_type' => $type,
            'change_action' => $action,
            'target_id' => $targetId,
            'payload' => $payload,
            'status' => TutorProfileChangeRequest::STATUS_PENDING,
            'rejection_reason' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];

        if ($change) {
            $obsoletePath = $type === TutorProfileChangeRequest::TYPE_DOCUMENT
                ? (string) data_get($change->payload, 'file_url', '')
                : '';
            $change->update($attributes);

            if (
                $obsoletePath !== ''
                && $obsoletePath !== (string) ($payload['file_url'] ?? '')
                && $obsoletePath !== (string) $change->targetDocument?->file_url
            ) {
                DB::afterCommit(fn () => $this->deletePrivateFile(
                    $obsoletePath,
                    (int) $profile->getKey()
                ));
            }

            $change = $change->refresh();
            $this->notifyAdminsWhenReviewIsRequired($profile, $change);

            return $change;
        }

        $change = $profile->changeRequests()->create($attributes);
        $this->notifyAdminsWhenReviewIsRequired($profile, $change);

        return $change;
    }

    public function cancelPending(
        TutorProfile $profile,
        string $type,
        ?int $targetId = null
    ): void {
        $profile->changeRequests()
            ->where('change_type', $type)
            ->whereIn('status', [
                TutorProfileChangeRequest::STATUS_PENDING,
                TutorProfileChangeRequest::STATUS_REJECTED,
            ])
            ->when(
                $targetId === null,
                fn ($query) => $query->whereNull('target_id'),
                fn ($query) => $query->where('target_id', $targetId)
            )
            ->delete();
    }

    public function approve(
        int $profileId,
        int $changeRequestId,
        User $reviewer
    ): bool {
        return DB::transaction(function () use ($profileId, $changeRequestId, $reviewer): bool {
            $profile = TutorProfile::query()
                ->whereKey($profileId)
                ->lockForUpdate()
                ->firstOrFail();
            $change = $profile->changeRequests()
                ->whereKey($changeRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $profile->approval_status !== TutorProfile::STATUS_APPROVED
                || $change->status !== TutorProfileChangeRequest::STATUS_PENDING
                || $change->change_type === TutorProfileChangeRequest::TYPE_SPECIALIZATION
            ) {
                return false;
            }

            $this->apply($profile, $change);
            $reviewedAt = now();

            $change->update([
                'reviewer_user_id' => $reviewer->getKey(),
                'status' => TutorProfileChangeRequest::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => $reviewedAt,
            ]);

            $this->recordReview($profile, $reviewer, $change, 'CHANGE_APPROVED', $reviewedAt);
            $this->notifyTutor(
                $profile,
                $change,
                'PROFILE_CHANGE_APPROVED',
                'Thay đổi hồ sơ đã được duyệt',
                $change->typeLabel().' của bạn đã được Admin duyệt và áp dụng.'
            );

            return true;
        });
    }

    public function reject(
        int $profileId,
        int $changeRequestId,
        User $reviewer,
        string $reason
    ): bool {
        return DB::transaction(function () use (
            $profileId,
            $changeRequestId,
            $reviewer,
            $reason
        ): bool {
            $profile = TutorProfile::query()
                ->whereKey($profileId)
                ->lockForUpdate()
                ->firstOrFail();
            $change = $profile->changeRequests()
                ->whereKey($changeRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $profile->approval_status !== TutorProfile::STATUS_APPROVED
                || $change->status !== TutorProfileChangeRequest::STATUS_PENDING
                || $change->change_type === TutorProfileChangeRequest::TYPE_SPECIALIZATION
            ) {
                return false;
            }

            if (
                $change->change_type === TutorProfileChangeRequest::TYPE_DOCUMENT
                && $change->change_action === TutorProfileChangeRequest::ACTION_ADD
            ) {
                $profile->documents()
                    ->whereKey($change->target_id)
                    ->where('verification_status', TutorDocument::STATUS_PENDING)
                    ->update(['verification_status' => TutorDocument::STATUS_REJECTED]);
            }

            $reviewedAt = now();
            $change->update([
                'reviewer_user_id' => $reviewer->getKey(),
                'status' => TutorProfileChangeRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_at' => $reviewedAt,
            ]);

            $this->recordReview($profile, $reviewer, $change, 'CHANGE_REJECTED', $reviewedAt);
            $this->notifyTutor(
                $profile,
                $change,
                'PROFILE_CHANGE_REJECTED',
                'Thay đổi hồ sơ cần bổ sung',
                $change->typeLabel().' chưa được duyệt. Lý do: '.$reason
            );

            return true;
        });
    }

    /**
     * @param  array<int, array{subject_id:int, subject_level_ids:array<int, int>}>  $specializations
     */
    public function syncSpecializations(TutorProfile $profile, array $specializations): void
    {
        $normalized = collect($specializations)
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['subject_id'] => collect($item['subject_level_ids'])
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->sortKeys()
            ->all();

        $this->validateSpecializations($normalized);
        $selectedSubjectIds = array_keys($normalized);
        $existingTutorSubjects = $profile->tutorSubjects()
            ->with('tutorSubjectLevels')
            ->get()
            ->keyBy(fn ($subject) => (int) $subject->subject_id);

        foreach ($normalized as $subjectId => $subjectLevelIds) {
            $tutorSubject = $existingTutorSubjects->get($subjectId)
                ?? $profile->tutorSubjects()->create(['subject_id' => $subjectId]);

            $tutorSubject->tutorSubjectLevels()
                ->whereNotIn('subject_level_id', $subjectLevelIds)
                ->delete();

            $existingLevelIds = $tutorSubject->tutorSubjectLevels()
                ->whereIn('subject_level_id', $subjectLevelIds)
                ->pluck('subject_level_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $missingLevelIds = array_values(array_diff($subjectLevelIds, $existingLevelIds));

            if ($missingLevelIds !== []) {
                $now = now();
                TutorSubjectLevel::query()->insert(array_map(
                    fn (int $levelId): array => [
                        'tutor_subject_id' => $tutorSubject->tutor_subject_id,
                        'subject_level_id' => $levelId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $missingLevelIds
                ));
            }
        }

        $profile->tutorSubjects()
            ->when(
                $selectedSubjectIds === [],
                fn ($query) => $query,
                fn ($query) => $query->whereNotIn('subject_id', $selectedSubjectIds)
            )
            ->delete();
    }

    private function apply(
        TutorProfile $profile,
        TutorProfileChangeRequest $change
    ): void {
        $payload = $change->payload ?? [];

        match ($change->change_type) {
            TutorProfileChangeRequest::TYPE_EDUCATION => $profile->update([
                'education_summary' => (string) ($payload['value'] ?? ''),
            ]),
            TutorProfileChangeRequest::TYPE_EXPERIENCE => $profile->update([
                'teaching_experience' => (string) ($payload['value'] ?? ''),
            ]),
            TutorProfileChangeRequest::TYPE_DOCUMENT => $this->applyDocumentChange(
                $profile,
                $change,
                $payload
            ),
            default => throw ValidationException::withMessages([
                'change' => 'Loại thay đổi hồ sơ không được hỗ trợ.',
            ]),
        };
    }

    private function applyDocumentChange(
        TutorProfile $profile,
        TutorProfileChangeRequest $change,
        array $payload
    ): void {
        $document = $profile->documents()
            ->whereKey($change->target_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($change->change_action === TutorProfileChangeRequest::ACTION_ADD) {
            abort_unless($document->verification_status === TutorDocument::STATUS_PENDING, 409);
            $document->forceFill(['verification_status' => TutorDocument::STATUS_APPROVED])->save();

            return;
        }

        abort_unless($document->verification_status === TutorDocument::STATUS_APPROVED, 409);

        if ($change->change_action === TutorProfileChangeRequest::ACTION_DELETE) {
            $oldPath = (string) $document->file_url;
            $document->delete();
            DB::afterCommit(fn () => $this->deletePrivateFile($oldPath, (int) $profile->getKey()));

            return;
        }

        abort_unless($change->change_action === TutorProfileChangeRequest::ACTION_REPLACE, 409);

        $newPath = (string) ($payload['file_url'] ?? '');
        abort_unless($this->isPrivateDocumentPath($newPath, (int) $profile->getKey()), 422);
        $oldPath = (string) $document->file_url;

        $document->forceFill([
            'document_type' => (string) ($payload['document_type'] ?? $document->document_type),
            'document_name' => (string) ($payload['document_name'] ?? $document->document_name),
            'file_url' => $newPath,
            'verification_status' => TutorDocument::STATUS_APPROVED,
            'uploaded_at' => now(),
        ])->save();

        if ($newPath !== $oldPath) {
            DB::afterCommit(fn () => $this->deletePrivateFile($oldPath, (int) $profile->getKey()));
        }
    }

    /** @param array<int, array<int, int>> $specializations */
    private function validateSpecializations(array $specializations): void
    {
        if ($specializations === []) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Hồ sơ gia sư phải có ít nhất một chuyên môn.',
            ]);
        }

        $subjectIds = array_keys($specializations);
        $activeSubjectIds = Subject::query()
            ->whereIn('subject_id', $subjectIds)
            ->where('status', 'ACTIVE')
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (array_diff($subjectIds, $activeSubjectIds) !== []) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Một hoặc nhiều môn học không còn hoạt động.',
            ]);
        }

        $levelIds = collect($specializations)->flatten()->unique()->values();
        $levels = SubjectLevel::query()
            ->whereIn('subject_level_id', $levelIds)
            ->where('status', 'ACTIVE')
            ->get(['subject_level_id', 'subject_id'])
            ->keyBy(fn ($level) => (int) $level->subject_level_id);

        foreach ($specializations as $subjectId => $subjectLevelIds) {
            if ($subjectLevelIds === []) {
                throw ValidationException::withMessages([
                    "subject_levels.$subjectId" => 'Mỗi môn học phải có ít nhất một cấp độ.',
                ]);
            }

            foreach ($subjectLevelIds as $levelId) {
                $level = $levels->get($levelId);

                if (! $level || (int) $level->subject_id !== (int) $subjectId) {
                    throw ValidationException::withMessages([
                        "subject_levels.$subjectId" => 'Cấp độ không thuộc môn học tương ứng.',
                    ]);
                }
            }
        }
    }

    private function recordReview(
        TutorProfile $profile,
        User $reviewer,
        TutorProfileChangeRequest $change,
        string $result,
        mixed $reviewedAt
    ): void {
        TutorProfileReview::query()->create([
            'tutor_profile_id' => $profile->getKey(),
            'reviewer_user_id' => $reviewer->getKey(),
            'review_source' => 'ADMIN',
            'review_result' => $result,
            'notes' => $change->typeLabel().' · '.$change->actionLabel(),
            'reviewed_at' => $reviewedAt,
        ]);
    }

    private function notifyTutor(
        TutorProfile $profile,
        TutorProfileChangeRequest $change,
        string $type,
        string $title,
        string $message
    ): void {
        Notification::query()->create([
            'user_id' => $profile->user_id,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => 'TUTOR_PROFILE_CHANGE',
            'related_id' => $change->getKey(),
        ]);
    }

    private function notifyAdminsWhenReviewIsRequired(
        TutorProfile $profile,
        TutorProfileChangeRequest $change
    ): void {
        if ($change->change_type !== TutorProfileChangeRequest::TYPE_DOCUMENT) {
            return;
        }

        $this->notifications->tutorProfileChangeSubmitted($profile, $change);
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

    private function deletePrivateFile(string $path, int $profileId): void
    {
        if ($this->isPrivateDocumentPath($path, $profileId)) {
            Storage::disk('local')->delete($path);
        }
    }
}
