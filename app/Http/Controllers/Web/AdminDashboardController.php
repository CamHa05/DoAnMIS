<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Thống kê tổng quan
        |--------------------------------------------------------------------------
        |
        | Người dùng thường:
        | - Không tính tài khoản Admin.
        |
        | Gia sư đã duyệt:
        | - Chỉ tính hồ sơ đã chính thức gửi xét duyệt.
        | - Hồ sơ phải có approval_status = APPROVED.
        | - submitted_at = NULL là hồ sơ đang làm dở và không thuộc phạm vi Admin.
        |
        | Yêu cầu học / Lớp học:
        | - Tổng số record hiện có.
        |
        */
        $statistics = [
            [
                'key' => 'users',
                'label' => 'Người dùng thường',
                'value' => User::query()
                    ->where('is_admin', false)
                    ->count(),
                'icon' => 'users',
                'tone' => 'teal',
            ],
            [
                'key' => 'tutors',
                'label' => 'Gia sư đã duyệt',
                'value' => TutorProfile::query()
                    ->whereNotNull('submitted_at')
                    ->where(
                        'approval_status',
                        TutorProfile::STATUS_APPROVED
                    )
                    ->count(),
                'icon' => 'education',
                'tone' => 'blue',
            ],
            [
                'key' => 'requests',
                'label' => 'Yêu cầu học',
                'value' => TutoringRequest::query()->count(),
                'icon' => 'request',
                'tone' => 'gold',
            ],
            [
                'key' => 'classes',
                'label' => 'Lớp học',
                'value' => TutoringClass::query()->count(),
                'icon' => 'book-open',
                'tone' => 'coral',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Hoạt động hệ thống trong 7 ngày gần nhất
        |--------------------------------------------------------------------------
        |
        | Đếm:
        | - Yêu cầu học mới được tạo.
        | - Lớp học mới được tạo.
        |
        | Dựa trên created_at.
        |
        */
        $activityEnd = CarbonImmutable::today();
        $activityStart = $activityEnd->subDays(6);

        $requestActivity = $this->dailyActivity(
            TutoringRequest::class,
            $activityStart,
            $activityEnd
        );

        $classActivity = $this->dailyActivity(
            TutoringClass::class,
            $activityStart,
            $activityEnd
        );

        $activityRows = collect(range(0, 6))
            ->map(function (int $offset) use (
                $activityStart,
                $requestActivity,
                $classActivity
            ): array {
                $date = $activityStart->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('d/m'),
                    'requests' => $requestActivity->get($dateKey, 0),
                    'classes' => $classActivity->get($dateKey, 0),
                ];
            });

        $activityPeak = max(
            (int) ($activityRows->max('requests') ?? 0),
            (int) ($activityRows->max('classes') ?? 0)
        );

        $activityChartMax = max(
            4,
            (int) ceil($activityPeak / 4) * 4
        );

        /*
        |--------------------------------------------------------------------------
        | Trạng thái hồ sơ gia sư
        |--------------------------------------------------------------------------
        |
        | Chỉ thống kê hồ sơ đã chính thức gửi xét duyệt
        | (submitted_at IS NOT NULL).
        |
        | Khác với card "Gia sư đã duyệt":
        | - Card Gia sư đã duyệt chỉ tính APPROVED.
        | - Donut chart tính các hồ sơ đã gửi theo từng trạng thái.
        |
        | Hồ sơ có submitted_at = NULL vẫn đang làm dở,
        | nên Admin không quản lý và không đưa vào thống kê.
        |
        */
        $statusCounts = TutorProfile::query()
            ->whereNotNull('submitted_at')
            ->selectRaw('approval_status, COUNT(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status')
            ->map(fn ($total): int => (int) $total);

        $statusMeta = [
            TutorProfile::STATUS_APPROVED => [
                'label' => 'Đã duyệt',
                'tone' => 'approved',
            ],
            TutorProfile::STATUS_PENDING => [
                'label' => 'Chờ duyệt',
                'tone' => 'pending',
            ],
            TutorProfile::STATUS_REJECTED => [
                'label' => 'Từ chối',
                'tone' => 'rejected',
            ],
        ];

        /*
         * Luôn tạo đủ 3 trạng thái.
         *
         * Nếu một trạng thái chưa có record thì count = 0.
         * Cách này giúp donut/legend ổn định hơn.
         */
        $tutorStatusBreakdown = collect($statusMeta)
            ->map(function (
                array $meta,
                string $status
            ) use ($statusCounts): array {
                return [
                    'status' => $status,
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'count' => $statusCounts->get($status, 0),
                ];
            })
            ->values();

        /*
         * Nếu DB xuất hiện status ngoài 3 trạng thái chuẩn,
         * vẫn giữ lại để tránh âm thầm bỏ mất dữ liệu.
         */
        $knownStatuses = array_keys($statusMeta);

        $otherStatuses = $statusCounts
            ->except($knownStatuses)
            ->map(function (int $count, string $status): array {
                return [
                    'status' => $status,
                    'label' => $status,
                    'tone' => 'neutral',
                    'count' => $count,
                ];
            })
            ->values();

        $tutorStatusBreakdown = $tutorStatusBreakdown
            ->concat($otherStatuses);

        $tutorStatusTotal = $statusCounts->sum();

        /*
        |--------------------------------------------------------------------------
        | Gia sư chờ xét duyệt
        |--------------------------------------------------------------------------
        |
        | Business rule:
        | - approval_status = PENDING
        | - submitted_at IS NOT NULL
        |
        | submitted_at = NULL là hồ sơ chưa gửi xét duyệt,
        | nên không xuất hiện trong danh sách quản trị.
        | Danh sách được sắp xếp theo ngày gửi mới nhất.
        |
        */
        $pendingTutors = TutorProfile::query()
            ->whereNotNull('submitted_at')
            ->where(
                'approval_status',
                TutorProfile::STATUS_PENDING
            )
            ->with([
                'user:user_id,full_name,avatar_url',

                'tutorSubjects:tutor_subject_id,tutor_profile_id,subject_id',

                'tutorSubjects.subject:subject_id,subject_name',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('tutor_profile_id')
            ->limit(5)
            ->get([
                'tutor_profile_id',
                'user_id',
                'teaching_experience',
                'approval_status',
                'submitted_at',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Yêu cầu học gần đây
        |--------------------------------------------------------------------------
        */
        $recentRequests = TutoringRequest::query()
            ->with([
                'user:user_id,full_name,avatar_url',

                'subjectLevel:subject_level_id,subject_id',

                'subjectLevel.subject:subject_id,subject_name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('request_id')
            ->limit(3)
            ->get([
                'request_id',
                'user_id',
                'subject_level_id',
                'learning_mode',
                'created_at',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Lớp học gần đây
        |--------------------------------------------------------------------------
        |
        | Hiện tại hiểu "gần đây" là các lớp mới được tạo gần nhất.
        |
        | View vẫn có thể hiển thị start_date.
        |
        */
        $recentClasses = TutoringClass::query()
            ->with([
                'contract:contract_id,request_id,tutor_profile_id',

                'contract.tutorProfile:tutor_profile_id,user_id',

                'contract.tutorProfile.user:user_id,full_name,avatar_url',

                'contract.tutoringRequest:request_id,user_id,subject_level_id',

                'contract.tutoringRequest.user:user_id,full_name,avatar_url',

                'contract.tutoringRequest.subjectLevel:subject_level_id,subject_id',

                'contract.tutoringRequest.subjectLevel.subject:subject_id,subject_name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('class_id')
            ->limit(3)
            ->get([
                'class_id',
                'contract_id',
                'start_date',
                'created_at',
            ]);

        return view('admin.index', compact(
            'statistics',
            'activityRows',
            'activityChartMax',
            'tutorStatusBreakdown',
            'tutorStatusTotal',
            'pendingTutors',
            'recentRequests',
            'recentClasses'
        ));
    }

    /**
     * Trả về số lượng record được tạo theo từng ngày.
     *
     * @param  class-string<TutoringRequest|TutoringClass>  $modelClass
     * @return Collection<string, int>
     */
    private function dailyActivity(
        string $modelClass,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        return $modelClass::query()
            ->whereBetween(
                'created_at',
                [
                    $start->startOfDay(),
                    $end->endOfDay(),
                ]
            )
            ->selectRaw(
                'DATE(created_at) as activity_date, COUNT(*) as total'
            )
            ->groupByRaw('DATE(created_at)')
            ->orderBy('activity_date')
            ->pluck('total', 'activity_date')
            ->mapWithKeys(
                fn ($total, $date): array => [
                    (string) $date => (int) $total,
                ]
            );
    }
}
