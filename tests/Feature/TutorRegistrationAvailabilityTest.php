<?php

namespace Tests\Feature;

use App\Models\TimeSlot;
use App\Models\TutorAvailability;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TutorRegistrationAvailabilityTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_cannot_view_or_save_availability(): void
    {
        $this->get(route('tutor-registration.availability.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('tutor-registration.availability.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_must_complete_basic_information_before_using_step_four(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('tutor-registration.availability.edit'))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [])
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, TutorAvailability::query()->count());
    }

    public function test_step_four_loads_only_active_time_slots_in_database_order(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $lateSlot = $this->createTimeSlot('14:00', '16:00', 'Buổi chiều');
        $earlySlot = $this->createTimeSlot('06:00', '08:00');
        $middleSlot = $this->createTimeSlot('10:00', '12:00', 'Buổi sáng');
        $inactiveSlot = $this->createTimeSlot('08:00', '10:00', 'Tạm ngưng', 'INACTIVE');

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.availability.edit'))
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSeeText('Lịch rảnh')
            ->assertSeeInOrder([
                '06:00 – 08:00',
                $middleSlot->slot_name,
                $lateSlot->slot_name,
            ])
            ->assertDontSee('value="'.$inactiveSlot->time_slot_id.'"', false)
            ->assertDontSeeText($inactiveSlot->slot_name)
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="tutor_profile_id"', false)
            ->assertDontSee('name="is_available"', false)
            ->assertDontSee('name="approval_status"', false);

        $this->assertStringContainsString(
            'value="'.$earlySlot->time_slot_id.'"',
            $response->getContent()
        );
        $this->assertSame(21, substr_count($response->getContent(), 'data-availability-checkbox'));
    }

    public function test_step_four_shows_an_empty_state_when_no_active_time_slot_exists(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $this->createTimeSlot('08:00', '10:00', 'Tạm ngưng', 'INACTIVE');

        $this->actingAs($user)
            ->get(route('tutor-registration.availability.edit'))
            ->assertOk()
            ->assertSeeText('Chưa có khung giờ khả dụng')
            ->assertDontSee('data-availability-checkbox', false);
    }

    public function test_get_prefills_true_rows_and_does_not_prefill_false_rows(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $trueSlot = $this->createTimeSlot('08:00', '10:00');
        $falseSlot = $this->createTimeSlot('10:00', '12:00');
        $this->createAvailability($profile, 1, $trueSlot, true);
        $this->createAvailability($profile, 1, $falseSlot, false);

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.availability.edit'))
            ->assertOk();

        $this->assertAvailabilityChecked($response, 1, $trueSlot->time_slot_id);
        $this->assertAvailabilityNotChecked($response, 1, $falseSlot->time_slot_id);
    }

    public function test_old_input_takes_priority_over_database_selection(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $databaseSlot = $this->createTimeSlot('08:00', '10:00');
        $oldInputSlot = $this->createTimeSlot('10:00', '12:00');
        $this->createAvailability($profile, 1, $databaseSlot, true);

        $response = $this->actingAs($user)
            ->withSession([
                '_old_input' => [
                    'availability' => [2 => [$oldInputSlot->time_slot_id]],
                ],
            ])
            ->get(route('tutor-registration.availability.edit'))
            ->assertOk();

        $this->assertAvailabilityNotChecked($response, 1, $databaseSlot->time_slot_id);
        $this->assertAvailabilityChecked($response, 2, $oldInputSlot->time_slot_id);
    }

    public function test_empty_old_input_takes_priority_over_database_selection(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $slot = $this->createTimeSlot('08:00', '10:00');
        $this->createAvailability($profile, 1, $slot, true);

        $response = $this->actingAs($user)
            ->withSession(['_old_input' => ['availability' => []]])
            ->get(route('tutor-registration.availability.edit'))
            ->assertOk()
            ->assertSeeText('Đã chọn 0 khung giờ');

        $this->assertAvailabilityNotChecked($response, 1, $slot->time_slot_id);
    }

    public function test_user_can_save_one_day_and_one_time_slot(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $slot = $this->createTimeSlot('08:00', '10:00');

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [1 => [$slot->time_slot_id]],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionHas('success', 'Đã lưu lịch rảnh');

        $this->assertDatabaseHas('tutor_availabilities', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => 1,
            'time_slot_id' => $slot->time_slot_id,
            'is_available' => true,
        ]);
    }

    public function test_user_can_save_multiple_days_and_reuse_a_time_slot_across_days(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $morningSlot = $this->createTimeSlot('08:00', '10:00');
        $afternoonSlot = $this->createTimeSlot('14:00', '16:00');

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [
                    1 => [$morningSlot->time_slot_id, $afternoonSlot->time_slot_id],
                    2 => [$morningSlot->time_slot_id],
                    7 => [$afternoonSlot->time_slot_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(4, $profile->availabilities()->where('is_available', true)->count());
        $this->assertDatabaseHas('tutor_availabilities', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => 2,
            'time_slot_id' => $morningSlot->time_slot_id,
            'is_available' => true,
        ]);
    }

    public function test_empty_availability_is_accepted_and_deactivates_old_true_rows(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $firstSlot = $this->createTimeSlot('08:00', '10:00');
        $secondSlot = $this->createTimeSlot('10:00', '12:00');
        $firstAvailability = $this->createAvailability($profile, 1, $firstSlot, true);
        $secondAvailability = $this->createAvailability($profile, 2, $secondSlot, true);

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $firstAvailability->availability_id,
            'is_available' => false,
        ]);
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $secondAvailability->availability_id,
            'is_available' => false,
        ]);
        $this->assertSame(2, TutorAvailability::query()->count());
    }

    public function test_unchanged_selection_preserves_existing_row_id(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $slot = $this->createTimeSlot('08:00', '10:00');
        $availability = $this->createAvailability($profile, 1, $slot, true);

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [1 => [$slot->time_slot_id]],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $availability->availability_id,
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => 1,
            'time_slot_id' => $slot->time_slot_id,
            'is_available' => true,
        ]);
        $this->assertSame(1, TutorAvailability::query()->count());
    }

    public function test_sync_adds_new_reactivates_false_and_deactivates_deselected_rows(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $retainedSlot = $this->createTimeSlot('08:00', '10:00');
        $reactivatedSlot = $this->createTimeSlot('10:00', '12:00');
        $deselectedSlot = $this->createTimeSlot('14:00', '16:00');
        $newSlot = $this->createTimeSlot('16:00', '18:00');
        $retained = $this->createAvailability($profile, 1, $retainedSlot, true);
        $reactivated = $this->createAvailability($profile, 2, $reactivatedSlot, false);
        $deselected = $this->createAvailability($profile, 3, $deselectedSlot, true);

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [
                    1 => [$retainedSlot->time_slot_id, $newSlot->time_slot_id],
                    2 => [$reactivatedSlot->time_slot_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $retained->availability_id,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $reactivated->availability_id,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $deselected->availability_id,
            'is_available' => false,
        ]);
        $this->assertDatabaseHas('tutor_availabilities', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => 1,
            'time_slot_id' => $newSlot->time_slot_id,
            'is_available' => true,
        ]);
        $this->assertSame(4, TutorAvailability::query()->count());
    }

    public function test_validation_rejects_day_outside_supported_range(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $slot = $this->createTimeSlot('08:00', '10:00');

        $this->actingAs($user)
            ->from(route('tutor-registration.availability.edit'))
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [8 => [$slot->time_slot_id]],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHasErrors('availability');

        $this->assertSame(0, TutorAvailability::query()->count());
    }

    public function test_validation_rejects_nonexistent_and_inactive_time_slots(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $inactiveSlot = $this->createTimeSlot('08:00', '10:00', null, 'INACTIVE');

        $this->actingAs($user)
            ->from(route('tutor-registration.availability.edit'))
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [1 => [999999]],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHasErrors('availability.1.0');

        $this->actingAs($user)
            ->from(route('tutor-registration.availability.edit'))
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [1 => [$inactiveSlot->time_slot_id]],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHasErrors('availability.1.0');

        $this->assertSame(0, TutorAvailability::query()->count());
    }

    public function test_validation_rejects_duplicate_time_slot_within_the_same_day(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $slot = $this->createTimeSlot('08:00', '10:00');

        $this->actingAs($user)
            ->from(route('tutor-registration.availability.edit'))
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [
                    1 => [$slot->time_slot_id, $slot->time_slot_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHasErrors('availability.1.0');

        $this->assertSame(0, TutorAvailability::query()->count());
    }

    public function test_step_four_only_updates_current_profile_and_preserves_previous_step_data(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user, [
            'supports_online' => true,
            'supports_offline' => true,
        ]);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        $slot = $this->createTimeSlot('08:00', '10:00');
        $otherAvailability = $this->createAvailability($otherProfile, 2, $slot, true);
        $teachingAreaId = DB::table('tutor_teaching_areas')->insertGetId([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'ward_id' => 501,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => 601,
            'description' => 'Chuyên môn đã lưu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tutorSubjectLevelId = DB::table('tutor_subject_levels')->insertGetId([
            'tutor_subject_id' => $tutorSubjectId,
            'subject_level_id' => 701,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'user_id' => $otherUser->user_id,
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'availability_id' => $otherAvailability->availability_id,
                'is_available' => false,
                'approval_status' => 'REJECTED',
                'approved_at' => now()->addYear()->toDateTimeString(),
                'availability' => [1 => [$slot->time_slot_id]],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $otherAvailability->availability_id,
            'tutor_profile_id' => $otherProfile->tutor_profile_id,
            'day_of_week' => 2,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('tutor_availabilities', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => 1,
            'time_slot_id' => $slot->time_slot_id,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('tutor_teaching_areas', ['teaching_area_id' => $teachingAreaId]);
        $this->assertDatabaseHas('tutor_subjects', [
            'tutor_subject_id' => $tutorSubjectId,
            'description' => 'Chuyên môn đã lưu',
        ]);
        $this->assertDatabaseHas('tutor_subject_levels', [
            'tutor_subject_level_id' => $tutorSubjectLevelId,
            'subject_level_id' => 701,
        ]);

        $profile->refresh();
        $this->assertSame('Giới thiệu Step 1', $profile->bio);
        $this->assertSame('Học vấn Step 1', $profile->education_summary);
        $this->assertSame('Kinh nghiệm Step 1', $profile->teaching_experience);
        $this->assertSame('200000.00', $profile->hourly_rate);
        $this->assertTrue($profile->supports_online);
        $this->assertTrue($profile->supports_offline);
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertNull($profile->approved_at);
    }

    public function test_step_three_save_redirects_to_step_four(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_online' => '1',
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHas('success', 'Đã lưu hình thức & khu vực');

        $this->assertTrue($profile->refresh()->supports_online);
    }

    public function test_step_four_save_redirects_to_step_five_with_success_message(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);

        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), [
                'availability' => [],
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionHas('success', 'Đã lưu lịch rảnh');
    }

    private function assertAvailabilityChecked(TestResponse $response, int $dayOfWeek, int $timeSlotId): void
    {
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*name="availability\['.$dayOfWeek.'\]\[\]")(?=[^>]*value="'.$timeSlotId.'")(?=[^>]*checked)[^>]*>/s',
            $response->getContent()
        );
    }

    private function assertAvailabilityNotChecked(TestResponse $response, int $dayOfWeek, int $timeSlotId): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<input(?=[^>]*name="availability\['.$dayOfWeek.'\]\[\]")(?=[^>]*value="'.$timeSlotId.'")(?=[^>]*checked)[^>]*>/s',
            $response->getContent()
        );
    }

    private function createUser(): User
    {
        $this->userSequence++;

        return User::query()->create([
            'google_id' => "google-availability-{$this->userSequence}",
            'email' => "availability-{$this->userSequence}@example.com",
            'full_name' => "Gia sư {$this->userSequence}",
            'avatar_url' => null,
            'phone' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTutorProfile(User $user, array $attributes = []): TutorProfile
    {
        $profile = TutorProfile::query()->create(array_merge([
            'user_id' => $user->user_id,
            'headline' => 'Gia sư tận tâm',
            'bio' => 'Giới thiệu Step 1',
            'education_summary' => 'Học vấn Step 1',
            'teaching_experience' => 'Kinh nghiệm Step 1',
            'hourly_rate' => 200000,
            'supports_online' => false,
            'supports_offline' => false,
        ], array_intersect_key($attributes, array_flip([
            'supports_online',
            'supports_offline',
        ]))));

        $profile->approval_status = $attributes['approval_status'] ?? 'PENDING';
        $profile->approved_at = $attributes['approved_at'] ?? null;
        $profile->save();

        return $profile;
    }

    private function createTimeSlot(
        string $startTime,
        string $endTime,
        ?string $slotName = null,
        string $status = 'ACTIVE'
    ): TimeSlot {
        return TimeSlot::query()->create([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'slot_name' => $slotName,
            'status' => $status,
        ]);
    }

    private function createAvailability(
        TutorProfile $profile,
        int $dayOfWeek,
        TimeSlot $timeSlot,
        bool $isAvailable
    ): TutorAvailability {
        return TutorAvailability::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'day_of_week' => $dayOfWeek,
            'time_slot_id' => $timeSlot->time_slot_id,
            'is_available' => $isAvailable,
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
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('headline', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('education_summary', 500)->nullable();
            $table->text('teaching_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('time_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('slot_name', 50)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['start_time', 'end_time']);
        });

        Schema::create('tutor_availabilities', function (Blueprint $table): void {
            $table->bigIncrements('availability_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'day_of_week', 'time_slot_id']);
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('time_slot_id')->references('time_slot_id')->on('time_slots')->restrictOnDelete();
        });

        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'ward_id']);
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
        });

        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();

            $table->foreign('tutor_subject_id')->references('tutor_subject_id')->on('tutor_subjects')->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
}
