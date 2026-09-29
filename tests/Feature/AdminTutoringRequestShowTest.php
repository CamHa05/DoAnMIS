<?php

namespace Tests\Feature;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTutoringRequestShowTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createSchema();
    }

    public function test_only_admin_can_open_request_detail(): void
    {
        $admin = $this->createUser('Quản trị viên', true);
        $member = $this->createUser('Người dùng thường');
        $requestId = $this->createRequest($member);

        $this->get(route('admin.requests.show', $requestId))
            ->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get(route('admin.requests.show', $requestId))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $requestId))
            ->assertOk()
            ->assertViewIs('admin.requests.show')
            ->assertSeeText('Chi tiết yêu cầu học')
            ->assertDontSee('<h1>Chi tiết yêu cầu học</h1>', false)
            ->assertSeeInOrder([
                'class="admin-request-detail-back"',
                'class="admin-request-detail-heading__meta"',
            ], false);
    }

    public function test_public_request_shows_all_applications_schedules_and_selected_tutor(): void
    {
        $admin = $this->createUser('Admin PUBLIC', true);
        $learner = $this->createUser('Nguyễn Minh Anh');
        $firstTutorUser = $this->createUser('Trần Minh Quân');
        $selectedTutorUser = $this->createUser('Lê Thị Phương');
        $requestId = $this->createRequest($learner, [
            'status' => 'MATCHED',
            'description' => "Cần học Toán lớp 12.\nMong muốn có lộ trình cụ thể.",
        ]);
        $firstTutorId = $this->createTutor($firstTutorUser, 'Có kinh nghiệm ôn thi THPT');
        $selectedTutorId = $this->createTutor($selectedTutorUser, 'Giáo viên tự do');

        $this->insertTutorApplication($requestId, $firstTutorId, 'REJECTED', 200000);
        $this->insertTutorApplication($requestId, $selectedTutorId, 'ACCEPTED', 180000);

        foreach ([
            [1, '18:00:00', '20:00:00'],
            [2, '18:00:00', '20:00:00'],
            [7, '09:00:00', '11:00:00'],
        ] as [$day, $start, $end]) {
            $this->createSchedule($requestId, $day, $start, $end);
        }

        DB::table('contracts')->insert([
            'request_id' => $requestId,
            'tutor_profile_id' => $selectedTutorId,
            'agreed_fee' => 180000,
            'learning_mode' => 'ONLINE',
            'status' => 'CONFIRMED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Gia sư ứng tuyển (2)')
            ->assertSeeText($firstTutorUser->full_name)
            ->assertSeeText($selectedTutorUser->full_name)
            ->assertSeeText('200.000 VNĐ / giờ')
            ->assertSeeText('180.000 VNĐ / giờ')
            ->assertSeeText('Đã được chọn')
            ->assertSeeText('Thứ 2')
            ->assertSeeText('Thứ 3')
            ->assertSeeText('Chủ nhật')
            ->assertSeeText('18:00–20:00')
            ->assertSeeText('09:00–11:00')
            ->assertSeeText('Cần học Toán lớp 12.')
            ->assertDontSeeText('Đang xét duyệt')
            ->assertDontSeeText('Khu vực');

        $this->assertSame(1, substr_count($response->getContent(), 'Đã được chọn'));
    }

    public function test_direct_request_shows_target_tutor_and_pending_message_without_applications(): void
    {
        $admin = $this->createUser('Admin DIRECT', true);
        $learner = $this->createUser('Người học gửi trực tiếp');
        $tutorUser = $this->createUser('Gia sư nhận yêu cầu');
        $tutorId = $this->createTutor($tutorUser, 'Gia sư Toán THPT');
        $requestId = $this->createRequest($learner, [
            'target_tutor_profile_id' => $tutorId,
            'request_type' => 'DIRECT',
            'status' => 'PENDING',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Gia sư nhận yêu cầu trực tiếp')
            ->assertSeeText($tutorUser->full_name)
            ->assertSeeText('Đang chờ gia sư phản hồi')
            ->assertDontSeeText('Gia sư ứng tuyển')
            ->assertDontSeeText('Số gia sư ứng tuyển');
    }

    public function test_offline_request_shows_ward_and_province(): void
    {
        $admin = $this->createUser('Admin khu vực', true);
        $learner = $this->createUser('Người học Offline');
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => 'Hồ Chí Minh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => 'Phường Bến Nghé',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = $this->createRequest($learner, [
            'learning_mode' => 'OFFLINE',
            'ward_id' => $wardId,
            'address_detail' => '12 đường riêng tư',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Khu vực')
            ->assertSeeText('Phường Bến Nghé, Hồ Chí Minh')
            ->assertDontSeeText('12 đường riêng tư');
    }

    public function test_deadline_copy_uses_status_before_expiration_time(): void
    {
        $admin = $this->createUser('Admin thời hạn', true);
        $learner = $this->createUser('Người học thời hạn');
        $openId = $this->createRequest($learner, [
            'status' => 'OPEN',
            'expires_at' => now()->addDays(3),
        ]);
        $matchedId = $this->createRequest($learner, [
            'status' => 'MATCHED',
            'expires_at' => now()->subDay(),
        ]);
        $expiredId = $this->createRequest($learner, [
            'status' => 'EXPIRED',
            'expires_at' => now()->addDays(5),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $openId))
            ->assertOk()
            ->assertSeeText('Còn 3 ngày')
            ->assertSeeText('(đến '.now()->addDays(3)->format('d/m/Y').')');

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $matchedId))
            ->assertOk()
            ->assertSeeText('Đã kết thúc');

        $this->actingAs($admin)
            ->get(route('admin.requests.show', $expiredId))
            ->assertOk()
            ->assertSeeText('Đã hết hạn');
    }

    public function test_detail_relationships_are_eager_loaded_without_lazy_queries(): void
    {
        $admin = $this->createUser('Admin eager', true);
        $learner = $this->createUser('Người học eager');
        $tutorUser = $this->createUser('Gia sư eager');
        $tutorId = $this->createTutor($tutorUser, 'Headline eager');
        $requestId = $this->createRequest($learner, [
            'target_tutor_profile_id' => $tutorId,
            'request_type' => 'DIRECT',
            'status' => 'PENDING',
        ]);
        $this->createSchedule($requestId, 4, '19:00:00', '21:00:00');

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($admin)
                ->get(route('admin.requests.show', $requestId))
                ->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $request = $response->viewData('tutoringRequest');

        $this->assertTrue($request->relationLoaded('user'));
        $this->assertTrue($request->relationLoaded('subjectLevel'));
        $this->assertTrue($request->subjectLevel->relationLoaded('subject'));
        $this->assertTrue($request->relationLoaded('schedules'));
        $this->assertTrue($request->schedules->first()->relationLoaded('timeSlot'));
        $this->assertTrue($request->relationLoaded('applications'));
        $this->assertTrue($request->relationLoaded('targetTutor'));
        $this->assertTrue($request->targetTutor->relationLoaded('user'));
        $this->assertTrue($request->relationLoaded('contract'));
    }

    public function test_request_directory_links_to_the_correct_detail_route(): void
    {
        $admin = $this->createUser('Admin danh sách', true);
        $learner = $this->createUser('Người học danh sách');
        $requestId = $this->createRequest($learner);

        $this->actingAs($admin)
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee(route('admin.requests.show', $requestId), false);
    }

    private function createUser(string $name, bool $isAdmin = false): User
    {
        $this->sequence++;

        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => 'admin-request-show-'.$this->sequence.'@example.test',
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

    /** @param array<string, mixed> $overrides */
    private function createRequest(User $learner, array $overrides = []): int
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'Toán học '.$this->sequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $educationLevelId = DB::table('education_levels')->insertGetId([
            'level_name' => 'THPT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => $educationLevelId,
            'level_name' => 'Lớp 12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('tutoring_requests')->insertGetId(array_merge([
            'user_id' => $learner->getKey(),
            'target_tutor_profile_id' => null,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => null,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'ANY',
            'address_detail' => null,
            'expected_fee' => 150000,
            'fee_type' => 'HOURLY',
            'description' => 'Mô tả yêu cầu học từ database.',
            'status' => 'OPEN',
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createTutor(User $user, string $headline): int
    {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->getKey(),
            'headline' => $headline,
            'hourly_rate' => 180000,
            'supports_online' => true,
            'supports_offline' => true,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now(),
            'submitted_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTutorApplication(
        int $requestId,
        int $tutorId,
        string $status,
        ?int $proposedFee
    ): void {
        DB::table('tutor_applications')->insert([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorId,
            'message' => null,
            'proposed_fee' => $proposedFee,
            'status' => $status,
            'applied_at' => now()->subMinutes($tutorId),
            'updated_at' => now(),
        ]);
    }

    private function createSchedule(int $requestId, int $day, string $start, string $end): void
    {
        $timeSlotId = DB::table('time_slots')->insertGetId([
            'start_time' => $start,
            'end_time' => $end,
            'slot_name' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_schedules')->insert([
            'request_id' => $requestId,
            'time_slot_id' => $timeSlotId,
            'day_of_week' => $day,
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
            $table->string('full_name');
            $table->string('avatar_url')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default('ACTIVE');
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name');
            $table->timestamps();
        });
        Schema::create('education_levels', function (Blueprint $table): void {
            $table->bigIncrements('education_level_id');
            $table->string('level_name');
            $table->timestamps();
        });
        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name');
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
            $table->string('ward_type')->nullable();
            $table->timestamps();
        });
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status')->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
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
            $table->string('preferred_tutor_gender')->nullable();
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
            $table->string('slot_name')->nullable();
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
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id')->unique();
            $table->unsignedBigInteger('tutor_profile_id');
            $table->decimal('agreed_fee', 12, 2);
            $table->string('learning_mode');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('terms')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id')->unique();
            $table->string('class_name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
    }
}
