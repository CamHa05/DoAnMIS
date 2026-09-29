<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicRequestCardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-10 10:00:00');
        $this->createSchema();
        DB::table('users')->insert([
            'google_id' => 'google-public-request-owner',
            'email' => 'public-request-owner@example.test',
            'full_name' => 'Người tạo yêu cầu',
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => false,
            'status' => User::STATUS_ACTIVE,
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_offline_card_uses_database_values_in_the_compact_directory_layout(): void
    {
        $subjectLevelId = $this->createSubjectLevel('Toán', 'Lớp 10');
        $wardId = $this->createWard('Hồ Chí Minh', 'Phường Bến Nghé');
        $description = str_repeat('Cần gia sư hướng dẫn kỹ phần đại số và hình học. ', 8)
            .'Nội dung <script>alert("unsafe")</script> và đoạn kết không được cắt.';
        $requestId = $this->createRequest($subjectLevelId, $wardId, [
            'learning_mode' => 'OFFLINE',
            'preferred_tutor_gender' => 'FEMALE',
            'expected_fee' => 2500000,
            'fee_type' => 'MONTHLY',
            'description' => $description,
            'address_detail' => 'Số 12 đường riêng tư',
            'expires_at' => now()->addDays(6),
            'created_at' => now()->subDay(),
        ]);
        $timeSlotId = $this->createTimeSlot('18:00:00', '20:00:00');

        $this->createSchedule($requestId, $timeSlotId, 1);
        $this->createSchedule($requestId, $timeSlotId, 3);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSeeText('Cần gia sư Toán lớp 10 tại Hồ Chí Minh')
            ->assertSeeText('Tại nhà')
            ->assertSeeText('Phường Bến Nghé, Hồ Chí Minh')
            ->assertSeeText('2 buổi/tuần')
            ->assertSeeText('Nữ')
            ->assertSeeText('2.500.000đ/tháng')
            ->assertSeeText('Còn 6 ngày')
            ->assertSeeText('Đăng 1 ngày trước')
            ->assertSeeText('Xem chi tiết')
            ->assertSeeInOrder([
                'class="request-card-detail-cta"',
                'href="'.route('requests.show', $requestId).'"',
            ], false)
            ->assertDontSee('class="request-status"', false)
            ->assertDontSeeText('T2, T4')
            ->assertDontSeeText('đoạn kết không được cắt.')
            ->assertDontSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSeeText('Số 12 đường riêng tư');
    }

    public function test_online_card_shows_online_title_hourly_fee_and_male_preference(): void
    {
        $subjectLevelId = $this->createSubjectLevel('IELTS', 'Foundation');
        $requestId = $this->createRequest($subjectLevelId, null, [
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'MALE',
            'expected_fee' => 150000,
            'fee_type' => 'HOURLY',
        ]);
        $timeSlotId = $this->createTimeSlot('14:00:00', '16:00:00');

        $this->createSchedule($requestId, $timeSlotId, 7);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSeeText('Cần gia sư IELTS Foundation học Online')
            ->assertSeeText('Trực tuyến')
            ->assertSeeText('1 buổi/tuần')
            ->assertSeeText('Nam')
            ->assertSeeText('150.000đ/giờ')
            ->assertDontSeeText('CN · 14:00–16:00')
            ->assertDontSee('data-kind="location"', false);
    }

    public function test_guest_can_view_public_request_detail_from_database_without_exact_address(): void
    {
        $subjectLevelId = $this->createSubjectLevel('Lập trình', 'Trung cấp');
        $wardId = $this->createWard('Hưng Yên', 'Đồng Bằng');
        $description = 'Cần học thực hành <script> và ôn lại kiến thức nền.';
        $requestId = $this->createRequest($subjectLevelId, $wardId, [
            'learning_mode' => 'OFFLINE',
            'preferred_tutor_gender' => 'MALE',
            'expected_fee' => 1000000,
            'fee_type' => 'HOURLY',
            'description' => $description,
            'address_detail' => 'Số 88, địa chỉ chỉ dành cho gia sư được ghép',
            'expires_at' => now()->addDays(6),
            'created_at' => now()->subDay(),
        ]);
        $morningSlotId = $this->createTimeSlot('08:00:00', '10:00:00');
        $laterSlotId = $this->createTimeSlot('09:00:00', '10:00:00');

        $this->createSchedule($requestId, $morningSlotId, 1);
        $this->createSchedule($requestId, $laterSlotId, 2);

        Model::preventLazyLoading();

        try {
            $this->get(route('requests.show', $requestId))
                ->assertOk()
                ->assertViewHas('tutoringRequest', fn ($request): bool => $request->request_id === $requestId)
                ->assertSeeText('Cần gia sư Lập trình Trung cấp tại Hưng Yên')
                ->assertSeeText('Còn 6 ngày')
                ->assertSeeText('Đăng 1 ngày trước')
                ->assertSeeText('Tại nhà')
                ->assertSeeText('Đồng Bằng, Hưng Yên')
                ->assertSeeText('Nam')
                ->assertSeeText('1.000.000 VNĐ/giờ')
                ->assertSeeText('Lịch học mong muốn')
                ->assertSeeText('Thứ 2')
                ->assertSeeText('08:00–10:00')
                ->assertSeeText('Thứ 3')
                ->assertSeeText($description)
                ->assertSee('Cần học thực hành &lt;script&gt; và ôn lại kiến thức nền.', false)
                ->assertSeeText('Ứng tuyển ngay')
                ->assertSeeText('Bạn cần đăng nhập để tiếp tục ứng tuyển.')
                ->assertDontSeeText('Số 88, địa chỉ chỉ dành cho gia sư được ghép');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_direct_request_cannot_be_viewed_through_the_public_detail_route(): void
    {
        $subjectLevelId = $this->createSubjectLevel('Toán', 'Lớp 7');
        $requestId = $this->createRequest($subjectLevelId, null, [
            'request_type' => 'DIRECT',
            'status' => 'PENDING',
        ]);

        $this->get(route('requests.show', $requestId))->assertNotFound();
    }

    public function test_admin_can_view_public_request_detail_without_an_application_action(): void
    {
        $admin = $this->createAdmin();
        $subjectLevelId = $this->createSubjectLevel('Hóa học', 'Lớp 11');
        $requestId = $this->createRequest($subjectLevelId, null);

        $this->actingAs($admin)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Cần gia sư Hóa học lớp 11 học Online')
            ->assertDontSee('class="request-detail-application"', false)
            ->assertDontSeeText('Ứng tuyển ngay');
    }

    public function test_directory_only_lists_current_public_open_requests_maps_any_gender_and_does_not_lazy_load(): void
    {
        $subjectLevelId = $this->createSubjectLevel('Ngữ văn', 'Lớp 8');
        $visibleRequestId = $this->createRequest($subjectLevelId, null, [
            'description' => 'Yêu cầu PUBLIC OPEN hợp lệ',
            'preferred_tutor_gender' => 'ANY',
        ]);
        $offlineSubjectLevelId = $this->createSubjectLevel('Vật lý', 'Lớp 9');
        $offlineWardId = $this->createWard('Đà Nẵng', 'Phường Hải Châu');
        $secondVisibleRequestId = $this->createRequest($offlineSubjectLevelId, $offlineWardId, [
            'description' => 'Yêu cầu PUBLIC OPEN thứ hai',
            'learning_mode' => 'OFFLINE',
            'preferred_tutor_gender' => 'ANY',
        ]);
        $timeSlotId = $this->createTimeSlot('08:00:00', '10:00:00');

        $this->createSchedule($visibleRequestId, $timeSlotId, 2);
        $this->createSchedule($secondVisibleRequestId, $timeSlotId, 4);
        $matchedRequestId = $this->createRequest($subjectLevelId, null, [
            'description' => 'Yêu cầu đã ghép không được hiện',
            'status' => 'MATCHED',
            'expires_at' => now()->subMinute(),
        ]);
        $this->createRequest($subjectLevelId, null, [
            'description' => 'Yêu cầu DIRECT không được hiện',
            'request_type' => 'DIRECT',
        ]);
        $expiredRequestId = $this->createRequest($subjectLevelId, null, [
            'description' => 'Yêu cầu hết hạn không được hiện',
            'expires_at' => now()->subMinute(),
        ]);

        Model::preventLazyLoading();

        try {
            $this->get(route('requests.index'))
                ->assertOk()
                ->assertViewHas('publicRequests', function ($requests) use ($visibleRequestId, $secondVisibleRequestId): bool {
                    $actualIds = $requests->getCollection()
                        ->pluck('request_id')
                        ->sort()
                        ->values()
                        ->all();
                    $expectedIds = collect([$visibleRequestId, $secondVisibleRequestId])
                        ->sort()
                        ->values()
                        ->all();

                    return $actualIds === $expectedIds && $requests->total() === 2;
                })
                ->assertSeeText('Cần gia sư Ngữ văn lớp 8 học Online')
                ->assertSeeText('Cần gia sư Vật lý lớp 9 tại Đà Nẵng')
                ->assertDontSeeText('Yêu cầu PUBLIC OPEN hợp lệ')
                ->assertDontSeeText('Yêu cầu PUBLIC OPEN thứ hai')
                ->assertDontSeeText('Yêu cầu đã ghép không được hiện')
                ->assertDontSeeText('Yêu cầu DIRECT không được hiện')
                ->assertDontSeeText('Yêu cầu hết hạn không được hiện')
                ->assertSeeText('Không yêu cầu');
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $expiredRequestId,
            'status' => 'EXPIRED',
        ]);
        $this->assertDatabaseHas('request_status_history', [
            'request_id' => $expiredRequestId,
            'initiated_by_user_id' => null,
            'old_status' => 'OPEN',
            'new_status' => 'EXPIRED',
            'reason' => 'Yêu cầu đã hết thời hạn xử lý.',
        ]);
        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $matchedRequestId,
            'status' => 'MATCHED',
        ]);

        $this->get(route('requests.index'))->assertOk();

        $this->assertSame(
            1,
            DB::table('request_status_history')
                ->where('request_id', $expiredRequestId)
                ->where('new_status', 'EXPIRED')
                ->count()
        );
    }

    public function test_open_request_from_disabled_owner_is_kept_but_hidden_from_new_applications(): void
    {
        $disabledOwnerId = DB::table('users')->insertGetId([
            'google_id' => 'google-disabled-request-owner',
            'email' => 'disabled-request-owner@example.test',
            'full_name' => 'Người dùng đã bị vô hiệu hóa',
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => false,
            'status' => User::STATUS_DISABLED,
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectLevelId = $this->createSubjectLevel('Sinh học', 'Lớp 12');
        $requestId = $this->createRequest($subjectLevelId, null, [
            'user_id' => $disabledOwnerId,
            'description' => 'Yêu cầu của tài khoản bị vô hiệu hóa',
        ]);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertViewHas(
                'publicRequests',
                fn ($requests): bool => ! $requests->getCollection()->contains('request_id', $requestId)
            )
            ->assertDontSeeText('Cần gia sư Sinh học lớp 12 học Online');

        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $requestId,
            'status' => 'OPEN',
        ]);
    }

    private function createSubjectLevel(string $subjectName, string $levelName): int
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $subjectName,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => $levelName,
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWard(string $provinceName, string $wardName): int
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => $provinceName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => $wardName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRequest(int $subjectLevelId, ?int $wardId, array $attributes = []): int
    {
        return DB::table('tutoring_requests')->insertGetId(array_merge([
            'user_id' => 1,
            'target_tutor_profile_id' => null,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'ANY',
            'address_detail' => null,
            'expected_fee' => 180000,
            'fee_type' => 'HOURLY',
            'description' => 'Mô tả yêu cầu học',
            'status' => 'OPEN',
            'expires_at' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function createTimeSlot(string $startTime, string $endTime): int
    {
        return DB::table('time_slots')->insertGetId([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchedule(int $requestId, int $timeSlotId, int $dayOfWeek): void
    {
        DB::table('request_schedules')->insert([
            'request_id' => $requestId,
            'time_slot_id' => $timeSlotId,
            'day_of_week' => $dayOfWeek,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAdmin(): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => 'google-public-request-admin',
            'email' => 'public-request-admin@example.test',
            'full_name' => 'Quản trị GiaSu',
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => true,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
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
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id')->unique();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('education_levels', function (Blueprint $table): void {
            $table->bigIncrements('education_level_id');
            $table->string('level_name');
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name');
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_name');
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_name');
            $table->timestamps();
        });

        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_tutor_profile_id')->nullable();
            $table->unsignedBigInteger('subject_level_id');
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->string('request_type');
            $table->string('learning_mode');
            $table->string('preferred_tutor_gender')->default('ANY');
            $table->string('address_detail')->nullable();
            $table->decimal('expected_fee', 12, 2)->nullable();
            $table->string('fee_type')->default('HOURLY');
            $table->text('description')->nullable();
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('time_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('request_schedules', function (Blueprint $table): void {
            $table->bigIncrements('request_schedule_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();
        });

        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->text('message')->nullable();
            $table->decimal('proposed_fee', 12, 2)->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamp('applied_at');
            $table->timestamp('updated_at');
        });

        Schema::create('request_status_history', function (Blueprint $table): void {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->string('reason')->nullable();
            $table->timestamp('changed_at');
        });
    }
}
