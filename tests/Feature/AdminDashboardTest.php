<?php

namespace Tests\Feature;

use App\Models\TutorProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow(CarbonImmutable::parse('2026-09-12 12:00:00'));
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_uses_database_counts_statuses_and_only_submitted_pending_profiles(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', true);
        [$subjectId, $subjectLevelId] = $this->createSubject('Toán');

        $newerPendingUser = $this->createUser('Gia sư mới gửi');
        $newerPendingId = $this->createTutor(
            $newerPendingUser->user_id,
            TutorProfile::STATUS_PENDING,
            '2026-09-12 10:00:00',
            'Ba năm hỗ trợ học sinh THPT.'
        );
        $this->assignSubject($newerPendingId, $subjectId);

        $olderPendingUser = $this->createUser('Gia sư gửi trước');
        $olderPendingId = $this->createTutor(
            $olderPendingUser->user_id,
            TutorProfile::STATUS_PENDING,
            '2026-09-11 09:00:00',
            'Hai năm giảng dạy.'
        );
        $this->assignSubject($olderPendingId, $subjectId);

        $draftUser = $this->createUser('Hồ sơ chưa gửi');
        $this->createTutor($draftUser->user_id, TutorProfile::STATUS_PENDING);

        $approvedUser = $this->createUser('Gia sư đã duyệt');
        $approvedTutorId = $this->createTutor(
            $approvedUser->user_id,
            TutorProfile::STATUS_APPROVED,
            '2026-09-10 08:00:00'
        );

        $unsubmittedApprovedUser = $this->createUser('Hồ sơ duyệt chưa gửi');
        $this->createTutor(
            $unsubmittedApprovedUser->user_id,
            TutorProfile::STATUS_APPROVED
        );

        $rejectedUser = $this->createUser('Gia sư bị từ chối');
        $this->createTutor(
            $rejectedUser->user_id,
            TutorProfile::STATUS_REJECTED,
            '2026-09-09 08:00:00'
        );

        $learner = $this->createUser('Người học');
        $requestId = $this->createRequest(
            $learner->user_id,
            $subjectLevelId,
            '2026-09-12 09:00:00'
        );
        $this->createClass(
            $requestId,
            $approvedTutorId,
            '2026-09-12 10:00:00',
            '2026-09-20'
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="admin-breadcrumb admin-dashboard__breadcrumb"', false)
            ->assertSee('aria-label="Xem Người dùng thường"', false)
            ->assertSee('aria-label="Xem Gia sư đã duyệt"', false)
            ->assertSee('aria-label="Xem Yêu cầu học"', false)
            ->assertSee('aria-label="Xem Lớp học"', false)
            ->assertSee(route('admin.tutors.show', $newerPendingId), false)
            ->assertSeeTextInOrder(['Gia sư mới gửi', 'Gia sư gửi trước'])
            ->assertDontSeeText('Hồ sơ chưa gửi')
            ->assertDontSeeText('Gia sư bị từ chối');

        $statistics = collect($response->viewData('statistics'))->keyBy('key');

        $this->assertSame('Người dùng thường', $statistics['users']['label']);
        $this->assertSame('Gia sư đã duyệt', $statistics['tutors']['label']);
        $this->assertSame(7, $statistics['users']['value']);
        $this->assertSame(1, $statistics['tutors']['value']);
        $this->assertSame(1, $statistics['requests']['value']);
        $this->assertSame(1, $statistics['classes']['value']);

        $statusCounts = $response->viewData('tutorStatusBreakdown')
            ->keyBy('status')
            ->map(fn (array $item): int => $item['count']);

        $this->assertSame(1, $statusCounts[TutorProfile::STATUS_APPROVED]);
        $this->assertSame(2, $statusCounts[TutorProfile::STATUS_PENDING]);
        $this->assertSame(1, $statusCounts[TutorProfile::STATUS_REJECTED]);
        $this->assertSame(4, $response->viewData('tutorStatusTotal'));

        $pendingTutors = $response->viewData('pendingTutors');

        $this->assertSame([$newerPendingId, $olderPendingId], $pendingTutors->modelKeys());
        $this->assertTrue($pendingTutors->first()->relationLoaded('user'));
        $this->assertTrue($pendingTutors->first()->relationLoaded('tutorSubjects'));
        $this->assertTrue($pendingTutors->first()->tutorSubjects->first()->relationLoaded('subject'));
    }

    public function test_dashboard_zero_fills_seven_days_and_limits_recent_lists_in_database_order(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', true);
        $tutorUser = $this->createUser('Gia sư hợp đồng');
        $tutorId = $this->createTutor(
            $tutorUser->user_id,
            TutorProfile::STATUS_APPROVED,
            '2026-09-01 08:00:00'
        );
        [, $subjectLevelId] = $this->createSubject('Vật lý');

        $requestIds = [];
        foreach ([
            '2026-09-04 08:00:00',
            '2026-09-06 08:00:00',
            '2026-09-08 08:00:00',
            '2026-09-08 08:00:00',
            '2026-09-12 08:00:00',
        ] as $index => $createdAt) {
            $learner = $this->createUser('Người học '.($index + 1));
            $requestIds[] = $this->createRequest(
                $learner->user_id,
                $subjectLevelId,
                $createdAt,
                $index % 2 === 0 ? 'ONLINE' : 'OFFLINE'
            );
        }

        $olderClassId = $this->createClass(
            $requestIds[0],
            $tutorId,
            '2026-09-03 08:00:00',
            '2026-10-10'
        );
        $seventhClassId = $this->createClass(
            $requestIds[1],
            $tutorId,
            '2026-09-07 08:00:00',
            '2026-09-20'
        );
        $ninthClassId = $this->createClass(
            $requestIds[2],
            $tutorId,
            '2026-09-09 08:00:00',
            '2026-09-22'
        );
        $latestClassId = $this->createClass(
            $requestIds[4],
            $tutorId,
            '2026-09-12 09:00:00',
            '2026-09-25'
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.users.show', $tutorUser), false)
            ->assertSee(route('admin.users.show', $learner), false);

        $activityRows = $response->viewData('activityRows');
        $activityByDate = $activityRows->keyBy('date');

        $this->assertCount(7, $activityRows);
        $this->assertSame('2026-09-06', $activityRows->first()['date']);
        $this->assertSame('2026-09-12', $activityRows->last()['date']);
        $this->assertSame(1, $activityByDate['2026-09-06']['requests']);
        $this->assertSame(0, $activityByDate['2026-09-07']['requests']);
        $this->assertSame(2, $activityByDate['2026-09-08']['requests']);
        $this->assertSame(0, $activityByDate['2026-09-10']['classes']);
        $this->assertSame(1, $activityByDate['2026-09-07']['classes']);
        $this->assertSame(1, $activityByDate['2026-09-09']['classes']);
        $this->assertSame(1, $activityByDate['2026-09-12']['classes']);

        $recentRequests = $response->viewData('recentRequests');
        $recentClasses = $response->viewData('recentClasses');

        $this->assertSame(
            [$requestIds[4], $requestIds[3], $requestIds[2]],
            $recentRequests->modelKeys()
        );
        $this->assertSame(
            [$latestClassId, $ninthClassId, $seventhClassId],
            $recentClasses->modelKeys()
        );
        $this->assertNotContains($olderClassId, $recentClasses->modelKeys());

        $this->assertTrue($recentRequests->first()->relationLoaded('user'));
        $this->assertTrue($recentRequests->first()->relationLoaded('subjectLevel'));
        $this->assertTrue($recentRequests->first()->subjectLevel->relationLoaded('subject'));
        $this->assertTrue($recentClasses->first()->relationLoaded('contract'));
        $this->assertTrue($recentClasses->first()->contract->relationLoaded('tutorProfile'));
        $this->assertTrue($recentClasses->first()->contract->tutorProfile->relationLoaded('user'));
        $this->assertTrue($recentClasses->first()->contract->relationLoaded('tutoringRequest'));
        $this->assertTrue($recentClasses->first()->contract->tutoringRequest->relationLoaded('user'));
        $this->assertTrue($recentClasses->first()->contract->tutoringRequest->relationLoaded('subjectLevel'));
    }

    public function test_dashboard_renders_honest_empty_states_when_business_tables_are_empty(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', true);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeText('Chưa có hoạt động mới trong 7 ngày gần nhất.')
            ->assertSeeText('Chưa có dữ liệu hồ sơ gia sư.')
            ->assertSeeText('Chưa có hồ sơ đang chờ xét duyệt.')
            ->assertSeeText('Chưa có yêu cầu học.')
            ->assertSeeText('Chưa có lớp học.');

        $statistics = collect($response->viewData('statistics'))->keyBy('key');

        $this->assertSame(0, $statistics['users']['value']);
        $this->assertSame(0, $statistics['tutors']['value']);
        $this->assertSame(0, $statistics['requests']['value']);
        $this->assertSame(0, $statistics['classes']['value']);
        $this->assertTrue(
            $response->viewData('activityRows')->every(
                fn (array $row): bool => $row['requests'] === 0 && $row['classes'] === 0
            )
        );
    }

    private function createUser(string $name, bool $isAdmin = false): User
    {
        $this->userSequence++;

        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => 'dashboard-user-'.$this->userSequence.'@example.test',
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createSubject(string $name): array
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'level_name' => 'THPT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$subjectId, $subjectLevelId];
    }

    private function createTutor(
        int $userId,
        string $status,
        ?string $submittedAt = null,
        ?string $experience = null
    ): int {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $userId,
            'teaching_experience' => $experience,
            'approval_status' => $status,
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt ?? now(),
            'updated_at' => $submittedAt ?? now(),
        ]);
    }

    private function assignSubject(int $tutorId, int $subjectId): void
    {
        DB::table('tutor_subjects')->insert([
            'tutor_profile_id' => $tutorId,
            'subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRequest(
        int $userId,
        int $subjectLevelId,
        string $createdAt,
        string $learningMode = 'ONLINE'
    ): int {
        return DB::table('tutoring_requests')->insertGetId([
            'user_id' => $userId,
            'subject_level_id' => $subjectLevelId,
            'request_type' => 'PUBLIC',
            'learning_mode' => $learningMode,
            'status' => 'OPEN',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createClass(
        int $requestId,
        int $tutorId,
        string $createdAt,
        string $startDate
    ): int {
        $contractId = DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorId,
            'learning_mode' => 'ONLINE',
            'status' => 'CONFIRMED',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return DB::table('tutoring_classes')->insertGetId([
            'contract_id' => $contractId,
            'class_name' => null,
            'start_date' => $startDate,
            'end_date' => null,
            'status' => 'ACTIVE',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('google_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->text('teaching_experience')->nullable();
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name', 100);
            $table->timestamps();
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('level_name', 100);
            $table->timestamps();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
        });

        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->string('request_type', 20);
            $table->string('learning_mode', 20);
            $table->string('status', 30);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('learning_mode', 20);
            $table->string('status', 30);
            $table->timestamps();
        });

        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id');
            $table->string('class_name', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();
        });
    }
}
