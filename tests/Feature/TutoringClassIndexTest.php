<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutoringClassIndexTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('classes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_learner_sees_their_class(): void
    {
        $learner = $this->createUser('learner');
        $this->createClassForLearner($learner, ['class_name' => 'Lớp Toán của tôi']);

        Model::preventLazyLoading();

        try {
            $this->actingAs($learner)
                ->get(route('classes.index'))
                ->assertOk()
                ->assertSee('Lớp học của tôi')
                ->assertSee('Lớp Toán của tôi')
                ->assertSee('Thứ Hai')
                ->assertSee('08:00–10:00');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_authenticated_learner_does_not_see_another_users_class(): void
    {
        $learner = $this->createUser('learner');
        $otherLearner = $this->createUser('other-learner');
        $this->createClassForLearner($learner, ['class_name' => 'Lớp thuộc tài khoản hiện tại']);
        $this->createClassForLearner($otherLearner, ['class_name' => 'Lớp riêng của người khác']);

        $this->actingAs($learner)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Lớp thuộc tài khoản hiện tại')
            ->assertDontSee('Lớp riêng của người khác');
    }

    public function test_authenticated_tutor_sees_only_classes_from_their_tutor_profile(): void
    {
        $learner = $this->createUser('learner-for-tutor');
        $tutor = $this->createUser('tutor-owner');
        $otherTutor = $this->createUser('other-tutor');
        $tutorProfileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $tutor->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherTutorProfileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $otherTutor->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createClassForLearner($learner, [
            'class_name' => null,
            'subject_name' => 'Hóa học',
            'level_name' => 'Lớp 11',
            'tutor_profile_id' => $tutorProfileId,
        ]);
        $this->createClassForLearner($learner, [
            'class_name' => 'Lớp thuộc gia sư khác',
            'tutor_profile_id' => $otherTutorProfileId,
        ]);

        $this->actingAs($tutor)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSeeText('Hóa học - Lớp 11')
            ->assertDontSee('Lớp thuộc gia sư khác');
    }

    public function test_class_without_schedule_still_renders(): void
    {
        $learner = $this->createUser('learner');
        $this->createClassForLearner($learner, [
            'class_name' => 'Lớp chưa xếp lịch',
            'with_schedule' => false,
        ]);

        $this->actingAs($learner)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Lớp chưa xếp lịch')
            ->assertSee('Chưa có lịch chính thức');
    }

    public function test_online_class_does_not_require_a_location(): void
    {
        $learner = $this->createUser('learner');
        $this->createClassForLearner($learner, [
            'class_name' => 'Lớp trực tuyến',
            'learning_mode' => 'ONLINE',
            'with_location' => false,
        ]);

        $this->actingAs($learner)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Lớp trực tuyến')
            ->assertSee('Trực tuyến')
            ->assertDontSee('class-card-fact--location', false);
    }

    public function test_legacy_class_without_name_uses_subject_and_level_on_list_and_detail(): void
    {
        $learner = $this->createUser('legacy-class-learner');
        $classId = $this->createClassForLearner($learner, [
            'class_name' => null,
            'subject_name' => 'Địa lý',
            'level_name' => 'Lớp 12',
        ]);

        $this->actingAs($learner)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSeeText('Địa lý - Lớp 12')
            ->assertDontSeeText('Chưa đặt tên');

        $this->actingAs($learner)
            ->get(route('classes.show', $classId))
            ->assertOk()
            ->assertSeeText('Địa lý - Lớp 12')
            ->assertDontSeeText('Chưa đặt tên');
    }

    private function createUser(string $label): User
    {
        $this->sequence++;

        return User::query()->create([
            'google_id' => "google-{$label}-{$this->sequence}",
            'email' => "{$label}-{$this->sequence}@example.com",
            'full_name' => "Người dùng {$label}",
            'avatar_url' => null,
            'phone' => null,
        ]);
    }

    private function createClassForLearner(User $learner, array $attributes = []): int
    {
        $this->sequence++;
        $suffix = $this->sequence;
        $now = now();
        $mode = $attributes['learning_mode'] ?? 'OFFLINE';
        $withLocation = $attributes['with_location'] ?? true;

        $tutorProfileId = $attributes['tutor_profile_id'] ?? null;
        if ($tutorProfileId === null) {
            $tutor = $this->createUser("tutor-{$suffix}");
            $tutorProfileId = DB::table('tutor_profiles')->insertGetId([
                'user_id' => $tutor->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $attributes['subject_name'] ?? "Toán {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $educationLevelId = DB::table('education_levels')->insertGetId([
            'level_name' => "THPT {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => $educationLevelId,
            'level_name' => $attributes['level_name'] ?? "Lớp 10 - {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wardId = null;
        if ($mode === 'OFFLINE' && $withLocation) {
            $provinceId = DB::table('provinces')->insertGetId([
                'province_name' => "Hà Nội {$suffix}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $wardId = DB::table('wards')->insertGetId([
                'province_id' => $provinceId,
                'ward_name' => "Phường Test {$suffix}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $requestId = DB::table('tutoring_requests')->insertGetId([
            'user_id' => $learner->user_id,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'learning_mode' => $mode,
            'status' => 'MATCHED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $contractId = DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
            'agreed_fee' => 180000,
            'learning_mode' => $mode,
            'status' => 'CONFIRMED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $classId = DB::table('tutoring_classes')->insertGetId([
            'contract_id' => $contractId,
            'class_name' => $attributes['class_name'] ?? null,
            'start_date' => '2026-09-01',
            'end_date' => null,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($attributes['with_schedule'] ?? true) {
            $timeSlotId = DB::table('time_slots')->insertGetId([
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('class_schedules')->insert([
                'class_id' => $classId,
                'time_slot_id' => $timeSlotId,
                'day_of_week' => 1,
                'effective_from' => '2026-09-01',
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $classId;
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->bigIncrements('user_id');
                $table->string('google_id')->nullable()->unique();
                $table->string('email')->unique();
                $table->string('full_name', 100);
                $table->string('avatar_url', 500)->nullable();
                $table->string('phone', 20)->nullable();
                $table->boolean('is_admin')->default(false);
                $table->string('status')->default('ACTIVE');
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutor_profiles')) {
            Schema::create('tutor_profiles', function (Blueprint $table): void {
                $table->bigIncrements('tutor_profile_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table): void {
                $table->bigIncrements('subject_id');
                $table->string('subject_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('education_levels')) {
            Schema::create('education_levels', function (Blueprint $table): void {
                $table->bigIncrements('education_level_id');
                $table->string('level_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subject_levels')) {
            Schema::create('subject_levels', function (Blueprint $table): void {
                $table->bigIncrements('subject_level_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('education_level_id')->nullable();
                $table->string('level_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table): void {
                $table->bigIncrements('province_id');
                $table->string('province_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wards')) {
            Schema::create('wards', function (Blueprint $table): void {
                $table->bigIncrements('ward_id');
                $table->unsignedBigInteger('province_id');
                $table->string('ward_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutoring_requests')) {
            Schema::create('tutoring_requests', function (Blueprint $table): void {
                $table->bigIncrements('request_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('subject_level_id');
                $table->unsignedBigInteger('ward_id')->nullable();
                $table->string('learning_mode', 20);
                $table->string('status', 30);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table): void {
                $table->bigIncrements('contract_id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('tutor_profile_id');
                $table->decimal('agreed_fee', 12, 2);
                $table->string('learning_mode', 20);
                $table->string('status', 30);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutoring_classes')) {
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

        if (! Schema::hasTable('time_slots')) {
            Schema::create('time_slots', function (Blueprint $table): void {
                $table->bigIncrements('time_slot_id');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('slot_name', 50)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('request_schedules')) {
            Schema::create('request_schedules', function (Blueprint $table): void {
                $table->bigIncrements('request_schedule_id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('time_slot_id');
                $table->unsignedTinyInteger('day_of_week');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('class_schedules')) {
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
}
