<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createSchema();
    }

    public function test_only_admin_can_open_user_management_pages(): void
    {
        $admin = $this->createUser('Quản trị viên', 'admin@example.test', true);
        $member = $this->createUser('Người dùng thường', 'member@example.test');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index');

        $this->actingAs($admin)
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertViewIs('admin.users.show');

        $this->actingAs($member)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_index_uses_real_statistics_and_eager_loaded_relationships(): void
    {
        $admin = $this->createUser('Admin', 'stats-admin@example.test', true);
        $approved = $this->createUser('Gia sư đã duyệt', 'approved@example.test');
        $inactive = $this->createUser(
            'Tài khoản tạm dừng',
            'inactive@example.test',
            false,
            'INACTIVE'
        );

        $this->createTutor($approved, TutorProfile::STATUS_APPROVED);
        $this->createVerification($approved, IdentityVerification::STATUS_VERIFIED);
        $this->createVerification($inactive, IdentityVerification::STATUS_PENDING);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText('Tổng tài khoản');

        $this->assertSame([
            'total' => 3,
            'active' => 2,
            'with_tutor_profile' => 1,
            'identity_verified' => 1,
        ], $response->viewData('statistics'));

        $listedUser = $response->viewData('users')
            ->getCollection()
            ->firstWhere('user_id', $approved->getKey());

        $this->assertNotNull($listedUser);
        $this->assertTrue($listedUser->relationLoaded('tutorProfile'));
        $this->assertTrue($listedUser->relationLoaded('identityVerification'));
    }

    public function test_search_and_all_supported_filters_work_and_pagination_keeps_query(): void
    {
        $admin = $this->createUser('Admin bộ lọc', 'filters-admin@example.test', true);
        $pending = $this->createUser(
            'Nguyễn Minh Anh',
            'minhanh@example.test',
            false,
            'ACTIVE',
            '0987654321'
        );
        $approved = $this->createUser('Trần Văn Nam', 'nam@example.test');
        $inactive = $this->createUser(
            'Lê Thu Hà',
            'hathu@example.test',
            false,
            'INACTIVE'
        );

        $this->createTutor($pending, TutorProfile::STATUS_PENDING);
        $this->createTutor($approved, TutorProfile::STATUS_APPROVED);

        foreach (['Nguyễn Minh', 'minhanh@example', '098765'] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.users.index', ['search' => $search]))
                ->assertOk()
                ->assertSeeText($pending->full_name)
                ->assertDontSeeText($approved->full_name);
        }

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'account_type' => 'user',
                'status' => 'inactive',
                'tutor_status' => 'none',
            ]))
            ->assertOk()
            ->assertSeeText($inactive->full_name)
            ->assertDontSeeText($pending->full_name);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['tutor_status' => 'approved']))
            ->assertOk()
            ->assertSeeText($approved->full_name)
            ->assertDontSeeText($pending->full_name);

        for ($index = 1; $index <= 12; $index++) {
            $this->createUser(
                'Người phân trang '.$index,
                'pagination-'.$index.'@example.test'
            );
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index', ['account_type' => 'user']))
            ->assertOk();

        $users = $response->viewData('users');
        $this->assertSame(10, $users->perPage());
        $this->assertStringContainsString(
            'account_type=user',
            (string) $users->nextPageUrl()
        );
    }

    public function test_invalid_array_filters_are_ignored_safely(): void
    {
        $admin = $this->createUser('Admin', 'invalid-admin@example.test', true);
        $member = $this->createUser('Người dùng hợp lệ', 'valid@example.test');

        $response = $this->actingAs($admin)
            ->get('/admin/users?search[]=bad&account_type[]=admin&status[]=active&tutor_status[]=none')
            ->assertOk()
            ->assertSeeText($member->full_name);

        $this->assertNull($response->viewData('search'));
        $this->assertNull($response->viewData('accountType'));
        $this->assertNull($response->viewData('status'));
        $this->assertNull($response->viewData('tutorStatus'));
    }

    public function test_detail_counts_requests_applications_and_distinct_related_classes(): void
    {
        $admin = $this->createUser('Admin', 'detail-admin@example.test', true);
        $target = $this->createUser(
            'Nguyễn Minh Anh',
            'target@example.test',
            false,
            'ACTIVE',
            null,
            null
        );
        $otherLearner = $this->createUser('Học viên khác', 'other@example.test');
        $profileId = $this->createTutor($target, TutorProfile::STATUS_APPROVED, [
            'headline' => 'Gia sư Toán THPT',
            'hourly_rate' => 200000,
            'supports_online' => true,
            'supports_offline' => true,
        ]);
        $verificationId = $this->createVerification(
            $target,
            IdentityVerification::STATUS_VERIFIED
        );

        $ownedRequestId = $this->createRequest($target);
        $otherRequestId = $this->createRequest($otherLearner);

        DB::table('tutor_applications')->insert([
            ['request_id' => $otherRequestId, 'tutor_profile_id' => $profileId],
            ['request_id' => $ownedRequestId, 'tutor_profile_id' => $profileId],
        ]);

        $firstContractId = $this->createContract($ownedRequestId, $profileId);
        $secondContractId = $this->createContract($otherRequestId, $profileId);
        $this->createClass($firstContractId, 'Lớp trùng hai vai trò');
        $this->createClass($secondContractId, 'Lớp theo vai trò gia sư');

        $response = $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSeeText('Chưa cập nhật')
            ->assertSeeText('Chưa có')
            ->assertSeeText('200.000đ / giờ')
            ->assertSeeText('Online, Offline')
            ->assertSeeText('Vô hiệu hóa tài khoản')
            ->assertSee(route('admin.tutors.show', $profileId), false)
            ->assertSee(
                route('admin.identity-verifications.show', $verificationId),
                false
            );

        $detailUser = $response->viewData('user');
        $this->assertSame(1, $detailUser->tutoring_requests_count);
        $this->assertSame(2, $detailUser->tutorProfile->applications_count);
        $this->assertSame(2, $response->viewData('relatedClassCount'));
    }

    public function test_detail_renders_empty_states_and_inactive_action_without_fake_data(): void
    {
        $admin = $this->createUser('Admin', 'empty-admin@example.test', true);
        $inactive = $this->createUser(
            'Người chưa bổ sung hồ sơ',
            'empty@example.test',
            false,
            'INACTIVE',
            null,
            null
        );

        $this->actingAs($admin)
            ->get(route('admin.users.show', $inactive))
            ->assertOk()
            ->assertSeeText('Người dùng chưa đăng ký trở thành gia sư.')
            ->assertSeeText('Người dùng chưa gửi yêu cầu xác minh danh tính.')
            ->assertSeeText('Kích hoạt lại tài khoản')
            ->assertDontSeeText('Xem hồ sơ gia sư')
            ->assertDontSeeText('Xem thông tin xác minh');
    }

    public function test_admin_can_disable_account_without_changing_or_deleting_related_data(): void
    {
        $admin = $this->createUser('Admin trạng thái', 'status-admin@example.test', true);
        $target = $this->createUser('Nguyễn Thị Lan', 'disable-target@example.test');
        $otherLearner = $this->createUser('Người học khác', 'disable-owner@example.test');
        $profileId = $this->createTutor($target, TutorProfile::STATUS_APPROVED);
        $requestId = $this->createRequest($target);
        $otherRequestId = $this->createRequest($otherLearner);

        DB::table('tutor_applications')->insert([
            'request_id' => $otherRequestId,
            'tutor_profile_id' => $profileId,
        ]);
        $contractId = $this->createContract($requestId, $profileId);
        $this->createClass($contractId, 'Lớp học được giữ nguyên');

        $countsBefore = [
            'users' => DB::table('users')->count(),
            'profiles' => DB::table('tutor_profiles')->count(),
            'requests' => DB::table('tutoring_requests')->count(),
            'applications' => DB::table('tutor_applications')->count(),
            'contracts' => DB::table('contracts')->count(),
            'classes' => DB::table('tutoring_classes')->count(),
        ];

        $this->actingAs($admin)
            ->patch(route('admin.users.disable', $target))
            ->assertRedirect(route('admin.users.show', $target))
            ->assertSessionHas('success', 'Tài khoản đã bị vô hiệu hóa.');

        $this->assertSame(User::STATUS_DISABLED, $target->refresh()->status);
        $this->assertSame(
            TutorProfile::STATUS_APPROVED,
            DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->value('approval_status')
        );
        $this->assertSame($countsBefore['users'], DB::table('users')->count());
        $this->assertSame($countsBefore['profiles'], DB::table('tutor_profiles')->count());
        $this->assertSame($countsBefore['requests'], DB::table('tutoring_requests')->count());
        $this->assertSame($countsBefore['applications'], DB::table('tutor_applications')->count());
        $this->assertSame($countsBefore['contracts'], DB::table('contracts')->count());
        $this->assertSame($countsBefore['classes'], DB::table('tutoring_classes')->count());

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertViewIs('admin.users.show')
            ->assertSeeText('Tài khoản đã bị vô hiệu hóa')
            ->assertSeeText('Kích hoạt lại tài khoản')
            ->assertSee('data-mode="activate"', false)
            ->assertSee(route('admin.users.activate', $target), false);
    }

    public function test_admin_can_reactivate_account_without_changing_related_data(): void
    {
        $admin = $this->createUser('Admin kích hoạt', 'activate-admin@example.test', true);
        $target = $this->createUser(
            'Tài khoản bị khóa',
            'activate-target@example.test',
            false,
            User::STATUS_DISABLED
        );
        $profileId = $this->createTutor($target, TutorProfile::STATUS_APPROVED);
        $requestId = $this->createRequest($target);

        $this->actingAs($admin)
            ->patch(route('admin.users.activate', $target))
            ->assertRedirect(route('admin.users.show', $target))
            ->assertSessionHas('success', 'Tài khoản đã được kích hoạt lại.');

        $this->assertSame(User::STATUS_ACTIVE, $target->refresh()->status);
        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $profileId,
            'approval_status' => TutorProfile::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('tutoring_requests', ['request_id' => $requestId]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSeeText('Vô hiệu hóa tài khoản')
            ->assertSee('data-mode="disable"', false)
            ->assertSee(route('admin.users.disable', $target), false);
    }

    public function test_admin_cannot_disable_self_and_regular_user_cannot_change_account_status(): void
    {
        $admin = $this->createUser('Admin tự bảo vệ', 'self-admin@example.test', true);
        $member = $this->createUser('Người dùng thường', 'status-member@example.test');
        $target = $this->createUser('Tài khoản mục tiêu', 'status-target@example.test');

        $this->actingAs($admin)
            ->patch(route('admin.users.disable', $admin))
            ->assertForbidden();
        $this->assertSame(User::STATUS_ACTIVE, $admin->refresh()->status);

        $this->actingAs($member)
            ->patch(route('admin.users.disable', $target))
            ->assertForbidden();
        $this->actingAs($member)
            ->patch(route('admin.users.activate', $target))
            ->assertForbidden();

        $this->assertSame(User::STATUS_ACTIVE, $target->refresh()->status);
    }

    public function test_self_detail_disables_self_disable_control_but_backend_remains_authoritative(): void
    {
        $admin = $this->createUser('Admin hiện tại', 'current-admin@example.test', true);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $admin))
            ->assertOk()
            ->assertSeeText('Bạn không thể vô hiệu hóa tài khoản quản trị của chính mình.')
            ->assertDontSee('data-admin-user-status-dialog', false);
    }

    private function createUser(
        string $name,
        string $email,
        bool $isAdmin = false,
        string $status = 'ACTIVE',
        ?string $phone = null,
        ?string $lastLogin = '2026-09-20 08:30:00'
    ): User {
        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => $email,
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => $phone,
            'is_admin' => $isAdmin,
            'status' => $status,
            'last_login' => $lastLogin,
            'created_at' => '2026-09-12 09:00:00',
            'updated_at' => '2026-09-12 09:00:00',
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createTutor(
        User $user,
        string $status,
        array $attributes = []
    ): int {
        return DB::table('tutor_profiles')->insertGetId(array_merge([
            'user_id' => $user->getKey(),
            'headline' => null,
            'hourly_rate' => null,
            'supports_online' => false,
            'supports_offline' => false,
            'approval_status' => $status,
            'submitted_at' => '2026-09-14 10:00:00',
            'created_at' => '2026-09-14 10:00:00',
            'updated_at' => '2026-09-14 10:00:00',
        ], $attributes));
    }

    private function createVerification(User $user, string $status): int
    {
        return DB::table('identity_verifications')->insertGetId([
            'user_id' => $user->getKey(),
            'document_type' => 'CCCD',
            'status' => $status,
            'submitted_at' => '2026-09-14 10:00:00',
            'verified_at' => $status === IdentityVerification::STATUS_VERIFIED
                ? '2026-09-15 11:00:00'
                : null,
            'created_at' => '2026-09-14 10:00:00',
            'updated_at' => '2026-09-14 10:00:00',
        ]);
    }

    private function createRequest(User $user): int
    {
        return DB::table('tutoring_requests')->insertGetId([
            'user_id' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createContract(int $requestId, int $tutorProfileId): int
    {
        return DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createClass(int $contractId, string $name): void
    {
        DB::table('tutoring_classes')->insert([
            'contract_id' => $contractId,
            'class_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('google_id')->nullable();
            $table->string('email')->unique();
            $table->string('full_name');
            $table->string('avatar_url')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default('ACTIVE');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status')->default(TutorProfile::STATUS_PENDING);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->bigIncrements('verification_id');
            $table->unsignedBigInteger('user_id');
            $table->string('document_type');
            $table->string('status')->default(IdentityVerification::STATUS_PENDING);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->timestamps();
        });

        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id');
            $table->string('class_name')->nullable();
            $table->timestamps();
        });
    }
}
