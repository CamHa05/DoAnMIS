<?php

namespace Tests\Feature;

use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTutorIndexTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createSchema();
    }

    public function test_admin_can_open_the_submitted_tutor_directory(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', 'admin-tutors@example.test', true);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index'))
            ->assertOk()
            ->assertViewIs('admin.tutors.index')
            ->assertDontSeeText('Quản lý hồ sơ và trạng thái xét duyệt của gia sư')
            ->assertSeeText('Tất cả hồ sơ đã gửi')
            ->assertSee('class="admin-navigation__item is-active"', false);
    }

    public function test_normal_user_is_forbidden_from_the_tutor_directory(): void
    {
        $member = $this->createUser('Thành viên', 'member-tutors@example.test');

        $this->actingAs($member)
            ->get(route('admin.tutors.index'))
            ->assertForbidden();
    }

    public function test_draft_profile_is_not_listed_or_counted_but_submitted_profile_is(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-drafts@example.test', true);
        $draftUser = $this->createUser('Hồ sơ đang làm dở', 'draft@example.test');
        $submittedUser = $this->createUser('Hồ sơ đã gửi', 'submitted@example.test');

        $this->createTutor($draftUser, TutorProfile::STATUS_PENDING, null);
        $this->createTutor($submittedUser, TutorProfile::STATUS_PENDING, '2026-09-10 08:24:00');

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index'))
            ->assertOk()
            ->assertDontSeeText($draftUser->full_name)
            ->assertSeeText($submittedUser->full_name);

        $this->assertSame([
            'all' => 1,
            'pending' => 1,
            'approved' => 0,
            'rejected' => 0,
            'changes' => 0,
        ], $response->viewData('statusCounts'));
    }

    public function test_changes_filter_lists_only_profiles_with_pending_document_changes(): void
    {
        $admin = $this->createUser('Quản trị thay đổi', 'admin-changes@example.test', true);
        $waitingUser = $this->createUser('Gia sư có minh chứng mới', 'waiting-change@example.test');
        $otherUser = $this->createUser('Gia sư không có thay đổi', 'without-change@example.test');
        $waitingTutorId = $this->createTutor(
            $waitingUser,
            TutorProfile::STATUS_APPROVED,
            '2026-09-20 08:00:00'
        );
        $this->createTutor(
            $otherUser,
            TutorProfile::STATUS_APPROVED,
            '2026-09-20 09:00:00'
        );
        DB::table('tutor_profile_change_requests')->insert([
            'tutor_profile_id' => $waitingTutorId,
            'change_type' => TutorProfileChangeRequest::TYPE_DOCUMENT,
            'change_action' => TutorProfileChangeRequest::ACTION_REPLACE,
            'status' => TutorProfileChangeRequest::STATUS_PENDING,
            'payload' => json_encode(['document_name' => 'Chứng chỉ mới']),
            'submitted_at' => '2026-09-21 10:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['status' => 'changes']))
            ->assertOk()
            ->assertSeeText('Minh chứng chờ duyệt')
            ->assertSeeText($waitingUser->full_name)
            ->assertDontSeeText($otherUser->full_name)
            ->assertSeeText('Thay thế')
            ->assertSeeText('Xét duyệt');

        $this->assertSame('changes', $response->viewData('status'));
        $this->assertSame(1, $response->viewData('statusCounts')['changes']);
        $this->assertCount(1, $response->viewData('tutors'));
    }

    public function test_pending_filter_only_lists_submitted_pending_profiles(): void
    {
        $this->assertStatusFilter(
            'pending',
            TutorProfile::STATUS_PENDING,
            'Gia sư chờ duyệt'
        );
    }

    public function test_approved_filter_only_lists_submitted_approved_profiles(): void
    {
        $this->assertStatusFilter(
            'approved',
            TutorProfile::STATUS_APPROVED,
            'Gia sư đã duyệt'
        );
    }

    public function test_rejected_filter_only_lists_submitted_rejected_profiles(): void
    {
        $this->assertStatusFilter(
            'rejected',
            TutorProfile::STATUS_REJECTED,
            'Gia sư bị từ chối'
        );
    }

    public function test_each_approval_status_renders_its_distinct_badge_and_icon(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-badges@example.test', true);
        $statuses = [
            TutorProfile::STATUS_PENDING => ['pending', 'clock'],
            TutorProfile::STATUS_APPROVED => ['approved', 'circle-check'],
            TutorProfile::STATUS_REJECTED => ['rejected', 'x-circle'],
        ];

        foreach ($statuses as $status => [$class, $icon]) {
            $user = $this->createUser('Gia sư '.$status, strtolower($status).'badge@example.test');
            $this->createTutor($user, $status, now()->toDateTimeString());

            $this->actingAs($admin)
                ->get(route('admin.tutors.index', ['status' => $class]))
                ->assertOk()
                ->assertSee('admin-status-badge--'.$class, false)
                ->assertSee('data-status-icon="'.$icon.'"', false);
        }

        $fallbackUser = $this->createUser('Gia sư trạng thái khác', 'fallback-badge@example.test');
        $this->createTutor($fallbackUser, 'REVIEW_NEEDED', now()->toDateTimeString());

        $this->actingAs($admin)
            ->get(route('admin.tutors.index'))
            ->assertOk()
            ->assertSee('admin-status-badge--neutral', false)
            ->assertSee('data-status-icon="info"', false);
    }

    public function test_search_matches_user_name_and_email(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-search@example.test', true);
        $nameMatch = $this->createUser('Lê Thanh An', 'an@example.test');
        $emailMatch = $this->createUser('Nguyễn Bình', 'teacher.lookup@example.test');
        $other = $this->createUser('Phạm Cường', 'other@example.test');

        foreach ([$nameMatch, $emailMatch, $other] as $user) {
            $this->createTutor($user, TutorProfile::STATUS_PENDING, now()->toDateTimeString());
        }

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['q' => 'Thanh An']))
            ->assertOk()
            ->assertSeeText($nameMatch->full_name)
            ->assertDontSeeText($emailMatch->full_name)
            ->assertDontSeeText($other->full_name);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['q' => 'teacher.lookup']))
            ->assertOk()
            ->assertSeeText($emailMatch->full_name)
            ->assertDontSeeText($nameMatch->full_name)
            ->assertDontSeeText($other->full_name);
    }

    public function test_subject_filter_uses_tutor_subject_relationship(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-subject@example.test', true);
        $firstUser = $this->createUser('Gia sư môn Alpha', 'alpha@example.test');
        $secondUser = $this->createUser('Gia sư môn Beta', 'beta@example.test');
        $firstTutorId = $this->createTutor($firstUser, TutorProfile::STATUS_APPROVED, now()->toDateTimeString());
        $secondTutorId = $this->createTutor($secondUser, TutorProfile::STATUS_APPROVED, now()->toDateTimeString());
        $firstSubjectId = $this->createSubject('Môn Alpha');
        $secondSubjectId = $this->createSubject('Môn Beta');
        $this->assignSubject($firstTutorId, $firstSubjectId);
        $this->assignSubject($secondTutorId, $secondSubjectId);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['subject' => $firstSubjectId]))
            ->assertOk()
            ->assertSeeText($firstUser->full_name)
            ->assertDontSeeText($secondUser->full_name)
            ->assertSeeText('Môn Alpha')
            ->assertSeeText('Môn Beta');
    }

    public function test_teaching_mode_filters_follow_the_two_boolean_fields(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-mode@example.test', true);
        $onlineUser = $this->createUser('Chỉ dạy online', 'online@example.test');
        $offlineUser = $this->createUser('Chỉ dạy tại nhà', 'offline@example.test');
        $bothUser = $this->createUser('Dạy cả hai', 'both@example.test');
        $submittedAt = now()->toDateTimeString();

        $this->createTutor($onlineUser, TutorProfile::STATUS_APPROVED, $submittedAt, true, false);
        $this->createTutor($offlineUser, TutorProfile::STATUS_APPROVED, $submittedAt, false, true);
        $this->createTutor($bothUser, TutorProfile::STATUS_APPROVED, $submittedAt, true, true);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['mode' => 'online']))
            ->assertSeeText($onlineUser->full_name)
            ->assertSeeText($bothUser->full_name)
            ->assertDontSeeText($offlineUser->full_name);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['mode' => 'offline']))
            ->assertSeeText($offlineUser->full_name)
            ->assertSeeText($bothUser->full_name)
            ->assertDontSeeText($onlineUser->full_name);

        $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['mode' => 'both']))
            ->assertSeeText($bothUser->full_name)
            ->assertDontSeeText($onlineUser->full_name)
            ->assertDontSeeText($offlineUser->full_name);
    }

    public function test_oldest_sort_uses_submitted_at_in_ascending_order(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-sort@example.test', true);
        $newerUser = $this->createUser('Gia sư gửi sau', 'newer@example.test');
        $olderUser = $this->createUser('Gia sư gửi trước', 'older@example.test');
        $this->createTutor($newerUser, TutorProfile::STATUS_PENDING, '2026-09-10 08:00:00');
        $this->createTutor($olderUser, TutorProfile::STATUS_PENDING, '2026-09-01 08:00:00');

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['sort' => 'oldest']))
            ->assertOk();

        $this->assertSame(
            $olderUser->user_id,
            $response->viewData('tutors')->first()->user_id
        );
    }

    public function test_pagination_is_real_and_keeps_normalized_filter_query(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-page@example.test', true);
        $subjectId = $this->createSubject('Môn phân trang');

        foreach (range(1, 11) as $index) {
            $user = $this->createUser(
                'Gia sư phân trang '.$index,
                'page-'.$index.'@example.test'
            );
            $tutorId = $this->createTutor(
                $user,
                TutorProfile::STATUS_PENDING,
                now()->addMinutes($index)->toDateTimeString(),
                true,
                false
            );
            $this->assignSubject($tutorId, $subjectId);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index', [
                'status' => 'pending',
                'q' => 'Gia sư phân trang',
                'subject' => $subjectId,
                'mode' => 'online',
                'sort' => 'oldest',
            ]))
            ->assertOk();

        $paginator = $response->viewData('tutors');
        $nextPageQuery = [];
        parse_str((string) parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $nextPageQuery);

        $this->assertSame(11, $paginator->total());
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame('pending', $nextPageQuery['status']);
        $this->assertSame('Gia sư phân trang', $nextPageQuery['q']);
        $this->assertSame((string) $subjectId, $nextPageQuery['subject']);
        $this->assertSame('online', $nextPageQuery['mode']);
        $this->assertSame('oldest', $nextPageQuery['sort']);
        $this->assertSame('2', $nextPageQuery['page']);
    }

    public function test_table_relations_are_eager_loaded_without_lazy_queries(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-eager@example.test', true);
        $tutorUser = $this->createUser('Gia sư eager', 'eager@example.test');
        $tutorId = $this->createTutor($tutorUser, TutorProfile::STATUS_APPROVED, now()->toDateTimeString());
        $subjectId = $this->createSubject('Môn eager');
        $this->assignSubject($tutorId, $subjectId);

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($admin)
                ->get(route('admin.tutors.index'))
                ->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $tutor = $response->viewData('tutors')->first();

        $this->assertTrue($tutor->relationLoaded('user'));
        $this->assertTrue($tutor->relationLoaded('tutorSubjects'));
        $this->assertTrue($tutor->tutorSubjects->first()->relationLoaded('subject'));
    }

    public function test_invalid_scalar_and_array_filters_fall_back_safely(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-invalid@example.test', true);
        $submittedUser = $this->createUser('Gia sư hợp lệ', 'valid@example.test');
        $this->createTutor($submittedUser, TutorProfile::STATUS_APPROVED, now()->toDateTimeString());

        $response = $this->actingAs($admin)
            ->get('/admin/tutors?status[]=unknown&mode[]=online&sort[]=oldest&q[]=bad&subject[]=1')
            ->assertOk()
            ->assertSeeText($submittedUser->full_name);

        $this->assertSame('all', $response->viewData('status'));
        $this->assertSame('newest', $response->viewData('sort'));
        $this->assertNull($response->viewData('mode'));
        $this->assertNull($response->viewData('search'));
        $this->assertNull($response->viewData('subjectId'));
    }

    public function test_view_profile_action_links_to_the_admin_tutor_detail_route(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-action@example.test', true);
        $tutorUser = $this->createUser('Gia sư chưa có trang chi tiết', 'no-show@example.test');
        $tutorId = $this->createTutor(
            $tutorUser,
            TutorProfile::STATUS_PENDING,
            now()->toDateTimeString()
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index'))
            ->assertOk()
            ->assertSeeText('Xem hồ sơ')
            ->assertSee(route('admin.tutors.show', $tutorId), false);

        $response->assertDontSee('admin-tutor-view is-disabled', false);
    }

    private function assertStatusFilter(
        string $filter,
        string $expectedStatus,
        string $expectedName
    ): void {
        $admin = $this->createUser('Quản trị '.$filter, 'admin-'.$filter.'@example.test', true);
        $statusNames = [
            TutorProfile::STATUS_PENDING => 'Gia sư chờ duyệt',
            TutorProfile::STATUS_APPROVED => 'Gia sư đã duyệt',
            TutorProfile::STATUS_REJECTED => 'Gia sư bị từ chối',
        ];

        foreach ($statusNames as $status => $name) {
            $user = $this->createUser($name, strtolower($status).'@example.test');
            $this->createTutor($user, $status, now()->toDateTimeString());
        }

        $draft = $this->createUser('Hồ sơ pending chưa gửi', 'draft-'.$filter.'@example.test');
        $this->createTutor($draft, TutorProfile::STATUS_PENDING, null);

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.index', ['status' => $filter]))
            ->assertOk()
            ->assertSeeText($expectedName)
            ->assertDontSeeText($draft->full_name);

        foreach ($statusNames as $status => $name) {
            if ($status !== $expectedStatus) {
                $response->assertDontSeeText($name);
            }
        }

        $this->assertSame($filter, $response->viewData('status'));
        $this->assertCount(1, $response->viewData('tutors'));
    }

    private function createUser(string $name, string $email, bool $isAdmin = false): User
    {
        $this->userSequence++;

        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => $email,
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

    private function createTutor(
        User $user,
        string $status,
        ?string $submittedAt,
        bool $supportsOnline = true,
        bool $supportsOffline = false,
        ?string $experience = 'Kinh nghiệm giảng dạy đã cập nhật.'
    ): int {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->user_id,
            'teaching_experience' => $experience,
            'supports_online' => $supportsOnline,
            'supports_offline' => $supportsOffline,
            'approval_status' => $status,
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt ?? now(),
            'updated_at' => $submittedAt ?? now(),
        ]);
    }

    private function createSubject(string $name): int
    {
        return DB::table('subjects')->insertGetId([
            'subject_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
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
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name', 100);
            $table->timestamps();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
        });

        Schema::create('tutor_profile_change_requests', function (Blueprint $table): void {
            $table->bigIncrements('change_request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('change_type', 40);
            $table->string('change_action', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default(TutorProfileChangeRequest::STATUS_PENDING);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
}
