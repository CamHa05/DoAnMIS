<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TutoringClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTutoringClassController extends Controller
{
    public function index(Request $request): View
    {
        $search = $this->searchValue($request->query('q'));
        $learningMode = $this->allowedValue(
            $request->query('mode'),
            ['ONLINE', 'OFFLINE']
        );

        $statusOptions = TutoringClass::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn ($status): string => strtoupper(trim((string) $status)))
            ->filter()
            ->prepend('COMPLETED')
            ->prepend('ACTIVE')
            ->unique()
            ->values();

        $status = $this->allowedValue(
            $request->query('status'),
            $statusOptions->all()
        );

        $subjects = Subject::query()
            ->select('subject_id', 'subject_name')
            ->orderBy('subject_name')
            ->get();

        $subjectId = $this->positiveInteger($request->query('subject'));
        if ($subjectId !== null && ! $subjects->contains('subject_id', $subjectId)) {
            $subjectId = null;
        }

        $userId = $this->positiveInteger($request->query('user'));

        $countRow = TutoringClass::query()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count',
                ['ACTIVE']
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count',
                ['COMPLETED']
            )
            ->first();

        $statistics = [
            'total' => (int) ($countRow?->total_count ?? 0),
            'active' => (int) ($countRow?->active_count ?? 0),
            'completed' => (int) ($countRow?->completed_count ?? 0),
        ];

        $formattedClassId = $this->formattedClassId($search);

        $classesQuery = TutoringClass::query()
            ->with([
                'contract.tutoringRequest.user:user_id,full_name,avatar_url',
                'contract.tutoringRequest.subjectLevel:subject_level_id,subject_id,education_level_id,level_name',
                'contract.tutoringRequest.subjectLevel.subject:subject_id,subject_name',
                'contract.tutoringRequest.subjectLevel.educationLevel:education_level_id,level_name',
                'contract.tutoringRequest.ward:ward_id,province_id,ward_name,ward_type',
                'contract.tutoringRequest.ward.province:province_id,province_name',
                'contract.tutorProfile.user:user_id,full_name,avatar_url',
            ])
            ->withCount('schedules')
            ->when(
                $search !== null,
                function (Builder $query) use ($formattedClassId, $search): void {
                    $query->where(function (Builder $searchQuery) use ($formattedClassId, $search): void {
                        if ($formattedClassId !== null) {
                            $searchQuery->whereKey($formattedClassId);

                            return;
                        }

                        $searchQuery
                            ->where('class_name', 'like', "%{$search}%")
                            ->orWhereHas(
                                'contract.tutoringRequest.user',
                                fn (Builder $userQuery): Builder => $userQuery->where(
                                    'full_name',
                                    'like',
                                    "%{$search}%"
                                )
                            )
                            ->orWhereHas(
                                'contract.tutorProfile.user',
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
                $status !== null,
                fn (Builder $query): Builder => $query->where('status', $status)
            )
            ->when(
                $learningMode !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'contract',
                    fn (Builder $contractQuery): Builder => $contractQuery->where(
                        'learning_mode',
                        $learningMode
                    )
                )
            )
            ->when(
                $subjectId !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'contract.tutoringRequest.subjectLevel',
                    fn (Builder $subjectQuery): Builder => $subjectQuery->where(
                        'subject_id',
                        $subjectId
                    )
                )
            )
            ->when(
                $userId !== null,
                function (Builder $query) use ($userId): void {
                    $query->where(function (Builder $userQuery) use ($userId): void {
                        $userQuery
                            ->whereHas(
                                'contract.tutoringRequest',
                                fn (Builder $requestQuery): Builder => $requestQuery->where(
                                    'user_id',
                                    $userId
                                )
                            )
                            ->orWhereHas(
                                'contract.tutorProfile',
                                fn (Builder $tutorQuery): Builder => $tutorQuery->where(
                                    'user_id',
                                    $userId
                                )
                            );
                    });
                }
            )
            ->orderByDesc('created_at')
            ->orderByDesc('class_id');

        $filters = array_filter([
            'q' => $search,
            'status' => $status,
            'mode' => $learningMode,
            'subject' => $subjectId,
            'user' => $userId,
        ], fn ($value): bool => $value !== null && $value !== '');

        $classes = $classesQuery
            ->paginate(10)
            ->appends($filters);

        return view('admin.classes.index', compact(
            'classes',
            'filters',
            'learningMode',
            'search',
            'statistics',
            'status',
            'statusOptions',
            'subjectId',
            'subjects'
        ));
    }

    public function show(TutoringClass $tutoringClass): View
    {
        $tutoringClass->load([
            'contract.tutoringRequest.user:user_id,full_name,email,avatar_url,status',
            'contract.tutoringRequest.subjectLevel:subject_level_id,subject_id,education_level_id,level_name',
            'contract.tutoringRequest.subjectLevel.subject:subject_id,subject_name',
            'contract.tutoringRequest.subjectLevel.educationLevel:education_level_id,level_name',
            'contract.tutoringRequest.ward:ward_id,province_id,ward_name,ward_type',
            'contract.tutoringRequest.ward.province:province_id,province_name',
            'contract.tutorProfile.user:user_id,full_name,avatar_url',
            'schedules' => fn ($query) => $query
                ->with('timeSlot:time_slot_id,start_time,end_time')
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'),
        ]);

        return view('admin.classes.show', compact('tutoringClass'));
    }

    /** @param array<int, string> $allowed */
    private function allowedValue(mixed $value, array $allowed): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

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

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $normalized === false ? null : (int) $normalized;
    }

    private function formattedClassId(?string $search): ?int
    {
        if ($search === null || preg_match('/^#?CLS-(\d+)$/i', $search, $matches) !== 1) {
            return null;
        }

        $classId = (int) $matches[1];

        return $classId > 0 ? $classId : null;
    }
}
