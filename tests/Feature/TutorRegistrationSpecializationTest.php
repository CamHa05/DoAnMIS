<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\SubjectCategory;
use App\Models\SubjectLevel;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\TutorSubjectLevel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorRegistrationSpecializationTest extends TestCase
{
    private int $userSequence = 0;

    private int $categorySequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_cannot_view_or_save_specialization(): void
    {
        $this->get(route('tutor-registration.specialization.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('tutor-registration.specialization.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_user_must_complete_basic_information_before_using_step_two(): void
    {
        $user = $this->createUser();
        [$subject, $level] = $this->createSubjectWithLevel('Toán', 'Lớp 10');

        $this->actingAs($user)
            ->get(route('tutor-registration.specialization.edit'))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->put(route('tutor-registration.specialization.update'), $this->validPayload($subject, $level))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, TutorSubject::query()->count());
        $this->assertSame(0, TutorSubjectLevel::query()->count());
    }

    public function test_step_two_loads_active_catalog_and_existing_database_selection(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        [$activeSubject, $activeLevel] = $this->createSubjectWithLevel('Toán học', 'Lớp 10');
        [$inactiveSubject] = $this->createSubjectWithLevel('Môn ngừng hoạt động', 'Cấp độ cũ', 'INACTIVE');
        [$subjectWithInactiveLevel] = $this->createSubjectWithLevel(
            'Vật lý',
            'Trình độ ngừng hoạt động',
            'ACTIVE',
            'INACTIVE'
        );

        $tutorSubject = TutorSubject::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => $activeSubject->subject_id,
        ]);
        TutorSubjectLevel::query()->create([
            'tutor_subject_id' => $tutorSubject->tutor_subject_id,
            'subject_level_id' => $activeLevel->subject_level_id,
        ]);

        $this->actingAs($user)
            ->get(route('tutor-registration.specialization.edit'))
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSeeText('Chuyên môn')
            ->assertSeeText('Toán học')
            ->assertSeeText('Lớp 10')
            ->assertSeeText($activeSubject->category->category_name)
            ->assertSee('data-initial-subject-id="'.$activeSubject->subject_id.'"', false)
            ->assertSee('name="subject_levels['.$activeSubject->subject_id.'][]"', false)
            ->assertDontSeeText('Môn ngừng hoạt động')
            ->assertDontSeeText('Vật lý')
            ->assertDontSeeText('Trình độ ngừng hoạt động')
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="tutor_profile_id"', false)
            ->assertDontSee('name="approval_status"', false);

        $this->assertNotNull($inactiveSubject);
        $this->assertNotNull($subjectWithInactiveLevel);
    }

    public function test_user_can_save_multiple_subjects_and_levels_with_server_controlled_ownership(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        [$math, $mathBasic] = $this->createSubjectWithLevel('Toán', 'Cơ bản');
        $mathAdvanced = $this->createLevel($math, 'Nâng cao');
        [$english, $englishLevel] = $this->createSubjectWithLevel('Tiếng Anh', 'B1');

        $this->actingAs($user)
            ->put(route('tutor-registration.specialization.update'), [
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'approval_status' => 'REJECTED',
                'subject_ids' => [$math->subject_id, $english->subject_id],
                'subject_levels' => [
                    $math->subject_id => [$mathBasic->subject_level_id, $mathAdvanced->subject_level_id],
                    $english->subject_id => [$englishLevel->subject_level_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'))
            ->assertSessionHas('success', 'Đã lưu chuyên môn');

        $this->assertSame(2, $profile->tutorSubjects()->count());
        $this->assertSame(3, TutorSubjectLevel::query()
            ->whereIn('tutor_subject_id', $profile->tutorSubjects()->pluck('tutor_subject_id'))
            ->count());
        $this->assertSame(0, $otherProfile->tutorSubjects()->count());
        $this->assertSame('PENDING', $profile->fresh()->approval_status);
        $this->assertSame('PENDING', $otherProfile->fresh()->approval_status);
    }

    public function test_update_syncs_removed_and_added_relations_without_recreating_retained_rows(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        [$math, $mathBasic] = $this->createSubjectWithLevel('Toán', 'Cơ bản');
        $mathAdvanced = $this->createLevel($math, 'Nâng cao');
        [$physics, $physicsLevel] = $this->createSubjectWithLevel('Vật lý', 'Lớp 12');
        [$english, $englishLevel] = $this->createSubjectWithLevel('Tiếng Anh', 'B2');

        $mathRelation = TutorSubject::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => $math->subject_id,
            'description' => 'Giữ nguyên mô tả này',
        ]);
        $retainedLevelRelation = TutorSubjectLevel::query()->create([
            'tutor_subject_id' => $mathRelation->tutor_subject_id,
            'subject_level_id' => $mathAdvanced->subject_level_id,
        ]);
        TutorSubjectLevel::query()->create([
            'tutor_subject_id' => $mathRelation->tutor_subject_id,
            'subject_level_id' => $mathBasic->subject_level_id,
        ]);

        $physicsRelation = TutorSubject::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => $physics->subject_id,
        ]);
        TutorSubjectLevel::query()->create([
            'tutor_subject_id' => $physicsRelation->tutor_subject_id,
            'subject_level_id' => $physicsLevel->subject_level_id,
        ]);

        $this->actingAs($user)
            ->put(route('tutor-registration.specialization.update'), [
                'subject_ids' => [$math->subject_id, $english->subject_id],
                'subject_levels' => [
                    $math->subject_id => [$mathAdvanced->subject_level_id],
                    $english->subject_id => [$englishLevel->subject_level_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'));

        $mathRelation->refresh();
        $retainedLevelRelation->refresh();

        $this->assertSame('Giữ nguyên mô tả này', $mathRelation->description);
        $this->assertSame($mathAdvanced->subject_level_id, $retainedLevelRelation->subject_level_id);
        $this->assertDatabaseMissing('tutor_subject_levels', [
            'tutor_subject_id' => $mathRelation->tutor_subject_id,
            'subject_level_id' => $mathBasic->subject_level_id,
        ]);
        $this->assertDatabaseMissing('tutor_subjects', [
            'tutor_subject_id' => $physicsRelation->tutor_subject_id,
        ]);
        $this->assertDatabaseHas('tutor_subjects', [
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => $english->subject_id,
        ]);
        $this->assertSame(2, $profile->tutorSubjects()->count());
        $this->assertSame(2, TutorSubjectLevel::query()->count());
    }

    public function test_validation_rejects_inactive_missing_duplicate_and_cross_subject_values(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        [$math, $mathLevel] = $this->createSubjectWithLevel('Toán', 'Lớp 10');
        [$physics, $physicsLevel] = $this->createSubjectWithLevel('Vật lý', 'Lớp 11');
        [$inactiveSubject, $inactiveLevel] = $this->createSubjectWithLevel(
            'Môn cũ',
            'Trình độ cũ',
            'INACTIVE',
            'INACTIVE'
        );

        $this->actingAs($user)
            ->from(route('tutor-registration.specialization.edit'))
            ->put(route('tutor-registration.specialization.update'), [
                'subject_ids' => [$math->subject_id, $math->subject_id, $inactiveSubject->subject_id, 999999],
                'subject_levels' => [
                    $math->subject_id => [
                        $mathLevel->subject_level_id,
                        $mathLevel->subject_level_id,
                        $physicsLevel->subject_level_id,
                        $inactiveLevel->subject_level_id,
                        999999,
                    ],
                    $physics->subject_id => [$physicsLevel->subject_level_id],
                ],
            ])
            ->assertRedirect(route('tutor-registration.specialization.edit'))
            ->assertSessionHasErrors([
                'subject_ids.1',
                'subject_ids.2',
                'subject_ids.3',
                'subject_levels',
                'subject_levels.'.$math->subject_id,
            ]);

        $this->assertSame(0, TutorSubject::query()->count());
        $this->assertSame(0, TutorSubjectLevel::query()->count());
    }

    public function test_each_selected_subject_requires_at_least_one_corresponding_level(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        [$math] = $this->createSubjectWithLevel('Toán', 'Lớp 10');

        $this->actingAs($user)
            ->from(route('tutor-registration.specialization.edit'))
            ->put(route('tutor-registration.specialization.update'), [
                'subject_ids' => [$math->subject_id],
                'subject_levels' => [],
            ])
            ->assertRedirect(route('tutor-registration.specialization.edit'))
            ->assertSessionHasErrors('subject_levels.'.$math->subject_id);

        $this->assertSame(0, TutorSubject::query()->count());
    }

    private function validPayload(Subject $subject, SubjectLevel $level): array
    {
        return [
            'subject_ids' => [$subject->subject_id],
            'subject_levels' => [
                $subject->subject_id => [$level->subject_level_id],
            ],
        ];
    }

    private function createUser(): User
    {
        $this->userSequence++;

        return User::query()->create([
            'google_id' => "google-specialization-{$this->userSequence}",
            'email' => "specialization-{$this->userSequence}@example.com",
            'full_name' => "Thành viên GiaSu {$this->userSequence}",
            'avatar_url' => null,
            'phone' => null,
        ]);
    }

    private function createTutorProfile(User $user, string $approvalStatus = 'PENDING'): TutorProfile
    {
        $profile = TutorProfile::query()->create([
            'user_id' => $user->user_id,
            'bio' => 'Giới thiệu gia sư.',
            'education_summary' => 'Thông tin học vấn.',
            'teaching_experience' => 'Thông tin kinh nghiệm.',
            'hourly_rate' => 200000,
        ]);
        $profile->approval_status = $approvalStatus;
        $profile->save();

        return $profile;
    }

    /**
     * @return array{Subject, SubjectLevel}
     */
    private function createSubjectWithLevel(
        string $subjectName,
        string $levelName,
        string $subjectStatus = 'ACTIVE',
        string $levelStatus = 'ACTIVE'
    ): array {
        $this->categorySequence++;
        $category = SubjectCategory::query()->create([
            'category_name' => "Danh mục {$this->categorySequence}",
            'status' => 'ACTIVE',
        ]);
        $subject = Subject::query()->create([
            'category_id' => $category->category_id,
            'subject_name' => $subjectName,
            'status' => $subjectStatus,
        ]);

        return [$subject, $this->createLevel($subject, $levelName, $levelStatus)];
    }

    private function createLevel(Subject $subject, string $levelName, string $status = 'ACTIVE'): SubjectLevel
    {
        return SubjectLevel::query()->create([
            'subject_id' => $subject->subject_id,
            'education_level_id' => null,
            'level_name' => $levelName,
            'sort_order' => 1,
            'status' => $status,
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

        Schema::create('subject_categories', function (Blueprint $table): void {
            $table->bigIncrements('category_id');
            $table->string('category_name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->unsignedBigInteger('category_id');
            $table->string('subject_name', 100);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('subject_categories');
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name', 100);
            $table->string('description', 255)->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('subject_id')->references('subject_id')->on('subjects')->cascadeOnDelete();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'subject_id']);
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('subject_id')->references('subject_id')->on('subjects');
        });

        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();

            $table->unique(['tutor_subject_id', 'subject_level_id']);
            $table->foreign('tutor_subject_id')->references('tutor_subject_id')->on('tutor_subjects')->cascadeOnDelete();
            $table->foreign('subject_level_id')->references('subject_level_id')->on('subject_levels');
        });

        Schema::enableForeignKeyConstraints();
    }
}
