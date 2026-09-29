<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\TimeSlot;
use App\Models\TutorAvailability;
use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\TutorSubjectLevel;
use App\Models\TutorTeachingArea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TutorRegistrationConfirmationTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_view_or_submit_confirmation(): void
    {
        $this->get(route('tutor-registration.confirmation.edit'))
            ->assertRedirect(route('login'));

        $this->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('login'));
    }

    public function test_user_without_profile_is_redirected_to_step_one(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'))
            ->assertRedirect(route('tutor-registration.basic.edit'));

        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('tutor-registration.basic.edit'));
    }

    public function test_review_is_database_driven_uses_correct_edit_links_and_never_leaks_private_path(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user, withAvailability: true);
        $otherUser = $this->createUser();
        $otherProfile = $this->createCompleteProfile($otherUser);
        $privatePath = TutorDocument::STORAGE_PREFIX.'/'.$profile->tutor_profile_id.'/private-proof.pdf';
        $profile->documents()->first()->update([
            'document_name' => 'Bằng tốt nghiệp Đại học',
            'file_url' => $privatePath,
        ]);
        Storage::disk('local')->put($privatePath, 'private');
        $otherProfile->forceFill(['bio' => 'Nội dung riêng của hồ sơ khác'])->save();

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'));

        $response
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSeeText('Xác nhận')
            ->assertSeeText('Tiêu đề hồ sơ')
            ->assertSeeText('Gia sư tận tâm')
            ->assertSeeText('Giới thiệu hồ sơ thật')
            ->assertSeeText('Toán học')
            ->assertSeeText('Lớp 10')
            ->assertSeeText('Trực tuyến')
            ->assertSeeText('Trực tiếp')
            ->assertSeeText('Phường Bến Nghé, Thành phố Hồ Chí Minh')
            ->assertSeeText('Thứ 2')
            ->assertSeeText('Buổi sáng · 08:00 - 10:00')
            ->assertSeeText('Bằng tốt nghiệp Đại học')
            ->assertSeeText('Bằng cấp · Chờ xác minh')
            ->assertSee(route('tutor-registration.basic.edit'), false)
            ->assertSee(route('tutor-registration.specialization.edit'), false)
            ->assertSee(route('tutor-registration.teaching-preferences.edit'), false)
            ->assertSee(route('tutor-registration.availability.edit'), false)
            ->assertSee(route('tutor-registration.documents.edit'), false)
            ->assertSee(route('tutor-registration.documents.download', $profile->documents()->first()->document_id), false)
            ->assertDontSee($privatePath, false)
            ->assertDontSeeText('Nội dung riêng của hồ sơ khác')
            ->assertDontSeeText('Bước 6/6');

        $this->assertSame(5, substr_count($response->getContent(), 'class="is-complete"'));
        $this->assertMatchesRegularExpression(
            '/<li class="is-active" aria-current="step">.*?Xác nhận/s',
            $response->getContent()
        );
    }

    public function test_availability_does_not_duplicate_range_when_slot_name_already_contains_it(): void
    {
        $user = $this->createUser();
        $this->createCompleteProfile($user, withAvailability: true);
        TimeSlot::query()->firstOrFail()->update([
            'slot_name' => '10:00 - 12:00',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'));

        $response
            ->assertOk()
            ->assertSeeText('10:00 - 12:00')
            ->assertDontSeeText('10:00 - 12:00 · 10:00 - 12:00');

        $this->assertSame(
            1,
            substr_count(strip_tags($response->getContent()), '10:00 - 12:00')
        );
    }

    public function test_empty_availability_and_missing_legacy_file_render_safely_without_blocking_submit_ui(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->documents()->first()->update([
            'file_url' => '/uploads/documents/legacy.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'))
            ->assertOk()
            ->assertSeeText('Chưa thiết lập lịch rảnh.')
            ->assertSeeText('Tệp không khả dụng')
            ->assertSee('name="confirmation"', false)
            ->assertSee('Gửi hồ sơ xét duyệt')
            ->assertDontSee('/uploads/documents/legacy.pdf', false);
    }

    public function test_confirmation_must_be_accepted(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);

        $this->actingAs($user)
            ->from(route('tutor-registration.confirmation.edit'))
            ->post(route('tutor-registration.confirmation.submit'))
            ->assertRedirect(route('tutor-registration.confirmation.edit'))
            ->assertSessionHasErrors('confirmation');

        $this->assertNull($profile->fresh()->submitted_at);
    }

    public function test_step_one_incomplete_profile_cannot_submit(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->forceFill(['bio' => null])->save();

        $this->assertIncompleteSubmission($user, $profile, 'completion.basic');
    }

    public function test_missing_headline_is_shown_as_incomplete_and_cannot_be_submitted(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->forceFill(['headline' => null])->save();

        $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'))
            ->assertOk()
            ->assertSeeText('Thông tin cơ bản chưa hoàn tất.')
            ->assertSeeText('Tiêu đề hồ sơ')
            ->assertSeeText('Chưa cung cấp');

        $this->assertIncompleteSubmission($user, $profile, 'completion.basic');
    }

    public function test_step_two_incomplete_profile_cannot_submit(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->tutorSubjects()->first()->tutorSubjectLevels()->delete();

        $this->assertIncompleteSubmission($user, $profile, 'completion.specialization');
    }

    public function test_step_three_incomplete_profile_cannot_submit(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->forceFill([
            'supports_online' => false,
            'supports_offline' => false,
        ])->save();

        $this->assertIncompleteSubmission($user, $profile, 'completion.teaching_preferences');
    }

    public function test_offline_profile_without_teaching_area_cannot_submit(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->forceFill([
            'supports_online' => false,
            'supports_offline' => true,
        ])->save();
        $profile->teachingAreas()->delete();

        $this->assertIncompleteSubmission($user, $profile, 'completion.teaching_preferences');
    }

    public function test_step_five_without_document_cannot_submit(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->documents()->delete();

        $this->assertIncompleteSubmission($user, $profile, 'completion.documents');
    }

    public function test_incomplete_review_shows_section_warning_and_disables_submit_controls(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $profile->documents()->delete();

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'));

        $response
            ->assertOk()
            ->assertSeeText('Hồ sơ chưa hoàn tất')
            ->assertSeeText('Minh chứng chưa hoàn tất.')
            ->assertSeeText('Hoàn thiện')
            ->assertSee(route('tutor-registration.documents.edit'), false);
        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*type="submit")(?=[^>]*disabled)[^>]*>.*?Gửi hồ sơ xét duyệt/s',
            $response->getContent()
        );
    }

    public function test_first_submit_sets_only_submission_workflow_fields_and_preserves_all_step_data(): void
    {
        Carbon::setTestNow('2026-09-11 10:30:00');
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createCompleteProfile($otherUser);
        $before = $this->registrationDataSnapshot($profile);

        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), [
                'confirmation' => 'yes',
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'user_id' => $otherUser->user_id,
                'approval_status' => TutorProfile::STATUS_APPROVED,
                'approved_at' => now()->addYear()->toDateTimeString(),
                'submitted_at' => now()->subYear()->toDateTimeString(),
            ])
            ->assertRedirect(route('tutor-registration.confirmation.edit'))
            ->assertSessionHas('success', 'Đã gửi hồ sơ xét duyệt.');

        $profile->refresh();
        $this->assertSame(TutorProfile::STATUS_PENDING, $profile->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertSame('2026-09-11 10:30:00', $profile->submitted_at?->toDateTimeString());
        $this->assertSame($before, $this->registrationDataSnapshot($profile));
        $this->assertNull($otherProfile->fresh()->submitted_at);
        $this->assertSame(0, DB::table('tutor_profile_reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_second_submit_is_idempotent_and_does_not_change_submission_time(): void
    {
        Carbon::setTestNow('2026-09-11 10:30:00');
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user);

        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('tutor-registration.confirmation.edit'));

        $profile->refresh();
        $submittedAt = $profile->submitted_at?->toDateTimeString();
        $updatedAt = $profile->updated_at?->toDateTimeString();
        Carbon::setTestNow('2026-09-11 11:30:00');

        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('tutor-registration.confirmation.edit'))
            ->assertSessionHas('success', 'Hồ sơ của bạn đã được gửi xét duyệt.');

        $profile->refresh();
        $this->assertSame($submittedAt, $profile->submitted_at?->toDateTimeString());
        $this->assertSame($updatedAt, $profile->updated_at?->toDateTimeString());
        $this->assertSame(0, DB::table('tutor_profile_reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_submitted_pending_profile_is_locked_across_steps_one_to_five(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user, [
            'approval_status' => TutorProfile::STATUS_PENDING,
            'submitted_at' => now()->subHour(),
        ]);

        $this->assertWizardLocked($user, $profile);
    }

    public function test_approved_profile_is_locked_across_steps_one_to_five_and_cannot_submit_again(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user, [
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now()->subHour(),
        ]);

        $this->assertWizardLocked($user, $profile);

        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertForbidden();

        $this->assertSame(TutorProfile::STATUS_APPROVED, $profile->fresh()->approval_status);
    }

    public function test_rejected_profile_can_edit_all_steps_and_resubmit(): void
    {
        $oldSubmittedAt = now()->subDays(2)->startOfSecond();
        $oldApprovedAt = now()->subDays(3)->startOfSecond();
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user, [
            'approval_status' => TutorProfile::STATUS_REJECTED,
            'approved_at' => $oldApprovedAt,
            'submitted_at' => $oldSubmittedAt,
        ]);
        $subject = Subject::query()->firstOrFail();
        $level = SubjectLevel::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('tutor-registration.basic.update'), $this->validBasicPayload())
            ->assertRedirect(route('tutor-registration.specialization.edit'));
        $this->actingAs($user)
            ->put(route('tutor-registration.specialization.update'), [
                'subject_ids' => [$subject->subject_id],
                'subject_levels' => [$subject->subject_id => [$level->subject_level_id]],
            ])
            ->assertRedirect(route('tutor-registration.teaching-preferences.edit'));
        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), ['supports_online' => '1'])
            ->assertRedirect(route('tutor-registration.availability.edit'));
        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), ['availability' => []])
            ->assertRedirect(route('tutor-registration.documents.edit'));
        $this->actingAs($user)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_OTHER,
                'document_name' => 'Giấy xác nhận trợ giảng',
                'document' => UploadedFile::fake()->create('new-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $profile->refresh();
        $this->assertSame('Gia sư Toán THCS đã chỉnh sửa', $profile->headline);
        $this->assertSame(TutorProfile::STATUS_REJECTED, $profile->approval_status);
        $this->assertSame($oldApprovedAt->toDateTimeString(), $profile->approved_at?->toDateTimeString());
        $this->assertSame($oldSubmittedAt->toDateTimeString(), $profile->submitted_at?->toDateTimeString());

        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('tutor-registration.confirmation.edit'));

        $profile->refresh();
        $this->assertSame(TutorProfile::STATUS_PENDING, $profile->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertSame('2026-09-11 14:00:00', $profile->submitted_at?->toDateTimeString());
        $this->assertSame(0, DB::table('tutor_profile_reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_unknown_profile_status_fails_safe(): void
    {
        $user = $this->createUser();
        $profile = $this->createCompleteProfile($user, [
            'approval_status' => 'MANUAL_REVIEW',
        ]);

        $this->actingAs($user)
            ->get(route('tutor-registration.basic.edit'))
            ->assertRedirect(route('tutor-registration.confirmation.edit'));
        $this->actingAs($user)
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'))
            ->assertOk()
            ->assertSeeText('Hồ sơ có trạng thái không hợp lệ.')
            ->assertDontSee('name="confirmation"', false)
            ->assertDontSeeText('Chỉnh sửa');

        $this->assertSame('MANUAL_REVIEW', $profile->fresh()->approval_status);
        $this->assertNull($profile->submitted_at);
    }

    private function assertIncompleteSubmission(User $user, TutorProfile $profile, string $errorKey): void
    {
        $this->actingAs($user)
            ->from(route('tutor-registration.confirmation.edit'))
            ->post(route('tutor-registration.confirmation.submit'), ['confirmation' => '1'])
            ->assertRedirect(route('tutor-registration.confirmation.edit'))
            ->assertSessionHasErrors($errorKey);

        $this->assertNull($profile->fresh()->submitted_at);
    }

    private function assertWizardLocked(User $user, TutorProfile $profile): void
    {
        foreach ([
            'tutor-registration.basic.edit',
            'tutor-registration.specialization.edit',
            'tutor-registration.teaching-preferences.edit',
            'tutor-registration.availability.edit',
            'tutor-registration.documents.edit',
        ] as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertRedirect(route('tutor-registration.confirmation.edit'));
        }

        $subject = Subject::query()->firstOrFail();
        $level = SubjectLevel::query()->firstOrFail();
        $document = $profile->documents()->firstOrFail();
        $before = $this->registrationDataSnapshot($profile);

        $this->actingAs($user)
            ->put(route('tutor-registration.basic.update'), $this->validBasicPayload())
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('tutor-registration.specialization.update'), [
                'subject_ids' => [$subject->subject_id],
                'subject_levels' => [$subject->subject_id => [$level->subject_level_id]],
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('tutor-registration.teaching-preferences.update'), ['supports_online' => '1'])
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('tutor-registration.availability.update'), ['availability' => []])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_OTHER,
                'document_name' => 'Tài liệu bị khóa',
                'document' => UploadedFile::fake()->create('blocked.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('tutor-registration.documents.destroy', $document->document_id))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('tutor-registration.documents.continue'))
            ->assertForbidden();

        $this->assertSame($before, $this->registrationDataSnapshot($profile->fresh()));
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->actingAs($user)
            ->get(route('tutor-registration.confirmation.edit'))
            ->assertOk()
            ->assertDontSeeText('Chỉnh sửa')
            ->assertDontSee('name="confirmation"', false);
    }

    /**
     * @param  array<string, mixed>  $workflow
     */
    private function createCompleteProfile(
        User $user,
        array $workflow = [],
        bool $withAvailability = false
    ): TutorProfile {
        $profile = TutorProfile::query()->create([
            'user_id' => $user->user_id,
            'headline' => 'Gia sư tận tâm',
            'bio' => 'Giới thiệu hồ sơ thật',
            'education_summary' => 'Cử nhân Sư phạm',
            'teaching_experience' => 'Ba năm giảng dạy',
            'hourly_rate' => 200000,
            'supports_online' => true,
            'supports_offline' => $withAvailability,
        ]);
        $profile->forceFill([
            'approval_status' => $workflow['approval_status'] ?? TutorProfile::STATUS_PENDING,
            'approved_at' => $workflow['approved_at'] ?? null,
            'submitted_at' => $workflow['submitted_at'] ?? null,
        ])->save();

        $subject = Subject::query()->firstOrCreate(
            ['subject_name' => 'Toán học'],
            ['status' => 'ACTIVE']
        );
        $level = SubjectLevel::query()->firstOrCreate(
            ['subject_id' => $subject->subject_id, 'level_name' => 'Lớp 10'],
            ['sort_order' => 1, 'status' => 'ACTIVE']
        );
        $tutorSubject = TutorSubject::query()->create([
            'tutor_profile_id' => $profile->tutor_profile_id,
            'subject_id' => $subject->subject_id,
        ]);
        TutorSubjectLevel::query()->create([
            'tutor_subject_id' => $tutorSubject->tutor_subject_id,
            'subject_level_id' => $level->subject_level_id,
        ]);

        $document = new TutorDocument([
            'document_type' => TutorDocument::TYPE_DEGREE,
            'document_name' => 'proof.pdf',
            'file_url' => TutorDocument::STORAGE_PREFIX.'/'.$profile->tutor_profile_id.'/'.Str::random(40).'.pdf',
        ]);
        $document->verification_status = TutorDocument::STATUS_PENDING;
        $document->uploaded_at = now();
        $profile->documents()->save($document);

        if ($withAvailability) {
            $province = Province::query()->firstOrCreate(
                ['province_name' => 'Thành phố Hồ Chí Minh'],
                ['province_code' => 'HCM']
            );
            $ward = $province->wards()->firstOrCreate(
                ['ward_name' => 'Phường Bến Nghé'],
                ['ward_code' => 'BN', 'ward_type' => 'Phường']
            );
            TutorTeachingArea::query()->create([
                'tutor_profile_id' => $profile->tutor_profile_id,
                'ward_id' => $ward->ward_id,
            ]);
            $timeSlot = TimeSlot::query()->firstOrCreate(
                ['start_time' => '08:00', 'end_time' => '10:00'],
                ['slot_name' => 'Buổi sáng', 'status' => 'ACTIVE']
            );
            TutorAvailability::query()->create([
                'tutor_profile_id' => $profile->tutor_profile_id,
                'time_slot_id' => $timeSlot->time_slot_id,
                'day_of_week' => 1,
                'is_available' => true,
            ]);
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationDataSnapshot(TutorProfile $profile): array
    {
        return [
            'basic' => $profile->only([
                'headline',
                'bio',
                'education_summary',
                'teaching_experience',
                'hourly_rate',
                'supports_online',
                'supports_offline',
            ]),
            'subjects' => DB::table('tutor_subjects')
                ->where('tutor_profile_id', $profile->tutor_profile_id)
                ->orderBy('tutor_subject_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'levels' => DB::table('tutor_subject_levels')
                ->whereIn('tutor_subject_id', $profile->tutorSubjects()->pluck('tutor_subject_id'))
                ->orderBy('tutor_subject_level_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'areas' => DB::table('tutor_teaching_areas')
                ->where('tutor_profile_id', $profile->tutor_profile_id)
                ->orderBy('teaching_area_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'availability' => DB::table('tutor_availabilities')
                ->where('tutor_profile_id', $profile->tutor_profile_id)
                ->orderBy('availability_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'documents' => DB::table('tutor_documents')
                ->where('tutor_profile_id', $profile->tutor_profile_id)
                ->orderBy('document_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validBasicPayload(): array
    {
        return [
            'headline' => 'Gia sư Toán THCS đã chỉnh sửa',
            'bio' => 'Nội dung đã chỉnh sửa sau khi bị từ chối.',
            'education_summary' => 'Cử nhân Giáo dục',
            'teaching_experience' => 'Bốn năm giảng dạy',
            'hourly_rate' => '250000',
        ];
    }

    private function createUser(): User
    {
        $this->userSequence++;

        return User::query()->create([
            'google_id' => "google-confirmation-{$this->userSequence}",
            'email' => "confirmation-{$this->userSequence}@example.com",
            'full_name' => "Gia sư {$this->userSequence}",
            'avatar_url' => null,
            'phone' => null,
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
            $table->string('approval_status', 30)->default(TutorProfile::STATUS_PENDING);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('subject_name', 100);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
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
            $table->foreign('subject_id')->references('subject_id')->on('subjects')->restrictOnDelete();
        });

        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();
            $table->unique(['tutor_subject_id', 'subject_level_id']);
            $table->foreign('tutor_subject_id')->references('tutor_subject_id')->on('tutor_subjects')->cascadeOnDelete();
            $table->foreign('subject_level_id')->references('subject_level_id')->on('subject_levels')->restrictOnDelete();
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
            $table->string('ward_code', 20)->nullable()->unique();
            $table->string('ward_name', 100);
            $table->string('ward_type', 20)->nullable();
            $table->timestamps();
            $table->foreign('province_id')->references('province_id')->on('provinces')->cascadeOnDelete();
        });

        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
            $table->timestamps();
            $table->unique(['tutor_profile_id', 'ward_id']);
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('ward_id')->references('ward_id')->on('wards')->restrictOnDelete();
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

        Schema::create('tutor_documents', function (Blueprint $table): void {
            $table->bigIncrements('document_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('document_type', 50);
            $table->string('document_name')->nullable();
            $table->string('file_url', 500);
            $table->string('verification_status', 30)->default(TutorDocument::STATUS_PENDING);
            $table->dateTime('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
        });

        Schema::create('tutor_profile_reviews', function (Blueprint $table): void {
            $table->bigIncrements('review_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('review_source', 20);
            $table->string('review_result', 30);
            $table->text('notes')->nullable();
            $table->dateTime('reviewed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->bigIncrements('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->string('notification_type', 50);
            $table->string('title');
            $table->text('message');
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
}
