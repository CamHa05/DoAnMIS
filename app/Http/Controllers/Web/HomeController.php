<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Subject;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $marketplaceTables = [
            'subjects',
            'tutor_subjects',
            'tutor_profiles',
            'users',
            'tutor_subject_levels',
            'subject_levels',
            'tutor_teaching_areas',
            'wards',
            'provinces',
            'education_levels',
            'tutoring_requests',
        ];

        // Toàn bộ môn học dùng cho ô tìm kiếm
        $subjects = collect();

        // 8 môn nổi bật dùng cho section "Môn học phổ biến"
        $popularSubjects = collect();

        $featuredTutors = collect();
        $learningRequests = collect();
        $locations = collect();

        $marketplaceSchemaIsAvailable = ! app()->environment('testing')
            || collect($marketplaceTables)
                ->every(fn ($table) => Schema::hasTable($table));

        if ($marketplaceSchemaIsAvailable) {
            /*
        |--------------------------------------------------------------------------
        | 1. Toàn bộ môn đang hoạt động
        |--------------------------------------------------------------------------
        | Dùng cho Search Panel.
        */
            $subjects = Subject::query()
                ->where('status', 'ACTIVE')
                ->orderBy('subject_name')
                ->get();

            /*
        |--------------------------------------------------------------------------
        | 2. Môn học phổ biến
        |--------------------------------------------------------------------------
        | Ưu tiên môn có nhiều gia sư APPROVED hơn.
        */
            $popularSubjects = Subject::query()
                ->where('status', 'ACTIVE')
                ->withCount([
                    'tutorSubjects as approved_tutors_count' => fn ($query) => $query
                        ->whereHas(
                            'tutorProfile',
                            fn ($tutorQuery) => $tutorQuery
                                ->where('approval_status', 'APPROVED')
                                ->whereHas(
                                    'user',
                                    fn ($userQuery) => $userQuery
                                        ->where('status', 'ACTIVE')
                                )
                        ),
                ])
                ->orderByDesc('approved_tutors_count')
                ->orderBy('subject_name')
                ->take(8)
                ->get();

            /*
        |--------------------------------------------------------------------------
        | 3. Gia sư nổi bật
        |--------------------------------------------------------------------------
        */
            $featuredTutors = TutorProfile::query()
                ->where('approval_status', 'APPROVED')
                ->whereHas(
                    'user',
                    fn ($query) => $query->where('status', 'ACTIVE')
                )

                // Lọc theo môn học
                ->when(
                    $request->integer('subject'),
                    function ($query, $subjectId) {
                        $query->whereHas(
                            'tutorSubjects',
                            fn ($subjectQuery) => $subjectQuery
                                ->where('subject_id', $subjectId)
                        );
                    }
                )

                // Lọc theo tỉnh/thành khi học tại nhà
                ->when(
                    $request->filled('location')
                        && $request->input('location') !== 'online',
                    function ($query) use ($request) {
                        $query
                            ->where('supports_offline', true)
                            ->whereHas(
                                'teachingAreas.ward.province',
                                fn ($provinceQuery) => $provinceQuery
                                    ->where(
                                        'province_id',
                                        $request->integer('location')
                                    )
                            );
                    }
                )

                // Học online
                ->when(
                    $request->input('location') === 'online',
                    fn ($query) => $query->where('supports_online', true)
                )

                ->with([
                    'user',
                    'tutorSubjects.subject',
                    'tutorSubjects.tutorSubjectLevels.subjectLevel',
                    'teachingAreas.ward.province',
                ])
                ->orderByDesc('approved_at')
                ->take(6)
                ->get();

            /*
        |--------------------------------------------------------------------------
        | 4. Yêu cầu tìm gia sư công khai
        |--------------------------------------------------------------------------
        */
            $learningRequests = TutoringRequest::query()
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
                ->with([
                    'subjectLevel:subject_level_id,subject_id,level_name',
                    'subjectLevel.subject:subject_id,subject_name',
                    'ward:ward_id,province_id,ward_name',
                    'ward.province:province_id,province_name',
                    'schedules:request_schedule_id,request_id,time_slot_id,day_of_week',
                    'schedules.timeSlot:time_slot_id,start_time,end_time',
                ])
                ->latest('created_at')
                ->take(4)
                ->get();

            /*
        |--------------------------------------------------------------------------
        | 5. Danh sách tỉnh/thành
        |--------------------------------------------------------------------------
        | Không load toàn bộ 3.321 wards lên homepage.
        */
            $locations = Province::query()
                ->whereHas('wards')
                ->orderBy('province_name')
                ->get([
                    'province_id',
                    'province_name',
                ]);
        }

        return view('home.index', compact(
            'subjects',
            'popularSubjects',
            'featuredTutors',
            'learningRequests',
            'locations'
        ));
    }
}
