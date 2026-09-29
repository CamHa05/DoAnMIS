<?php

namespace Tests\Feature;

use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TutorOwnerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->createSchema();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tutor-area.profile'))
            ->assertRedirect(route('login'));
    }

    public function test_owner_page_uses_current_users_profile_and_eager_loaded_relationships(): void
    {
        $owner = $this->createUser('Nguyễn Văn Minh', 'minh-owner@example.test');
        $ownerProfileId = $this->createTutorProfile(
            $owner,
            TutorProfile::STATUS_APPROVED,
            '2026-09-18 09:00:00'
        );
        $other = $this->createUser('Hồ sơ người khác', 'other-owner@example.test');
        $this->createTutorProfile($other, TutorProfile::STATUS_APPROVED, '2026-09-18 09:00:00');

        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'Toán',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => 'THPT',
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $ownerProfileId,
            'subject_id' => $subjectId,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_subject_levels')->insert([
            'tutor_subject_id' => $tutorSubjectId,
            'subject_level_id' => $levelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $provinceId = DB::table('provinces')->insertGetId([
            'province_code' => '79',
            'province_name' => 'TP. Hồ Chí Minh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_code' => 'owner-ward',
            'ward_name' => 'Phường Bến Thành',
            'ward_type' => 'Phường',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_teaching_areas')->insert([
            'tutor_profile_id' => $ownerProfileId,
            'ward_id' => $wardId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $timeSlotId = DB::table('time_slots')->insertGetId([
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'slot_name' => 'Buổi tối',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_availabilities')->insert([
            'tutor_profile_id' => $ownerProfileId,
            'time_slot_id' => $timeSlotId,
            'day_of_week' => 2,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentPath = TutorDocument::STORAGE_PREFIX.'/'.$ownerProfileId.'/degree.png';
        Storage::disk('local')->put($documentPath, 'private-image');
        $documentId = DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $ownerProfileId,
            'document_type' => TutorDocument::TYPE_DEGREE,
            'document_name' => 'Bằng tốt nghiệp',
            'file_url' => $documentPath,
            'verification_status' => TutorDocument::STATUS_APPROVED,
            'uploaded_at' => '2026-09-18 08:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($owner)
                ->get(route('tutor-area.profile'));
        } finally {
            Model::preventLazyLoading(false);
        }

        $response
            ->assertOk()
            ->assertViewIs('tutor-area.profile')
            ->assertSee('class="tutor-area-shell"', false)
            ->assertSee('class="tutor-account-sidebar"', false)
            ->assertSeeText('Tài khoản gia sư')
            ->assertSeeText('Nguyễn Văn Minh')
            ->assertSeeText('Gia sư Toán THPT')
            ->assertSeeText('Toán')
            ->assertSeeText('THPT')
            ->assertSeeText('Phường Bến Thành, TP. Hồ Chí Minh')
            ->assertSeeText('Thứ 3')
            ->assertSeeText('18:00 – 20:00')
            ->assertSeeText('Bằng tốt nghiệp')
            ->assertSeeText('Hồ sơ đã được duyệt')
            ->assertSeeText('Chỉnh sửa hồ sơ')
            ->assertSee(route('tutor-area.profile.edit'), false)
            ->assertDontSeeText('Hồ sơ người khác')
            ->assertSee(route('tutors.show', $ownerProfileId), false)
            ->assertSee(e(route('tutor-registration.documents.download', [
                'document' => $documentId,
                'disposition' => 'inline',
            ])), false);

        $profile = $response->viewData('tutorProfile');
        $this->assertSame($ownerProfileId, $profile->tutor_profile_id);
        $this->assertTrue($profile->relationLoaded('tutorSubjects'));
        $this->assertTrue($profile->relationLoaded('teachingAreas'));
        $this->assertTrue($profile->relationLoaded('availabilities'));
        $this->assertTrue($profile->relationLoaded('documents'));
        $this->assertTrue($profile->relationLoaded('changeRequests'));
    }

    public function test_draft_profile_links_to_existing_edit_flow_and_has_honest_empty_states(): void
    {
        $owner = $this->createUser('Gia sư hồ sơ nháp', 'draft-owner@example.test');
        $this->createTutorProfile($owner, TutorProfile::STATUS_PENDING, null, false, false);

        $this->actingAs($owner)
            ->get(route('tutor-area.profile'))
            ->assertOk()
            ->assertSeeText('Hồ sơ chưa hoàn tất')
            ->assertSeeText('Chỉnh sửa hồ sơ')
            ->assertSee(route('tutor-registration.basic.edit'), false)
            ->assertSeeText('Chưa thể xem công khai')
            ->assertSeeText('Chưa có môn học và cấp độ giảng dạy.')
            ->assertSeeText('Chưa có lịch rảnh để hiển thị.')
            ->assertSeeText('Bạn chưa tải lên tài liệu minh chứng.');
    }

    public function test_private_document_preview_is_inline_and_scoped_to_the_owner(): void
    {
        $owner = $this->createUser('Gia sư có tài liệu', 'document-owner@example.test');
        $ownerProfileId = $this->createTutorProfile($owner, TutorProfile::STATUS_PENDING, null);
        $other = $this->createUser('Gia sư khác', 'document-other@example.test');
        $otherProfileId = $this->createTutorProfile($other, TutorProfile::STATUS_PENDING, null);

        $ownerDocumentId = $this->createDocument($ownerProfileId, 'owner-proof.png');
        $otherDocumentId = $this->createDocument($otherProfileId, 'other-proof.pdf');

        $this->actingAs($owner)
            ->get(route('tutor-registration.documents.download', [
                'document' => $ownerDocumentId,
                'disposition' => 'inline',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($owner)
            ->get(route('tutor-registration.documents.download', [
                'document' => $otherDocumentId,
                'disposition' => 'inline',
            ]))
            ->assertNotFound();
    }

    private function createUser(string $name, string $email): User
    {
        return User::query()->create([
            'google_id' => null,
            'email' => $email,
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => false,
            'status' => 'ACTIVE',
        ]);
    }

    private function createTutorProfile(
        User $user,
        string $status,
        ?string $submittedAt,
        bool $supportsOnline = true,
        bool $supportsOffline = true
    ): int {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->user_id,
            'headline' => 'Gia sư Toán THPT',
            'bio' => 'Tận tâm hỗ trợ người học củng cố kiến thức.',
            'education_summary' => 'Sinh viên ngành Sư phạm Toán.',
            'teaching_experience' => 'Hai năm kinh nghiệm giảng dạy.',
            'hourly_rate' => 150000,
            'supports_online' => $supportsOnline,
            'supports_offline' => $supportsOffline,
            'approval_status' => $status,
            'approved_at' => $status === TutorProfile::STATUS_APPROVED ? '2026-09-19 09:00:00' : null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'submitted_at' => $submittedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDocument(int $tutorProfileId, string $fileName): int
    {
        $path = TutorDocument::STORAGE_PREFIX.'/'.$tutorProfileId.'/'.$fileName;
        Storage::disk('local')->put($path, 'private-proof');

        return DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $tutorProfileId,
            'document_type' => TutorDocument::TYPE_CERTIFICATE,
            'document_name' => 'Tài liệu minh chứng',
            'file_url' => $path,
            'verification_status' => TutorDocument::STATUS_PENDING,
            'uploaded_at' => now(),
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
            $table->unsignedBigInteger('user_id');
            $table->string('headline', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('education_summary', 500)->nullable();
            $table->text('teaching_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name', 100);
            $table->timestamps();
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name', 100);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
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

        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_code', 20)->nullable();
            $table->string('province_name', 100);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_code', 20);
            $table->string('ward_name', 100);
            $table->string('ward_type', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
            $table->timestamps();
        });

        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('time_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('slot_name', 50)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('tutor_availabilities', function (Blueprint $table): void {
            $table->bigIncrements('availability_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('tutor_documents', function (Blueprint $table): void {
            $table->bigIncrements('document_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('document_type', 50);
            $table->string('document_name')->nullable();
            $table->string('file_url', 500);
            $table->string('verification_status', 30)->default('PENDING');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profile_change_requests', function (Blueprint $table): void {
            $table->bigIncrements('change_request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('change_type', 40);
            $table->string('change_action', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
}
