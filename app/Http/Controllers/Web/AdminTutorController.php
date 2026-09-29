<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\TutorProfileReview;
use App\Services\TutorProfileChangeService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTutorController extends Controller
{
    /**
     * Danh sách chỉ bao gồm hồ sơ đã chính thức gửi xét duyệt.
     */
    public function index(Request $request): View
    {
        $status = $this->allowedValue(
            $request->query('status'),
            ['pending', 'approved', 'rejected', 'changes'],
            'all'
        );
        $mode = $this->allowedValue(
            $request->query('mode'),
            ['online', 'offline', 'both']
        );
        $sort = $this->allowedValue(
            $request->query('sort'),
            ['newest', 'oldest'],
            'newest'
        );
        $search = $this->searchValue($request->query('q'));
        $subjectId = $this->positiveInteger($request->query('subject'));

        $countRow = TutorProfile::query()
            ->whereNotNull('submitted_at')
            ->selectRaw('COUNT(*) as all_count')
            ->selectRaw(
                'SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as pending_count',
                [TutorProfile::STATUS_PENDING]
            )
            ->selectRaw(
                'SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as approved_count',
                [TutorProfile::STATUS_APPROVED]
            )
            ->selectRaw(
                'SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as rejected_count',
                [TutorProfile::STATUS_REJECTED]
            )
            ->first();

        $statusCounts = [
            'all' => (int) ($countRow?->all_count ?? 0),
            'pending' => (int) ($countRow?->pending_count ?? 0),
            'approved' => (int) ($countRow?->approved_count ?? 0),
            'rejected' => (int) ($countRow?->rejected_count ?? 0),
            'changes' => TutorProfileChangeRequest::query()
                ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                ->where('status', TutorProfileChangeRequest::STATUS_PENDING)
                ->whereHas('tutorProfile', fn (Builder $query): Builder => $query
                    ->whereNotNull('submitted_at')
                    ->where('approval_status', TutorProfile::STATUS_APPROVED))
                ->distinct()
                ->count('tutor_profile_id'),
        ];

        $statusMap = [
            'pending' => TutorProfile::STATUS_PENDING,
            'approved' => TutorProfile::STATUS_APPROVED,
            'rejected' => TutorProfile::STATUS_REJECTED,
        ];

        $tutorsQuery = TutorProfile::query()
            ->whereNotNull('submitted_at')
            ->with([
                'user:user_id,full_name,email,avatar_url',
                'tutorSubjects:tutor_subject_id,tutor_profile_id,subject_id',
                'tutorSubjects.subject:subject_id,subject_name',
                'changeRequests' => fn ($query) => $query
                    ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                    ->where('status', TutorProfileChangeRequest::STATUS_PENDING)
                    ->oldest('submitted_at')
                    ->oldest('change_request_id'),
            ])
            ->when(
                isset($statusMap[$status]),
                fn (Builder $query): Builder => $query->where(
                    'approval_status',
                    $statusMap[$status]
                )
            )
            ->when(
                $status === 'changes',
                fn (Builder $query): Builder => $query
                    ->where('approval_status', TutorProfile::STATUS_APPROVED)
                    ->whereHas('changeRequests', fn (Builder $changeQuery): Builder => $changeQuery
                        ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                        ->where('status', TutorProfileChangeRequest::STATUS_PENDING))
            )
            ->when(
                $search !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'user',
                    fn (Builder $userQuery): Builder => $userQuery
                        ->where(function (Builder $identityQuery) use ($search): void {
                            $identityQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                )
            )
            ->when(
                $subjectId !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'tutorSubjects',
                    fn (Builder $subjectQuery): Builder => $subjectQuery->where(
                        'subject_id',
                        $subjectId
                    )
                )
            )
            ->when(
                $mode === 'online',
                fn (Builder $query): Builder => $query->where('supports_online', true)
            )
            ->when(
                $mode === 'offline',
                fn (Builder $query): Builder => $query->where('supports_offline', true)
            )
            ->when(
                $mode === 'both',
                fn (Builder $query): Builder => $query
                    ->where('supports_online', true)
                    ->where('supports_offline', true)
            );

        if ($status === 'changes') {
            $pendingChangeTime = TutorProfileChangeRequest::query()
                ->selectRaw($sort === 'oldest' ? 'MIN(submitted_at)' : 'MAX(submitted_at)')
                ->whereColumn(
                    'tutor_profile_id',
                    'tutor_profiles.tutor_profile_id'
                )
                ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
                ->where('status', TutorProfileChangeRequest::STATUS_PENDING);

            $tutorsQuery
                ->orderBy($pendingChangeTime, $sort === 'oldest' ? 'asc' : 'desc')
                ->orderBy('tutor_profile_id', $sort === 'oldest' ? 'asc' : 'desc');
        } elseif ($sort === 'oldest') {
            $tutorsQuery
                ->orderBy('submitted_at')
                ->orderBy('tutor_profile_id');
        } else {
            $tutorsQuery
                ->orderByDesc('submitted_at')
                ->orderByDesc('tutor_profile_id');
        }

        $filters = array_filter([
            'status' => $status === 'all' ? null : $status,
            'q' => $search,
            'subject' => $subjectId,
            'mode' => $mode,
            'sort' => $sort,
        ], fn ($value): bool => $value !== null && $value !== '');

        $tutors = $tutorsQuery
            ->paginate(10, [
                'tutor_profile_id',
                'user_id',
                'teaching_experience',
                'supports_online',
                'supports_offline',
                'approval_status',
                'submitted_at',
            ])
            ->appends($filters);

        $subjects = Subject::query()
            ->whereHas(
                'tutorSubjects.tutorProfile',
                fn (Builder $query): Builder => $query->whereNotNull('submitted_at')
            )
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);

        return view('admin.tutors.index', compact(
            'filters',
            'mode',
            'search',
            'sort',
            'status',
            'statusCounts',
            'subjectId',
            'subjects',
            'tutors'
        ));
    }

    /**
     * Chỉ hiển thị hồ sơ đã chính thức gửi xét duyệt cho Admin.
     */
    public function show(int $tutorProfile): View
    {
        $tutorProfile = TutorProfile::query()
            ->whereKey($tutorProfile)
            ->whereNotNull('submitted_at')
            ->with([
                'user:user_id,full_name,email,avatar_url',
                'tutorSubjects' => fn ($query) => $query->orderBy('tutor_subject_id'),
                'tutorSubjects.subject:subject_id,subject_name',
                'tutorSubjects.tutorSubjectLevels' => fn ($query) => $query
                    ->orderBy('tutor_subject_level_id'),
                'tutorSubjects.tutorSubjectLevels.subjectLevel:subject_level_id,subject_id,level_name,sort_order',
                'teachingAreas' => fn ($query) => $query->orderBy('teaching_area_id'),
                'teachingAreas.ward:ward_id,province_id,ward_name,ward_type',
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
                    ->where('status', TutorProfileChangeRequest::STATUS_PENDING)
                    ->oldest('submitted_at')
                    ->oldest('change_request_id'),
                'changeRequests.targetDocument',
            ])
            ->firstOrFail();

        $disk = Storage::disk('local');
        $documentAccess = $tutorProfile->documents
            ->mapWithKeys(function (TutorDocument $document) use ($disk, $tutorProfile): array {
                $path = $this->privateDocumentPath(
                    $document,
                    (int) $tutorProfile->tutor_profile_id
                );
                $isAvailable = $path !== null && $disk->exists($path);

                return [
                    (int) $document->document_id => [
                        'available' => $isAvailable,
                        'preview_type' => $isAvailable
                            ? $this->documentPreviewType($path)
                            : null,
                    ],
                ];
            })
            ->all();

        $changeSummaries = $tutorProfile->changeRequests
            ->map(fn (TutorProfileChangeRequest $change): array => $this->changeSummary(
                $change,
                $tutorProfile
            ));
        $changeDocumentAccess = $tutorProfile->changeRequests
            ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
            ->mapWithKeys(function (TutorProfileChangeRequest $change) use ($disk, $tutorProfile): array {
                $path = (string) data_get($change->payload, 'file_url', '');
                $available = $this->isPrivateDocumentPath(
                    $path,
                    (int) $tutorProfile->getKey()
                ) && $disk->exists($path);

                return [
                    (int) $change->getKey() => [
                        'available' => $available,
                        'preview_type' => $available ? $this->documentPreviewType($path) : null,
                    ],
                ];
            });

        return view('admin.tutors.show', [
            'changeDocumentAccess' => $changeDocumentAccess,
            'changeSummaries' => $changeSummaries,
            'documentAccess' => $documentAccess,
            'hasImagePreviews' => collect($documentAccess)
                ->contains(fn (array $access): bool => $access['preview_type'] === 'image'),
            'tutorProfile' => $tutorProfile,
        ]);
    }

    public function approveChange(
        Request $request,
        int $tutorProfile,
        int $changeRequest,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $approved = $changes->approve(
            $tutorProfile,
            $changeRequest,
            $request->user()
        );

        return redirect()
            ->route('admin.tutors.show', $tutorProfile)
            ->with(
                $approved ? 'success' : 'error',
                $approved
                    ? 'Đã duyệt và áp dụng thay đổi hồ sơ.'
                    : 'Thay đổi này đã được xử lý hoặc không còn hợp lệ.'
            );
    }

    public function rejectChange(
        Request $request,
        int $tutorProfile,
        int $changeRequest,
        TutorProfileChangeService $changes
    ): RedirectResponse {
        $validated = $request->validate([
            'change_rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'change_rejection_reason.required' => 'Vui lòng nhập lý do từ chối thay đổi.',
            'change_rejection_reason.min' => 'Lý do từ chối phải có ít nhất 10 ký tự.',
            'change_rejection_reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ]);
        $rejected = $changes->reject(
            $tutorProfile,
            $changeRequest,
            $request->user(),
            trim($validated['change_rejection_reason'])
        );

        return redirect()
            ->route('admin.tutors.show', $tutorProfile)
            ->with(
                $rejected ? 'success' : 'error',
                $rejected
                    ? 'Đã từ chối thay đổi; dữ liệu đang hoạt động được giữ nguyên.'
                    : 'Thay đổi này đã được xử lý hoặc không còn hợp lệ.'
            );
    }

    public function downloadChangeDocument(
        Request $request,
        int $tutorProfile,
        int $changeRequest
    ): StreamedResponse {
        $profile = TutorProfile::query()
            ->whereKey($tutorProfile)
            ->whereNotNull('submitted_at')
            ->firstOrFail();
        $change = $profile->changeRequests()
            ->whereKey($changeRequest)
            ->where('change_type', TutorProfileChangeRequest::TYPE_DOCUMENT)
            ->firstOrFail();
        $path = (string) data_get($change->payload, 'file_url', '');

        abort_unless($this->isPrivateDocumentPath($path, (int) $profile->getKey()), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $document = $change->targetDocument ?? new TutorDocument();
        $document->document_name = (string) data_get(
            $change->payload,
            'document_name',
            'minh-chung-cap-nhat'
        );

        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $name = $this->documentDownloadName($document, $path);

        if ($request->query('disposition') === 'inline') {
            $mime = $this->documentPreviewMimeType($path);
            abort_if($mime === null, 415);

            return Storage::disk('local')->response(
                $path,
                $name,
                [...$headers, 'Content-Type' => $mime],
                'inline'
            );
        }

        return Storage::disk('local')->download($path, $name, $headers);
    }

    public function approve(
        Request $request,
        int $tutorProfile,
        SystemNotificationService $notifications
    ): RedirectResponse
    {
        $wasApproved = DB::transaction(function () use ($request, $tutorProfile, $notifications): bool {
            $profile = $this->submittedProfileForUpdate($tutorProfile);

            if ($profile->approval_status !== TutorProfile::STATUS_PENDING) {
                return false;
            }

            $reviewedAt = now();

            $profile->forceFill([
                'approval_status' => TutorProfile::STATUS_APPROVED,
                'approved_at' => $reviewedAt,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            $profile->documents()
                ->where('verification_status', TutorDocument::STATUS_PENDING)
                ->update([
                    'verification_status' => TutorDocument::STATUS_APPROVED,
                ]);

            TutorProfileReview::query()->create([
                'tutor_profile_id' => $profile->tutor_profile_id,
                'reviewer_user_id' => $request->user()->getKey(),
                'review_source' => 'ADMIN',
                'review_result' => 'PASSED',
                'notes' => null,
                'reviewed_at' => $reviewedAt,
            ]);

            $notifications->tutorProfileApproved($profile);

            return true;
        });

        return redirect()
            ->route('admin.tutors.show', $tutorProfile)
            ->with(
                $wasApproved ? 'success' : 'error',
                $wasApproved
                    ? 'Đã duyệt hồ sơ gia sư thành công.'
                    : 'Hồ sơ gia sư đã được xử lý trước đó.'
            );
    }

    public function reject(
        Request $request,
        int $tutorProfile,
        SystemNotificationService $notifications
    ): RedirectResponse
    {
        $validated = $request->validate(
            [
                'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
            ],
            [
                'rejection_reason.required' => 'Vui lòng nhập lý do từ chối.',
                'rejection_reason.string' => 'Lý do từ chối phải là chuỗi ký tự.',
                'rejection_reason.min' => 'Lý do từ chối phải có ít nhất 10 ký tự.',
                'rejection_reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
            ]
        );

        $reason = trim($validated['rejection_reason']);
        $wasRejected = DB::transaction(function () use ($request, $reason, $tutorProfile, $notifications): bool {
            $profile = $this->submittedProfileForUpdate($tutorProfile);

            if ($profile->approval_status !== TutorProfile::STATUS_PENDING) {
                return false;
            }

            $reviewedAt = now();

            $profile->forceFill([
                'approval_status' => TutorProfile::STATUS_REJECTED,
                'approved_at' => null,
                'rejected_at' => $reviewedAt,
                'rejection_reason' => $reason,
            ])->save();

            TutorProfileReview::query()->create([
                'tutor_profile_id' => $profile->tutor_profile_id,
                'reviewer_user_id' => $request->user()->getKey(),
                'review_source' => 'ADMIN',
                'review_result' => 'REJECTED',
                'notes' => null,
                'reviewed_at' => $reviewedAt,
            ]);

            $notifications->tutorProfileRejected($profile);

            return true;
        });

        return redirect()
            ->route('admin.tutors.show', $tutorProfile)
            ->with(
                $wasRejected ? 'success' : 'error',
                $wasRejected
                    ? 'Đã từ chối hồ sơ gia sư.'
                    : 'Hồ sơ gia sư đã được xử lý trước đó.'
            );
    }

    /**
     * Cho phép Admin đọc minh chứng private thuộc một hồ sơ đã gửi xét duyệt.
     */
    public function downloadDocument(
        Request $request,
        int $tutorProfile,
        int $document
    ): StreamedResponse {
        $tutorProfile = TutorProfile::query()
            ->whereKey($tutorProfile)
            ->whereNotNull('submitted_at')
            ->firstOrFail();

        $document = $tutorProfile->documents()
            ->where('document_id', $document)
            ->firstOrFail();
        $path = $this->privateDocumentPath(
            $document,
            (int) $tutorProfile->tutor_profile_id
        );

        abort_if($path === null, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $downloadName = $this->documentDownloadName($document, $path);
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

        return $disk->download($path, $downloadName, $headers);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedValue(
        mixed $value,
        array $allowed,
        ?string $fallback = null
    ): ?string {
        if (! is_string($value)) {
            return $fallback;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true)
            ? $normalized
            : $fallback;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function searchValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim(mb_substr($value, 0, 100));

        return $search !== '' ? $search : null;
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

    private function changeSummary(
        TutorProfileChangeRequest $change,
        TutorProfile $profile
    ): array {
        $payload = $change->payload ?? [];
        $current = null;
        $proposed = null;

        if ($change->change_type === TutorProfileChangeRequest::TYPE_EDUCATION) {
            $current = $profile->education_summary;
            $proposed = (string) ($payload['value'] ?? '');
        } elseif ($change->change_type === TutorProfileChangeRequest::TYPE_EXPERIENCE) {
            $current = $profile->teaching_experience;
            $proposed = (string) ($payload['value'] ?? '');
        } elseif ($change->change_type === TutorProfileChangeRequest::TYPE_DOCUMENT) {
            $current = $change->targetDocument
                ? trim(implode("\n", array_filter([
                    $change->targetDocument->document_name,
                    $change->targetDocument->typeLabel(),
                ])))
                : 'Chưa có dữ liệu đang hoạt động';
            $proposed = $change->change_action === TutorProfileChangeRequest::ACTION_DELETE
                ? 'Xóa minh chứng khỏi hồ sơ công khai'
                : trim(implode("\n", array_filter([
                    $payload['document_name'] ?? null,
                    TutorDocument::REGISTRATION_TYPE_LABELS[$payload['document_type'] ?? ''] ?? null,
                ])));
        }

        return [
            'change' => $change,
            'current' => filled($current) ? $current : 'Chưa cập nhật',
            'proposed' => filled($proposed) ? $proposed : 'Chưa cập nhật',
        ];
    }

    private function submittedProfileForUpdate(int $tutorProfile): TutorProfile
    {
        return TutorProfile::query()
            ->whereKey($tutorProfile)
            ->whereNotNull('submitted_at')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function documentPreviewType(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'image',
            default => null,
        };
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
}
