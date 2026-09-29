<?php

namespace Tests\Feature;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorRegistrationBasicInformationTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_cannot_view_or_save_tutor_basic_information(): void
    {
        $this->get(route('tutor-registration.basic.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('tutor-registration.basic.update'), $this->validPayload())
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_database_identity_existing_values_and_background(): void
    {
        $user = $this->createUser([
            'full_name' => 'Nguyễn Minh Anh',
            'avatar_url' => 'https://lh3.googleusercontent.com/tutor-avatar.jpg',
        ]);

        TutorProfile::query()->create([
            'user_id' => $user->user_id,
            'headline' => 'Gia sư Toán THCS – Dễ hiểu, kiên nhẫn',
            'bio' => 'Giới thiệu đang lưu trong cơ sở dữ liệu.',
            'education_summary' => 'Cử nhân Sư phạm Toán',
            'teaching_experience' => 'Ba năm giảng dạy học sinh THPT.',
            'hourly_rate' => 250000,
        ]);

        $this->actingAs($user)
            ->get(route('tutor-registration.basic.edit'))
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSee('Đăng ký trở thành')
            ->assertSee('Thông tin cơ bản')
            ->assertSee('Chuyên môn')
            ->assertSee('Nguyễn Minh Anh')
            ->assertSee('https://lh3.googleusercontent.com/tutor-avatar.jpg', false)
            ->assertSee('value="Gia sư Toán THCS – Dễ hiểu, kiên nhẫn"', false)
            ->assertSeeText('Một câu ngắn giúp người học nhanh chóng hiểu thế mạnh của bạn.')
            ->assertSee('Giới thiệu đang lưu trong cơ sở dữ liệu.')
            ->assertSee('value="Cử nhân Sư phạm Toán"', false)
            ->assertSee('Ba năm giảng dạy học sinh THPT.')
            ->assertSee('value="250000.00"', false)
            ->assertSee(asset('images/nen.webp'), false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="tutor_profile_id"', false)
            ->assertDontSee('name="approval_status"', false)
            ->assertDontSee('Hồ sơ đang chờ xét duyệt');
    }

    public function test_user_without_a_profile_can_save_step_one_with_server_controlled_ownership_and_status(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $response = $this->actingAs($user)
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'user_id' => $otherUser->user_id,
                'tutor_profile_id' => 999999,
                'approval_status' => 'APPROVED',
                'approved_at' => now()->toDateTimeString(),
                'submitted_at' => now()->toDateTimeString(),
                'supports_online' => true,
                'supports_offline' => true,
            ]));

        $response
            ->assertRedirect(route('tutor-registration.specialization.edit'));

        $profile = TutorProfile::query()->sole();

        $this->assertSame($user->user_id, $profile->user_id);
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertNull($profile->submitted_at);
        $this->assertFalse($profile->supports_online);
        $this->assertFalse($profile->supports_offline);
        $this->assertSame('Gia sư Toán THCS – Dễ hiểu, kiên nhẫn', $profile->headline);
        $this->assertSame('Tôi hướng dẫn kiến thức theo cách rõ ràng.', $profile->bio);
        $this->assertSame('Cử nhân Công nghệ thông tin', $profile->education_summary);
        $this->assertSame('Có kinh nghiệm hỗ trợ học sinh ôn tập.', $profile->teaching_experience);
        $this->assertSame('200000.00', $profile->hourly_rate);
        $this->assertSame(0, Schema::getConnection()->table('notifications')->count());
        $this->assertSame(0, Schema::getConnection()->table('tutor_profile_reviews')->count());
    }

    public function test_existing_profile_is_updated_without_creating_a_second_profile_or_changing_other_fields(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $profile = TutorProfile::query()->create([
            'user_id' => $user->user_id,
            'headline' => 'Headline cần được giữ nguyên',
            'bio' => 'Giới thiệu cũ',
            'education_summary' => 'Học vấn cũ',
            'teaching_experience' => 'Kinh nghiệm cũ',
            'hourly_rate' => 100000,
            'supports_online' => true,
            'supports_offline' => true,
        ]);
        $otherProfile = TutorProfile::query()->create([
            'user_id' => $otherUser->user_id,
            'bio' => 'Không được thay đổi',
            'education_summary' => 'Học vấn người khác',
            'teaching_experience' => 'Kinh nghiệm người khác',
            'hourly_rate' => 150000,
        ]);

        $this->actingAs($user)
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'user_id' => $otherUser->user_id,
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'approval_status' => 'REJECTED',
            ]))
            ->assertRedirect(route('tutor-registration.specialization.edit'));

        $profile->refresh();
        $otherProfile->refresh();

        $this->assertSame(2, TutorProfile::query()->count());
        $this->assertSame('Tôi hướng dẫn kiến thức theo cách rõ ràng.', $profile->bio);
        $this->assertSame('Cử nhân Công nghệ thông tin', $profile->education_summary);
        $this->assertSame('Có kinh nghiệm hỗ trợ học sinh ôn tập.', $profile->teaching_experience);
        $this->assertSame('200000.00', $profile->hourly_rate);
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertTrue($profile->supports_online);
        $this->assertTrue($profile->supports_offline);
        $this->assertSame('Gia sư Toán THCS – Dễ hiểu, kiên nhẫn', $profile->headline);
        $this->assertNull($profile->approved_at);
        $this->assertNull($profile->submitted_at);
        $this->assertSame('Không được thay đổi', $otherProfile->bio);
    }

    public function test_validation_rejects_missing_invalid_and_oversized_values_without_saving(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from(route('tutor-registration.basic.edit'))
            ->put(route('tutor-registration.basic.update'), [
                'headline' => '',
                'bio' => '',
                'education_summary' => str_repeat('a', 501),
                'teaching_experience' => ['not-a-string'],
                'hourly_rate' => -1,
            ])
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHasErrors([
                'headline',
                'bio',
                'education_summary',
                'teaching_experience',
                'hourly_rate',
            ]);

        $this->assertSame(0, TutorProfile::query()->count());
    }

    public function test_headline_rejects_whitespace_and_values_longer_than_database_column(): void
    {
        $user = $this->createUser();
        $url = route('tutor-registration.basic.edit');

        $this->actingAs($user)
            ->from($url)
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'headline' => '   ',
            ]))
            ->assertRedirect($url)
            ->assertSessionHasErrors('headline');

        $this->actingAs($user)
            ->from($url)
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'headline' => str_repeat('a', 151),
            ]))
            ->assertRedirect($url)
            ->assertSessionHasErrors('headline');

        $this->assertSame(0, TutorProfile::query()->count());
    }

    public function test_headline_is_preserved_as_old_input_when_another_field_is_invalid(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from(route('tutor-registration.basic.edit'))
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'hourly_rate' => -1,
            ]))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHasErrors('hourly_rate')
            ->assertSessionHasInput('headline', 'Gia sư Toán THCS – Dễ hiểu, kiên nhẫn');
    }

    public function test_mysql_text_byte_limit_is_validated_before_saving(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from(route('tutor-registration.basic.edit'))
            ->put(route('tutor-registration.basic.update'), array_merge($this->validPayload(), [
                'bio' => str_repeat('a', 65536),
            ]))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHasErrors('bio');

        $this->assertSame(0, TutorProfile::query()->count());
    }

    public function test_saved_values_are_loaded_again_for_editing(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->put(route('tutor-registration.basic.update'), $this->validPayload())
            ->assertRedirect(route('tutor-registration.specialization.edit'));

        $this->actingAs($user)
            ->get(route('tutor-registration.basic.edit'))
            ->assertOk()
            ->assertSee('value="Gia sư Toán THCS – Dễ hiểu, kiên nhẫn"', false)
            ->assertSee('Tôi hướng dẫn kiến thức theo cách rõ ràng.')
            ->assertSee('value="Cử nhân Công nghệ thông tin"', false)
            ->assertSee('Có kinh nghiệm hỗ trợ học sinh ôn tập.')
            ->assertSee('value="200000.00"', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'headline' => '  Gia sư Toán THCS – Dễ hiểu, kiên nhẫn  ',
            'bio' => '  Tôi hướng dẫn kiến thức theo cách rõ ràng.  ',
            'education_summary' => '  Cử nhân Công nghệ thông tin  ',
            'teaching_experience' => '  Có kinh nghiệm hỗ trợ học sinh ôn tập.  ',
            'hourly_rate' => '200000.00',
        ];
    }

    private function createUser(array $attributes = []): User
    {
        $this->userSequence++;

        return User::query()->create(array_merge([
            'google_id' => "google-tutor-registration-{$this->userSequence}",
            'email' => "tutor-registration-{$this->userSequence}@example.com",
            'full_name' => "Thành viên GiaSu {$this->userSequence}",
            'avatar_url' => null,
            'phone' => null,
        ], $attributes));
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
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutor_profiles')) {
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
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->bigIncrements('notification_id');
                $table->unsignedBigInteger('user_id');
                $table->string('notification_type', 50);
            });
        }

        if (! Schema::hasTable('tutor_profile_reviews')) {
            Schema::create('tutor_profile_reviews', function (Blueprint $table): void {
                $table->bigIncrements('review_id');
                $table->unsignedBigInteger('tutor_profile_id');
                $table->string('review_source', 30);
                $table->string('review_result', 30);
            });
        }
    }
}
