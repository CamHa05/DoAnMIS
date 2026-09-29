<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\TutorApplication;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-13 10:00:00');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_approved_submitted_tutor_can_apply_and_accept_expected_fee(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutor = $this->createUser('Gia sư đã duyệt');
        $profileId = $this->createTutorProfile($tutor);
        $requestId = $this->createTutoringRequest($owner);

        $response = $this->actingAs($tutor)->post(
            route('requests.applications.store', $requestId),
            $this->validPayload()
        );

        $response
            ->assertRedirect(route('requests.show', $requestId))
            ->assertSessionHas('success', 'Ứng tuyển thành công.');

        $this->assertDatabaseHas('tutor_applications', [
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'message' => 'Mình có kinh nghiệm dạy nền tảng và có thể đồng hành cùng bạn.',
            'proposed_fee' => null,
            'status' => TutorApplication::STATUS_PENDING,
            'applied_at' => now(),
        ]);
        $this->assertSame(1, DB::table('tutor_applications')->count());
    }

    public function test_approved_tutor_without_identity_verification_cannot_apply(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutor = $this->createUser('Gia sư chưa xác minh', identityStatus: null);
        $this->createTutorProfile($tutor);
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas('error', 'Bạn cần xác minh danh tính trước khi ứng tuyển.');

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_approved_tutor_with_pending_identity_verification_cannot_apply(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutor = $this->createUser(
            'Gia sư đang xác minh',
            identityStatus: IdentityVerification::STATUS_PENDING
        );
        $this->createTutorProfile($tutor);
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas('error', 'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.');

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_approved_tutor_with_rejected_identity_verification_cannot_apply(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutor = $this->createUser(
            'Gia sư bị từ chối xác minh',
            identityStatus: IdentityVerification::STATUS_REJECTED
        );
        $this->createTutorProfile($tutor);
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas(
                'error',
                'Xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật hồ sơ.'
            );

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_request_detail_shows_identity_cta_and_status_specific_helper(): void
    {
        foreach ([
            'none' => [null, 'Bạn cần xác minh danh tính trước khi ứng tuyển.'],
            'pending' => [IdentityVerification::STATUS_PENDING, 'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.'],
            'rejected' => [IdentityVerification::STATUS_REJECTED, 'Xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật hồ sơ.'],
        ] as $label => [$identityStatus, $message]) {
            $owner = $this->createUser("Chủ {$label}");
            $tutor = $this->createUser("Gia sư {$label}", identityStatus: $identityStatus);
            $this->createTutorProfile($tutor);
            $requestId = $this->createTutoringRequest($owner);

            $this->actingAs($tutor)
                ->get(route('requests.show', $requestId))
                ->assertOk()
                ->assertSee('data-context="tutor_identity_required"', false)
                ->assertSeeText('Xác minh danh tính')
                ->assertSeeText($message)
                ->assertSee('href="'.route('identity-verification.show').'"', false)
                ->assertDontSee('data-request-application-open', false)
                ->assertDontSeeText('Bạn đáp ứng điều kiện ứng tuyển');
        }
    }

    public function test_custom_formatted_fee_is_normalized_and_saved(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'fee_option' => 'custom',
                'proposed_fee' => '175.000',
            ]))
            ->assertRedirect(route('requests.show', $requestId));

        $this->assertDatabaseHas('tutor_applications', [
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'proposed_fee' => 175000,
            'status' => TutorApplication::STATUS_PENDING,
        ]);
    }

    public function test_custom_fee_is_required_and_validation_reopens_modal_with_old_input(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();
        $detailUrl = route('requests.show', $requestId);

        $response = $this->actingAs($tutor)
            ->followingRedirects()
            ->from($detailUrl)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'fee_option' => 'custom',
                'proposed_fee' => '',
                'message' => 'Lời nhắn cần được giữ lại.',
            ]));

        $response
            ->assertOk()
            ->assertSee('data-auto-open="true"', false)
            ->assertSeeText('Lời nhắn cần được giữ lại.')
            ->assertSeeText('Vui lòng nhập mức học phí bạn muốn đề xuất.');

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_message_is_required(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'message' => '   ',
            ]))
            ->assertSessionHasErrorsIn('tutorApplication', ['message']);

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_message_cannot_exceed_500_characters(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'message' => str_repeat('a', 501),
            ]))
            ->assertSessionHasErrorsIn('tutorApplication', ['message']);

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_expected_fee_choice_ignores_client_proposed_fee_and_stores_null(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'fee_option' => 'expected',
                'proposed_fee' => 'không phải số',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requests.show', $requestId));

        $this->assertNull(DB::table('tutor_applications')->value('proposed_fee'));
    }

    public function test_negative_custom_fee_fails_validation(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'fee_option' => 'custom',
                'proposed_fee' => '-1',
            ]))
            ->assertSessionHasErrorsIn('tutorApplication', ['proposed_fee']);

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_non_numeric_custom_fee_fails_validation(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'fee_option' => 'custom',
                'proposed_fee' => 'một trăm nghìn',
            ]))
            ->assertSessionHasErrorsIn('tutorApplication', ['proposed_fee']);

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_duplicate_application_is_blocked_with_friendly_message(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();
        $this->insertApplication($requestId, $profileId);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertRedirect(route('requests.show', $requestId))
            ->assertSessionHas('error', 'Bạn đã ứng tuyển yêu cầu học này trước đó.');

        $this->assertDatabaseCount('tutor_applications', 1);
    }

    public function test_regular_learner_without_tutor_profile_cannot_apply(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $learner = $this->createUser('Người học');
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($learner)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_admin_cannot_apply(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $admin = $this->createUser('Quản trị viên', true);
        $this->createTutorProfile($admin);
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($admin)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_pending_and_rejected_tutor_profiles_cannot_apply(): void
    {
        foreach ([TutorProfile::STATUS_PENDING, TutorProfile::STATUS_REJECTED] as $status) {
            $owner = $this->createUser('Chủ '.$status);
            $tutor = $this->createUser('Gia sư '.$status);
            $this->createTutorProfile($tutor, [
                'approval_status' => $status,
                'submitted_at' => now()->subDay(),
            ]);
            $requestId = $this->createTutoringRequest($owner);

            $this->actingAs($tutor)
                ->post(route('requests.applications.store', $requestId), $this->validPayload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_unsubmitted_tutor_profile_cannot_apply(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext([
            'submitted_at' => null,
        ]);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_direct_request_cannot_receive_application(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext(
            requestAttributes: ['request_type' => 'DIRECT', 'status' => 'PENDING']
        );

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_matched_request_cannot_receive_application(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext(
            requestAttributes: ['status' => 'MATCHED']
        );

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_expired_request_status_cannot_receive_application(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext(
            requestAttributes: ['status' => 'EXPIRED']
        );

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_request_with_past_expiration_cannot_receive_application(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext(
            requestAttributes: ['expires_at' => now()->subSecond()]
        );

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_request_from_disabled_owner_cannot_receive_a_new_application(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();
        DB::table('users')
            ->where('user_id', $owner->getKey())
            ->update(['status' => User::STATUS_DISABLED]);

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_request_owner_cannot_apply_to_own_request(): void
    {
        $owner = $this->createUser('Chủ yêu cầu kiêm gia sư');
        $this->createTutorProfile($owner);
        $requestId = $this->createTutoringRequest($owner);

        $this->actingAs($owner)
            ->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_guest_is_redirected_to_login_before_applying(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $requestId = $this->createTutoringRequest($owner);

        $this->post(route('requests.applications.store', $requestId), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('tutor_applications', 0);
    }

    public function test_detail_switches_to_applied_state_after_success(): void
    {
        [$owner, $tutor, $profileId, $requestId] = $this->applicationContext();
        $message = 'Mình sẽ chuẩn bị lộ trình phù hợp với mục tiêu của bạn.';

        $this->actingAs($tutor)
            ->post(route('requests.applications.store', $requestId), $this->validPayload([
                'message' => $message,
            ]))
            ->assertRedirect(route('requests.show', $requestId));

        $this->actingAs($tutor)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Ứng tuyển thành công.')
            ->assertSeeText('Đã ứng tuyển')
            ->assertSeeText('Đang chờ phản hồi')
            ->assertSeeText('Theo mức yêu cầu')
            ->assertSeeText($message)
            ->assertDontSeeText('Ứng tuyển ngay');
    }

    /**
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $requestAttributes
     * @return array{User, User, int, int}
     */
    private function applicationContext(
        array $profileAttributes = [],
        array $requestAttributes = []
    ): array {
        $owner = $this->createUser('Chủ yêu cầu '.DB::table('users')->count());
        $tutor = $this->createUser('Gia sư '.DB::table('users')->count());
        $profileId = $this->createTutorProfile($tutor, $profileAttributes);
        $requestId = $this->createTutoringRequest($owner, $requestAttributes);

        return [$owner, $tutor, $profileId, $requestId];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'fee_option' => 'expected',
            'proposed_fee' => null,
            'message' => 'Mình có kinh nghiệm dạy nền tảng và có thể đồng hành cùng bạn.',
        ], $overrides);
    }

    private function createUser(
        string $name,
        bool $isAdmin = false,
        ?string $identityStatus = IdentityVerification::STATUS_VERIFIED
    ): User
    {
        $index = DB::table('users')->count() + 1;
        $userId = DB::table('users')->insertGetId([
            'google_id' => "google-application-{$index}",
            'email' => "application-{$index}@example.test",
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => '090'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail($userId);

        if ($identityStatus !== null) {
            $this->createIdentityVerification($user, $identityStatus);
        }

        return $user;
    }

    private function createIdentityVerification(User $user, string $status): void
    {
        DB::table('identity_verifications')->insert([
            'user_id' => $user->getKey(),
            'document_type' => 'CCCD',
            'document_number' => str_pad((string) $user->getKey(), 12, '0', STR_PAD_LEFT),
            'front_image_path' => 'identity-verifications/test/front.jpg',
            'back_image_path' => 'identity-verifications/test/back.jpg',
            'status' => $status,
            'submitted_at' => now()->subDay(),
            'verified_at' => $status === IdentityVerification::STATUS_VERIFIED ? now() : null,
            'verified_by' => null,
            'rejection_reason' => $status === IdentityVerification::STATUS_REJECTED
                ? 'Ảnh giấy tờ chưa rõ.'
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createTutorProfile(User $user, array $attributes = []): int
    {
        return DB::table('tutor_profiles')->insertGetId(array_merge([
            'user_id' => $user->getKey(),
            'headline' => 'Gia sư tận tâm',
            'bio' => null,
            'education_summary' => null,
            'teaching_experience' => null,
            'hourly_rate' => 180000,
            'supports_online' => true,
            'supports_offline' => false,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
            'submitted_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function createTutoringRequest(User $owner, array $attributes = []): int
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'IELTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => 'Foundation',
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('tutoring_requests')->insertGetId(array_merge([
            'user_id' => $owner->getKey(),
            'target_tutor_profile_id' => null,
            'subject_level_id' => $levelId,
            'ward_id' => null,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'ANY',
            'address_detail' => null,
            'expected_fee' => 180000,
            'fee_type' => 'HOURLY',
            'description' => 'Cần củng cố kiến thức nền tảng.',
            'status' => 'OPEN',
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subHours(4),
            'updated_at' => now(),
        ], $attributes));
    }

    private function insertApplication(int $requestId, int $profileId): void
    {
        DB::table('tutor_applications')->insert([
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'message' => 'Đã gửi trước đó.',
            'proposed_fee' => null,
            'status' => TutorApplication::STATUS_PENDING,
            'applied_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
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
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('education_summary', 500)->nullable();
            $table->text('teaching_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status')->default('PENDING');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
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
        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name');
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
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('request_schedules', function (Blueprint $table): void {
            $table->bigIncrements('request_schedule_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
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
        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->text('message')->nullable();
            $table->decimal('proposed_fee', 12, 2)->nullable();
            $table->string('status')->default('PENDING');
            $table->dateTime('applied_at');
            $table->dateTime('updated_at');
            $table->unique(['request_id', 'tutor_profile_id'], 'uq_tutor_application');
        });
    }
}
