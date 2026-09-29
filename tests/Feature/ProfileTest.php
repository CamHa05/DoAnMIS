<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('approval_status', 30)->default('PENDING');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('identity_verifications')) {
            Schema::create('identity_verifications', function (Blueprint $table): void {
                $table->bigIncrements('verification_id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('status', 20)->default('PENDING');
                $table->timestamps();
            });
        }
    }

    public function test_guest_cannot_view_or_update_a_profile(): void
    {
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->patch(route('profile.update'), [
            'full_name' => 'Nguyễn Minh Anh',
            'phone' => '0901234567',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Hồ sơ của tôi')
            ->assertSee($user->full_name)
            ->assertSee($user->email)
            ->assertSee('name="email"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="google_id"', false)
            ->assertDontSee('name="is_admin"', false)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="user_id"', false);
    }

    public function test_authenticated_user_updates_only_whitelisted_fields(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser([
            'email' => 'other@example.com',
            'full_name' => 'Người dùng khác',
            'google_id' => 'google-other',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'user_id' => $otherUser->user_id,
                'full_name' => 'Nguyễn Minh Anh',
                'phone' => '090 123 4567',
                'email' => 'changed@example.com',
                'google_id' => 'changed-google-id',
                'is_admin' => true,
                'status' => 'BLOCKED',
                'last_login' => now()->addYear()->toDateTimeString(),
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Cập nhật hồ sơ thành công.');

        $user->refresh();
        $otherUser->refresh();

        $this->assertSame('Nguyễn Minh Anh', $user->full_name);
        $this->assertSame('090 123 4567', $user->phone);
        $this->assertSame('member@example.com', $user->email);
        $this->assertSame('google-member', $user->google_id);
        $this->assertFalse($user->is_admin);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertSame('Người dùng khác', $otherUser->full_name);
    }

    public function test_profile_update_validates_name_and_phone_length(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->patch(route('profile.update'), [
                'full_name' => '',
                'phone' => str_repeat('1', 21),
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['full_name', 'phone'])
            ->assertSessionHasInput('phone', str_repeat('1', 21));

        $user->refresh();

        $this->assertSame('Thành viên GiaSu', $user->full_name);
        $this->assertSame('0912345678', $user->phone);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'google_id' => 'google-member',
            'email' => 'member@example.com',
            'full_name' => 'Thành viên GiaSu',
            'avatar_url' => null,
            'phone' => '0912345678',
        ], $attributes));
    }
}
