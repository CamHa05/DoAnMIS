<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Services\TutorScheduleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TutorController extends Controller
{
    public function index(Request $request): View
    {
        $subjectId = $request->integer('subject');
        $subjectLevelId = $request->integer('subject_level');
        $provinceId = $request->integer('location');
        $minPrice = $request->integer('min_price');
        $maxPrice = $request->integer('max_price');
        $learningMode = $request->input('learning_mode');
        $sort = $request->input('sort', 'relevance');

        $subjectId = $subjectId > 0 ? $subjectId : null;
        $subjectLevelId = $subjectLevelId > 0 ? $subjectLevelId : null;
        $provinceId = $provinceId > 0 ? $provinceId : null;
        $minPrice = $minPrice > 0 ? $minPrice : null;
        $maxPrice = $maxPrice > 0 ? $maxPrice : null;
        $learningMode = in_array($learningMode, ['online', 'offline'], true)
            ? $learningMode
            : null;
        $sort = in_array($sort, ['relevance', 'newest', 'price_asc', 'price_desc'], true)
            ? $sort
            : 'relevance';

        $subjects = Subject::query()
            ->where('status', 'ACTIVE')
            ->orderBy('subject_name')
            ->get(['subject_id', 'subject_name']);

        $levels = SubjectLevel::query()
            ->where('status', 'ACTIVE')
            ->when(
                $subjectId,
                fn ($query) => $query->where('subject_id', $subjectId)
            )
            ->with('subject:subject_id,subject_name')
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->orderBy('level_name')
            ->get([
                'subject_level_id',
                'subject_id',
                'level_name',
            ]);

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

        $tutors = TutorProfile::query()
            ->where('approval_status', 'APPROVED')
            ->whereHas('user', fn ($query) => $query->where('status', 'ACTIVE'))
            ->when(
                $subjectId,
                fn ($query) => $query->whereHas(
                    'tutorSubjects',
                    fn ($subjectQuery) => $subjectQuery->where('subject_id', $subjectId)
                )
            )
            ->when(
                $subjectLevelId,
                fn ($query) => $query->whereHas(
                    'tutorSubjects.tutorSubjectLevels',
                    fn ($levelQuery) => $levelQuery->where('subject_level_id', $subjectLevelId)
                )
            )
            ->when(
                $provinceId,
                fn ($query) => $query->whereHas(
                    'teachingAreas.ward',
                    fn ($areaQuery) => $areaQuery->where('province_id', $provinceId)
                )
            )
            ->when(
                $learningMode === 'online',
                fn ($query) => $query->where('supports_online', true)
            )
            ->when(
                $learningMode === 'offline',
                fn ($query) => $query->where('supports_offline', true)
            )
            ->when(
                $minPrice,
                fn ($query) => $query->where('hourly_rate', '>=', $minPrice)
            )
            ->when(
                $maxPrice,
                fn ($query) => $query->where('hourly_rate', '<=', $maxPrice)
            )
            ->with([
                'user',
                'tutorSubjects.subject',
                'tutorSubjects.tutorSubjectLevels.subjectLevel',
                'teachingAreas.ward.province',
            ])
            ->when(
                in_array($sort, ['relevance', 'newest'], true),
                fn ($query) => $query->orderByDesc('approved_at')
            )
            ->when(
                $sort === 'price_asc',
                fn ($query) => $query->orderBy('hourly_rate')
            )
            ->when(
                $sort === 'price_desc',
                fn ($query) => $query->orderByDesc('hourly_rate')
            )
            ->paginate(9)
            ->withQueryString();

        return view('tutors.index', compact(
            'tutors',
            'subjects',
            'levels',
            'locations',
            'subjectId',
            'subjectLevelId',
            'provinceId',
            'learningMode',
            'minPrice',
            'maxPrice',
            'sort',
            'selectedLevel'
        ));
    }

    public function show(
        TutorProfile $tutor,
        TutorScheduleAvailabilityService $scheduleAvailability
    ): View
    {
        abort_unless(
            $tutor->approval_status === 'APPROVED'
                && $tutor->user?->status === 'ACTIVE',
            404
        );

        $tutor->load([
            'user',
            'tutorSubjects.subject',
            'tutorSubjects.tutorSubjectLevels.subjectLevel',
            'teachingAreas.ward.province',
            'availabilities.timeSlot',
            'documents' => fn ($query) => $query
                ->where('verification_status', TutorDocument::STATUS_APPROVED)
                ->whereIn('document_type', array_keys(TutorDocument::REGISTRATION_TYPE_LABELS))
                ->orderBy('document_type')
                ->orderBy('document_id'),
        ]);

        $occupiedSlotKeys = $scheduleAvailability->occupiedSlotKeys(
            (int) $tutor->tutor_profile_id
        );
        $availabilityGroups = $tutor->availabilities
            ->filter(fn ($availability) =>
                $availability->is_available
                && $availability->timeSlot
                && ! isset($occupiedSlotKeys[$scheduleAvailability->slotKey(
                    (int) $availability->day_of_week,
                    (int) $availability->time_slot_id
                )])
            )
            ->sortBy(fn ($availability) => sprintf(
                '%02d-%s',
                $availability->day_of_week,
                $availability->timeSlot->start_time
            ))
            ->groupBy('day_of_week');

        return view('tutors.show', [
            'tutor' => $tutor,
            'availabilityGroups' => $availabilityGroups,
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
}
