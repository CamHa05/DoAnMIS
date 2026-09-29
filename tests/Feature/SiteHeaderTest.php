<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteHeaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('google_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->bigIncrements('verification_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('document_type', 30)->default('CCCD');
            $table->string('document_number', 50);
            $table->string('front_image_path');
            $table->string('back_image_path');
            $table->string('status', 20)->default(IdentityVerification::STATUS_PENDING);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamps();
        });
    }

    public function test_guest_header_shows_real_routes_and_no_account_identity(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('src="'.asset('images/logo_giasu.webp').'"', false)
            ->assertSee('alt="GiaSu — Kết nối tri thức, nâng tầm học tập"', false)
            ->assertSee('href="'.route('tutors.index').'"', false)
            ->assertSee('href="'.route('requests.index').'"', false)
            ->assertSee('href="'.route('requests.create').'"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('data-mobile-menu-toggle', false)
            ->assertDontSee('data-notification-bell', false)
            ->assertDontSee('data-account-menu', false)
            ->assertDontSee('class="account-menu-name"', false);

        $this->assertFileExists(public_path('images/logo_giasu.webp'));
    }

    public function test_authenticated_tutor_header_keeps_notification_and_account_access(): void
    {
        $user = $this->createUser(fullName: 'Gia sư Nguyễn An');

        DB::table('tutor_profiles')->insert([
            'user_id' => $user->getKey(),
            'approval_status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-notification-bell', false)
            ->assertSee('data-account-menu', false)
            ->assertSeeText('Gia sư Nguyễn An')
            ->assertSee('href="'.route('tutor-area.profile').'"', false)
            ->assertDontSee('class="login-link site-header-control"', false);
    }

    public function test_authenticated_header_reads_name_and_avatar_from_the_user_model(): void
    {
        $user = $this->createUser(
            fullName: 'Trần Minh Anh',
            avatarUrl: 'https://images.example.test/minh-anh.jpg',
        );

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-notification-bell', false)
            ->assertSee('aria-label="Thông báo"', false)
            ->assertDontSee('class="notification-badge"', false)
            ->assertSee('data-account-menu', false)
            ->assertSeeText('Trần Minh Anh')
            ->assertSeeText('minh.anh@example.test')
            ->assertSee('src="https://images.example.test/minh-anh.jpg"', false)
            ->assertSee('href="'.route('tutor-registration.basic.edit').'"', false)
            ->assertSee('href="'.route('requests.create').'"', false)
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertSee('href="'.route('my-requests.index').'"', false)
            ->assertSee('href="'.route('classes.index').'"', false)
            ->assertDontSee('class="login-link site-header-control"', false);
    }

    public function test_unverified_user_header_keeps_the_short_create_request_cta(): void
    {
        $user = $this->createUser(
            fullName: 'Người dùng chưa xác minh',
            identityStatus: null
        );

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Tạo yêu cầu học')
            ->assertDontSeeText('Xác minh danh tính để tạo yêu cầu')
            ->assertSee('href="'.route('requests.create').'"', false)
            ->assertDontSee('href="'.route('identity-verification.show').'"', false);
    }

    public function test_account_dropdown_is_independent_of_identity_review_status(): void
    {
        $user = $this->createUser(
            fullName: 'Người dùng kiểm tra trạng thái',
            identityStatus: IdentityVerification::STATUS_PENDING
        );

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSeeText('Chờ duyệt');

        DB::table('identity_verifications')
            ->where('user_id', $user->getKey())
            ->update(['status' => IdentityVerification::STATUS_VERIFIED]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSeeText('Đã xác minh');

        DB::table('identity_verifications')
            ->where('user_id', $user->getKey())
            ->update(['status' => IdentityVerification::STATUS_REJECTED]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSeeText('Cần cập nhật');
    }

    public function test_admin_header_keeps_public_navigation_but_hides_regular_user_actions(): void
    {
        $admin = $this->createUser(
            fullName: 'Quản trị GiaSu',
            isAdmin: true,
        );

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('tutors.index').'"', false)
            ->assertSee('href="'.route('requests.index').'"', false)
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSeeText('Trang quản trị')
            ->assertSeeText('Đăng xuất')
            ->assertDontSee('href="'.route('tutor-registration.basic.edit').'"', false)
            ->assertDontSee('href="'.route('requests.create').'"', false)
            ->assertDontSee('href="'.route('profile.show').'"', false)
            ->assertDontSee('href="'.route('my-requests.index').'"', false)
            ->assertDontSee('href="'.route('classes.index').'"', false)
            ->assertDontSee('href="#request-options"', false)
            ->assertDontSee('href="#become-tutor"', false)
            ->assertDontSeeText('Hồ sơ của tôi')
            ->assertDontSeeText('Yêu cầu của tôi')
            ->assertDontSeeText('Lớp học của tôi')
            ->assertDontSeeText('Trở thành gia sư');
    }

    public function test_authenticated_header_uses_the_existing_initial_fallback_without_an_avatar(): void
    {
        $user = $this->createUser(fullName: 'Nguyễn Văn An');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Nguyễn Văn An')
            ->assertSee('aria-label="Avatar chữ cái A của Nguyễn Văn An"', false)
            ->assertDontSee('<img class="avatar tutor-avatar-image"', false);
    }

    public function test_header_marks_the_current_navigation_destination(): void
    {
        Route::get('/_header-test/tutors', fn () => Blade::render('<x-site-header />'))
            ->name('tutors.header-test');

        $this->get('/_header-test/tutors')
            ->assertOk()
            ->assertSee('class="site-nav-link site-header-control is-active"', false)
            ->assertSee('aria-current="page"', false);
    }

    private function createUser(
        string $fullName,
        ?string $avatarUrl = null,
        bool $isAdmin = false,
        ?string $identityStatus = IdentityVerification::STATUS_VERIFIED
    ): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => $isAdmin ? 'admin@example.test' : 'minh.anh@example.test',
            'full_name' => $fullName,
            'avatar_url' => $avatarUrl,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail($userId);

        if ($identityStatus !== null) {
            DB::table('identity_verifications')->insert([
                'user_id' => $user->getKey(),
                'document_type' => 'CCCD',
                'document_number' => str_pad((string) $user->getKey(), 12, '0', STR_PAD_LEFT),
                'front_image_path' => 'identity-verifications/test/front.jpg',
                'back_image_path' => 'identity-verifications/test/back.jpg',
                'status' => $identityStatus,
                'submitted_at' => now()->subDay(),
                'verified_at' => $identityStatus === IdentityVerification::STATUS_VERIFIED ? now() : null,
                'verified_by' => null,
                'rejection_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }
}
