<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\TutorProfile;
use App\Models\TutorTeachingArea;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorRegistrationTeachingPreferencesTest extends TestCase
{
    private int $userSequence = 0;

    private int $locationSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_cannot_view_or_save_teaching_preferences(): void
    {
        $this->get(route('tutor-registration.teaching-preferences.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('tutor-registration.teaching-preferences.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_must_complete_basic_information_before_using_step_three(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('tutor-registration.teaching-preferences.edit'))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_online' => '1',
            ])
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, TutorTeachingArea::query()->count());
    }

    public function test_step_three_loads_database_locations_and_existing_multi_province_selections(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user, [
            'supports_online' => true,
            'supports_offline' => true,
        ]);
        [$provinceA, $wardA] = $this->createProvinceWithWard('Hà Nội', 'Phường Ba Đình');
        [$provinceB, $wardB] = $this->createProvinceWithWard('Đà Nẵng', 'Phường Hải Châu');
        $emptyProvince = Province::query()->create([
            'province_code' => 'EMPTY',
            'province_name' => 'Tỉnh chưa có phường xã',
        ]);

        $this->createTeachingArea($profile, $wardA);
        $this->createTeachingArea($profile, $wardB);

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.teaching-preferences.edit'))
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSeeText('Hình thức & khu vực')
            ->assertSeeText($provinceA->province_name)
            ->assertSeeText($provinceB->province_name)
            ->assertSeeText($wardA->ward_name)
            ->assertSeeText($wardB->ward_name)
            ->assertDontSeeText($emptyProvince->province_name)
            ->assertSee('name="supports_online"', false)
            ->assertSee('name="supports_offline"', false)
            ->assertSee('name="ward_ids[]"', false)
            ->assertSee('data-ward-id="'.$wardA->ward_id.'"', false)
            ->assertSee('data-ward-id="'.$wardB->ward_id.'"', false)
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="tutor_profile_id"', false)
            ->assertDontSee('name="approval_status"', false);

        $this->assertMatchesRegularExpression(
            '/name="supports_online"[^>]*checked/s',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/name="supports_offline"[^>]*checked/s',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/name="ward_ids\[\]"[^>]*value="'.$wardA->ward_id.'"[^>]*checked/s',
            $response->getContent()
        );
    }

    public function test_user_can_save_online_only_and_old_teaching_areas_are_removed(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user, [
            'supports_offline' => true,
        ]);
        [, $ward] = $this->createProvinceWithWard('Cần Thơ', 'Phường Ninh Kiều');
        $this->createTeachingArea($profile, $ward);

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_online' => '1',
                'ward_ids' => [$ward->ward_id],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHas('success', 'Đã lưu hình thức & khu vực');

        $profile->refresh();
        $this->assertTrue($profile->supports_online);
        $this->assertFalse($profile->supports_offline);
        $this->assertSame(0, $profile->teachingAreas()->count());
    }

    public function test_user_can_save_offline_only_with_wards_from_multiple_provinces(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user, [
            'supports_online' => true,
        ]);
        [$provinceA, $wardA] = $this->createProvinceWithWard('Hải Phòng', 'Phường Hồng Bàng');
        [, $wardB] = $this->createProvinceWithWard('Huế', 'Phường Thuận Hóa');

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_offline' => '1',
                'province_id' => $provinceA->province_id,
                'ward_ids' => [$wardA->ward_id, $wardB->ward_id],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'));

        $profile->refresh();
        $this->assertFalse($profile->supports_online);
        $this->assertTrue($profile->supports_offline);
        $this->assertEqualsCanonicalizing(
            [$wardA->ward_id, $wardB->ward_id],
            $profile->teachingAreas()->pluck('ward_id')->all()
        );
    }

    public function test_user_can_save_both_teaching_modes(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        [$province, $ward] = $this->createProvinceWithWard('Quảng Ninh', 'Phường Hạ Long');

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_online' => '1',
                'supports_offline' => '1',
                'province_id' => $province->province_id,
                'ward_ids' => [$ward->ward_id],
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'))
            ->assertSessionHas('success', 'Đã lưu hình thức & khu vực');

        $profile->refresh();
        $this->assertTrue($profile->supports_online);
        $this->assertTrue($profile->supports_offline);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'ward_id' => $ward->ward_id,
        ]);
    }

    public function test_validation_rejects_missing_teaching_mode_and_offline_without_wards(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);

        $this->actingAs($user)
            ->from(route('tutor-registration.teaching-preferences.edit'))
            ->put(route('tutor-registration.teaching-preferences.update'), [])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'))
            ->assertSessionHasErrors('supports_online');

        $this->actingAs($user)
            ->from(route('tutor-registration.teaching-preferences.edit'))
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_offline' => '1',
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'))
            ->assertSessionHasErrors('ward_ids');

        $profile->refresh();
        $this->assertFalse($profile->supports_online);
        $this->assertFalse($profile->supports_offline);
    }

    public function test_validation_rejects_invalid_duplicate_wards_and_invalid_province(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        [, $ward] = $this->createProvinceWithWard('Lâm Đồng', 'Phường Xuân Hương');

        $this->actingAs($user)
            ->from(route('tutor-registration.teaching-preferences.edit'))
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_offline' => '1',
                'province_id' => 999999,
                'ward_ids' => [999999],
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'))
            ->assertSessionHasErrors(['province_id', 'ward_ids.0']);

        $this->actingAs($user)
            ->from(route('tutor-registration.teaching-preferences.edit'))
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'supports_offline' => '1',
                'ward_ids' => [$ward->ward_id, $ward->ward_id],
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'))
            ->assertSessionHasErrors('ward_ids.0');

        $this->assertSame(0, TutorTeachingArea::query()->count());
    }

    public function test_sync_preserves_retained_row_adds_new_and_removes_deselected_area(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user, [
            'supports_offline' => true,
        ]);
        [, $retainedWard] = $this->createProvinceWithWard('Bắc Ninh', 'Phường Kinh Bắc');
        [, $removedWard] = $this->createProvinceWithWard('Ninh Bình', 'Phường Hoa Lư');
        [, $addedWard] = $this->createProvinceWithWard('Phú Thọ', 'Phường Việt Trì');
        $retainedArea = $this->createTeachingArea($profile, $retainedWard);
        $removedArea = $this->createTeachingArea($profile, $removedWard);
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => 101,
            'description' => 'Chuyên môn giữ nguyên',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tutorSubjectLevelId = DB::table('tutor_subject_levels')->insertGetId([
            'tutor_subject_id' => $tutorSubjectId,
            'subject_level_id' => 202,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'supports_offline' => '1',
            'ward_ids' => [$retainedWard->ward_id, $addedWard->ward_id],
        ];

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), $payload)
            ->assertRedirect(route('tutor-registration.availability.edit'));

        $this->assertDatabaseHas('tutor_teaching_areas', [
            'teaching_area_id' => $retainedArea->teaching_area_id,
            'tutor_profile_id' => $profile->tutor_profile_id,
            'ward_id' => $retainedWard->ward_id,
        ]);
        $this->assertDatabaseMissing('tutor_teaching_areas', [
            'teaching_area_id' => $removedArea->teaching_area_id,
        ]);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'ward_id' => $addedWard->ward_id,
        ]);

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), $payload)
            ->assertRedirect(route('tutor-registration.availability.edit'));

        $this->assertSame(2, $profile->teachingAreas()->count());
        $this->assertDatabaseHas('tutor_subjects', [
            'tutor_subject_id' => $tutorSubjectId,
            'description' => 'Chuyên môn giữ nguyên',
        ]);
        $this->assertDatabaseHas('tutor_subject_levels', [
            'tutor_subject_level_id' => $tutorSubjectLevelId,
            'subject_level_id' => 202,
        ]);

        $profile->refresh();
        $this->assertSame('Giới thiệu ban đầu', $profile->bio);
        $this->assertSame('Học vấn ban đầu', $profile->education_summary);
        $this->assertSame('Kinh nghiệm ban đầu', $profile->teaching_experience);
        $this->assertSame('200000.00', $profile->hourly_rate);
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertNull($profile->approved_at);
    }

    public function test_payload_cannot_modify_another_profile_or_approval_fields(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        [, $ward] = $this->createProvinceWithWard('Thanh Hóa', 'Phường Hạc Thành');
        $otherArea = $this->createTeachingArea($otherProfile, $ward);

        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), [
                'user_id' => $otherUser->user_id,
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'approval_status' => 'REJECTED',
                'approved_at' => now()->addYear()->toDateTimeString(),
                'supports_online' => '1',
            ])
            ->assertRedirect(route('tutor-registration.availability.edit'));

        $profile->refresh();
        $otherProfile->refresh();
        $this->assertTrue($profile->supports_online);
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertFalse($otherProfile->supports_online);
        $this->assertFalse($otherProfile->supports_offline);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'teaching_area_id' => $otherArea->teaching_area_id,
            'tutor_profile_id' => $otherProfile->tutor_profile_id,
        ]);
    }

    private function createUser(): User
    {
        $this->userSequence++;

        return User::query()->create([
            'google_id' => "google-teaching-{$this->userSequence}",
            'email' => "teaching-{$this->userSequence}@example.com",
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
            'bio' => 'Giới thiệu ban đầu',
            'education_summary' => 'Học vấn ban đầu',
            'teaching_experience' => 'Kinh nghiệm ban đầu',
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

    /**
     * @return array{Province, Ward}
     */
    private function createProvinceWithWard(string $provinceName, string $wardName): array
    {
        $this->locationSequence++;
        $province = Province::query()->create([
            'province_code' => "P{$this->locationSequence}",
            'province_name' => $provinceName,
        ]);
        $ward = Ward::query()->create([
            'province_id' => $province->province_id,
            'ward_code' => "W{$this->locationSequence}",
            'ward_name' => $wardName,
            'ward_type' => 'Phường',
        ]);

        return [$province, $ward];
    }

    private function createTeachingArea(TutorProfile $profile, Ward $ward): TutorTeachingArea
    {
        return TutorTeachingArea::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'ward_id' => $ward->ward_id,
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

        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_code', 20)->nullable()->unique();
            $table->string('province_name', 100);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_code', 20)->unique();
            $table->string('ward_name', 100);
            $table->string('ward_type', 30)->nullable();
            $table->timestamps();

            $table->foreign('province_id')->references('province_id')->on('provinces');
        });

        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'ward_id']);
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('ward_id')->references('ward_id')->on('wards');
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
}
