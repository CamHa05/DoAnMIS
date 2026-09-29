<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTutoringClassTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createSchema();
    }

    public function test_only_admin_can_open_class_directory_and_detail(): void
    {
        $admin = $this->createUser('Quản trị lớp học', true);
        $member = $this->createUser('Người dùng thường');
        $class = $this->createClass(['class_name' => 'Lớp kiểm tra quyền']);

        $this->get(route('admin.classes.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get(route('admin.classes.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('admin.classes.show', $class['class_id']))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertViewIs('admin.classes.index')
            ->assertSeeText('Quản lý lớp học');

        $this->actingAs($admin)
            ->get(route('admin.classes.show', $class['class_id']))
            ->assertOk()
            ->assertViewIs('admin.classes.show')
            ->assertSeeText('Lớp kiểm tra quyền');
    }

    public function test_statistics_use_class_status_without_inferring_from_end_date(): void
    {
        $admin = $this->createUser('Admin thống kê', true);
        $this->createClass([
            'class_name' => 'ACTIVE dù đã qua ngày kết thúc',
            'status' => 'ACTIVE',
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $this->createClass([
            'class_name' => 'ACTIVE không có ngày kết thúc',
            'status' => 'ACTIVE',
            'end_date' => null,
        ]);
        $this->createClass([
            'class_name' => 'COMPLETED dù ngày kết thúc chưa đến',
            'status' => 'COMPLETED',
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.classes.index'))
            ->assertOk();

        $this->assertSame([
            'total' => 3,
            'active' => 2,
            'completed' => 1,
        ], $response->viewData('statistics'));
    }

    public function test_search_and_filters_use_class_relationship_data(): void
    {
        $admin = $this->createUser('Admin bộ lọc', true);
        $online = $this->createClass([
            'class_name' => 'Tiếng Anh giao tiếp',
            'learner_name' => 'Lê Gia Bảo',
            'tutor_name' => 'Lê Thị Phương',
            'subject_name' => 'Tiếng Anh',
            'status' => 'ACTIVE',
            'learning_mode' => 'ONLINE',
        ]);
        $offline = $this->createClass([
            'class_name' => 'Toán nâng cao',
            'learner_name' => 'Đỗ Quang Huy',
            'tutor_name' => 'Trần Minh Quân',
            'subject_name' => 'Toán học',
            'status' => 'COMPLETED',
            'learning_mode' => 'OFFLINE',
        ]);

        foreach (['Tiếng Anh giao tiếp', 'Lê Gia Bảo', 'Lê Thị Phương'] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.classes.index', ['q' => $search]))
                ->assertOk()
                ->assertSeeText('Tiếng Anh giao tiếp')
                ->assertDontSeeText('Toán nâng cao');
        }

        $this->actingAs($admin)
            ->get(route('admin.classes.index', [
                'q' => '#CLS-'.str_pad((string) $offline['class_id'], 3, '0', STR_PAD_LEFT),
            ]))
            ->assertSeeText('Toán nâng cao')
            ->assertDontSeeText('Tiếng Anh giao tiếp');

        $this->actingAs($admin)
            ->get(route('admin.classes.index', ['status' => 'completed']))
            ->assertSeeText('Toán nâng cao')
            ->assertDontSeeText('Tiếng Anh giao tiếp');

        $this->actingAs($admin)
            ->get(route('admin.classes.index', ['mode' => 'online']))
            ->assertSeeText('Tiếng Anh giao tiếp')
            ->assertDontSeeText('Toán nâng cao');

        $this->actingAs($admin)
            ->get(route('admin.classes.index', ['subject' => $online['subject_id']]))
            ->assertSeeText('Tiếng Anh giao tiếp')
            ->assertDontSeeText('Toán nâng cao');
    }

    public function test_directory_shows_relationships_schedule_count_and_detail_link(): void
    {
        $admin = $this->createUser('Admin danh sách', true);
        $class = $this->createClass([
            'class_name' => 'Vật lý lớp 10',
            'learner_name' => 'Phạm Thu Hà',
            'tutor_name' => 'Nguyễn Hoàng Nam',
            'subject_name' => 'Vật lý',
            'level_name' => 'Lớp 10',
            'schedule_count' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSeeText('Vật lý lớp 10')
            ->assertSeeText('Phạm Thu Hà')
            ->assertSeeText('Nguyễn Hoàng Nam')
            ->assertSeeText('Vật lý')
            ->assertSeeText('Lớp 10')
            ->assertSeeText('3 buổi / tuần')
            ->assertSee(route('admin.classes.show', $class['class_id']), false);
    }

    public function test_detail_uses_contract_fee_and_class_schedules_for_online_class(): void
    {
        $admin = $this->createUser('Admin chi tiết online', true);
        $class = $this->createClass([
            'class_name' => 'Lớp online dùng dữ liệu thật',
            'learning_mode' => 'ONLINE',
            'expected_fee' => 999999,
            'agreed_fee' => 180000,
            'agreed_fee_type' => 'HOURLY',
            'payment_method' => 'BANK_TRANSFER',
            'schedule_count' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classes.show', $class['class_id']))
            ->assertOk()
            ->assertSeeText('180.000 VNĐ / giờ')
            ->assertDontSeeText('999.999 VNĐ')
            ->assertSeeText('2 buổi')
            ->assertSeeText('Thứ 2')
            ->assertSeeText('Thứ 3')
            ->assertSeeText('08:00–10:00')
            ->assertSeeText('Chuyển khoản')
            ->assertDontSeeText('Khu vực');
    }

    public function test_offline_detail_shows_ward_and_province_and_business_links(): void
    {
        $admin = $this->createUser('Admin chi tiết offline', true);
        $class = $this->createClass([
            'class_name' => 'Lớp trực tiếp',
            'learning_mode' => 'OFFLINE',
            'ward_name' => 'Phường Bến Nghé',
            'province_name' => 'TP.HCM',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classes.show', $class['class_id']))
            ->assertOk()
            ->assertSeeText('Khu vực')
            ->assertSeeText('Phường Bến Nghé, TP.HCM')
            ->assertSee(route('admin.users.show', $class['learner_id']), false)
            ->assertSee(route('admin.tutors.show', $class['tutor_profile_id']), false)
            ->assertSee(route('admin.requests.show', $class['request_id']), false)
            ->assertSeeText('#C-'.str_pad((string) $class['contract_id'], 3, '0', STR_PAD_LEFT));
    }

    public function test_index_and_detail_eager_load_all_rendered_relationships(): void
    {
        $admin = $this->createUser('Admin eager load', true);
        $class = $this->createClass([
            'class_name' => 'Lớp eager load',
            'learning_mode' => 'OFFLINE',
            'schedule_count' => 2,
        ]);

        Model::preventLazyLoading(true);

        try {
            $indexResponse = $this->actingAs($admin)
                ->get(route('admin.classes.index'))
                ->assertOk();

            $showResponse = $this->actingAs($admin)
                ->get(route('admin.classes.show', $class['class_id']))
                ->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $classItem = $indexResponse->viewData('classes')->first();
        $detailClass = $showResponse->viewData('tutoringClass');

        $this->assertTrue($classItem->relationLoaded('contract'));
        $this->assertTrue($classItem->contract->relationLoaded('tutoringRequest'));
        $this->assertTrue($classItem->contract->relationLoaded('tutorProfile'));
        $this->assertArrayHasKey('schedules_count', $classItem->getAttributes());
        $this->assertTrue($detailClass->relationLoaded('schedules'));
        $this->assertTrue($detailClass->schedules->first()->relationLoaded('timeSlot'));
    }

    public function test_legacy_class_without_name_uses_subject_and_level_on_directory_and_detail(): void
    {
        $admin = $this->createUser('Admin lớp cũ', true);
        $class = $this->createClass([
            'class_name' => '   ',
            'subject_name' => 'Tiếng Anh',
            'level_name' => 'IELTS',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSeeText('Tiếng Anh - IELTS')
            ->assertDontSeeText('Chưa đặt tên');

        $this->actingAs($admin)
            ->get(route('admin.classes.show', $class['class_id']))
            ->assertOk()
            ->assertSeeText('Tiếng Anh - IELTS')
            ->assertDontSeeText('Chưa đặt tên');
    }

    public function test_unknown_class_returns_not_found(): void
    {
        $admin = $this->createUser('Admin 404', true);

        $this->actingAs($admin)
            ->get(route('admin.classes.show', 999999))
            ->assertNotFound();
    }

    /** @return array<string, int> */
    private function createClass(array $attributes = []): array
    {
        $this->sequence++;
        $suffix = $this->sequence;
        $now = now()->addMinutes($suffix);
        $mode = $attributes['learning_mode'] ?? 'ONLINE';

        $learner = $this->createUser($attributes['learner_name'] ?? "Người học {$suffix}");
        $tutor = $this->createUser($attributes['tutor_name'] ?? "Gia sư {$suffix}");
        $tutorProfileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $tutor->getKey(),
            'headline' => "Kinh nghiệm giảng dạy {$suffix}",
            'approval_status' => 'APPROVED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $attributes['subject_name'] ?? "Môn học {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $educationLevelId = DB::table('education_levels')->insertGetId([
            'level_name' => 'Trung học',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => $educationLevelId,
            'level_name' => $attributes['level_name'] ?? "Lớp {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wardId = null;
        if ($mode === 'OFFLINE') {
            $provinceId = DB::table('provinces')->insertGetId([
                'province_name' => $attributes['province_name'] ?? 'Hà Nội',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $wardId = DB::table('wards')->insertGetId([
                'province_id' => $provinceId,
                'ward_name' => $attributes['ward_name'] ?? "Phường {$suffix}",
                'ward_type' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $requestId = DB::table('tutoring_requests')->insertGetId([
            'user_id' => $learner->getKey(),
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'request_type' => 'PUBLIC',
            'learning_mode' => $mode,
            'expected_fee' => $attributes['expected_fee'] ?? 250000,
            'fee_type' => 'HOURLY',
            'status' => 'MATCHED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $contractId = DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
            'agreed_fee' => $attributes['agreed_fee'] ?? 180000,
            'agreed_fee_type' => $attributes['agreed_fee_type'] ?? 'HOURLY',
            'payment_method' => $attributes['payment_method'] ?? 'CASH',
            'learning_mode' => $mode,
            'start_date' => '2026-09-15',
            'end_date' => '2026-12-15',
            'status' => 'CONFIRMED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $classId = DB::table('tutoring_classes')->insertGetId([
            'contract_id' => $contractId,
            'class_name' => array_key_exists('class_name', $attributes)
                ? $attributes['class_name']
                : "Lớp học {$suffix}",
            'start_date' => '2026-09-15',
            'end_date' => array_key_exists('end_date', $attributes)
                ? $attributes['end_date']
                : '2026-12-15',
            'status' => $attributes['status'] ?? 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(1, $attributes['schedule_count'] ?? 1) as $day) {
            $timeSlotId = DB::table('time_slots')->insertGetId([
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('class_schedules')->insert([
                'class_id' => $classId,
                'time_slot_id' => $timeSlotId,
                'day_of_week' => $day,
                'effective_from' => '2026-09-15',
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'class_id' => $classId,
            'contract_id' => $contractId,
            'learner_id' => (int) $learner->getKey(),
            'request_id' => $requestId,
            'subject_id' => $subjectId,
            'tutor_profile_id' => $tutorProfileId,
        ];
    }

    private function createUser(string $name, bool $isAdmin = false): User
    {
        $this->sequence++;

        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => 'admin-class-'.$this->sequence.'@example.test',
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
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline')->nullable();
            $table->string('approval_status')->default('PENDING');
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
        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->string('request_type');
            $table->string('learning_mode');
            $table->decimal('expected_fee', 12, 2)->nullable();
            $table->string('fee_type')->default('HOURLY');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->decimal('agreed_fee', 12, 2);
            $table->string('agreed_fee_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('learning_mode');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id');
            $table->string('class_name')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('ACTIVE');
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
        Schema::create('class_schedules', function (Blueprint $table): void {
            $table->bigIncrements('class_schedule_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }
}
