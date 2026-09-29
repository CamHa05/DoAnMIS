<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleAuthRoleRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('google_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id')->unique();
        });
    }

    public function test_new_google_user_with_a_safe_avatar_is_created_and_logged_in(): void
    {
        $profile = $this->googleProfile([
            'sub' => 'google-new-safe-avatar',
            'email' => 'new-safe-avatar@example.test',
            'name' => 'Nguyễn Minh Anh',
            'picture' => 'https://lh3.googleusercontent.com/avatar.jpg',
        ]);

        $this->fakeGoogleProfile($profile);

        $this->callbackFor($profile['sub'])
            ->assertRedirect(route('home'));

        $user = User::query()->where('email', $profile['email'])->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($profile['picture'], $user->avatar_url);
        $this->assertFalse($user->is_admin);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertDatabaseCount('tutor_profiles', 0);
    }

    public function test_new_google_user_with_an_oversized_avatar_is_created_without_an_avatar(): void
    {
        $profile = $this->googleProfile([
            'sub' => 'google-new-oversized-avatar',
            'email' => 'new-oversized-avatar@example.test',
            'picture' => 'https://lh3.googleusercontent.com/'.str_repeat('a', 501),
        ]);

        $this->assertGreaterThan(500, Str::length($profile['picture']));

        $this->fakeGoogleProfile($profile);

        $this->callbackFor($profile['sub'])
            ->assertRedirect(route('home'));

        $user = User::query()->where('email', $profile['email'])->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->avatar_url);
    }

    public function test_existing_user_keeps_a_valid_avatar_when_google_returns_an_oversized_one(): void
    {
        $user = $this->createUser(false, 'google-existing-oversized-avatar');
        $user->avatar_url = 'https://lh3.googleusercontent.com/existing-avatar.jpg';
        $user->save();

        $profile = $this->googleProfile([
            'sub' => $user->google_id,
            'email' => $user->email,
            'picture' => 'https://lh3.googleusercontent.com/'.str_repeat('a', 501),
        ]);

        $this->fakeGoogleProfile($profile);

        $this->callbackFor($profile['sub'])
            ->assertRedirect(route('home'));

        $user->refresh();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(
            'https://lh3.googleusercontent.com/existing-avatar.jpg',
            $user->avatar_url
        );
    }

    public function test_google_full_name_is_trimmed_and_limited_without_breaking_utf8(): void
    {
        $longName = '  '.str_repeat('Nguyễn Ánh ', 20).'  ';
        $profile = $this->googleProfile([
            'sub' => 'google-long-utf8-name',
            'email' => 'long-utf8-name@example.test',
            'name' => $longName,
        ]);

        $this->fakeGoogleProfile($profile);

        $this->callbackFor($profile['sub'])
            ->assertRedirect(route('home'));

        $user = User::query()->where('email', $profile['email'])->firstOrFail();
        $expectedName = Str::substr(trim($longName), 0, 100);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($expectedName, $user->full_name);
        $this->assertSame(100, Str::length($user->full_name));
    }

    public function test_empty_google_picture_does_not_overwrite_an_existing_avatar(): void
    {
        $user = $this->createUser(false, 'google-existing-empty-avatar');
        $user->avatar_url = 'https://lh3.googleusercontent.com/existing-avatar.jpg';
        $user->save();

        $profile = $this->googleProfile([
            'sub' => $user->google_id,
            'email' => $user->email,
            'picture' => '   ',
        ]);

        $this->fakeGoogleProfile($profile);

        $this->callbackFor($profile['sub'])
            ->assertRedirect(route('home'));

        $this->assertSame(
            'https://lh3.googleusercontent.com/existing-avatar.jpg',
            $user->refresh()->avatar_url
        );
    }

    public function test_successful_google_login_always_sends_admin_to_the_admin_dashboard(): void
    {
        $admin = $this->createUser(true, 'google-admin-role-redirect');
        $this->fakeGoogleProfile($this->googleProfile([
            'sub' => $admin->google_id,
            'email' => $admin->email,
            'name' => $admin->full_name,
        ]));

        $this->withSession([
            'google_oauth_state' => 'valid-admin-state',
            'url.intended' => route('requests.create'),
        ])->get(route('auth.google.callback', [
            'state' => 'valid-admin-state',
            'code' => 'valid-admin-code',
        ]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        Http::assertSentCount(2);
    }

    public function test_successful_google_login_keeps_the_existing_intended_redirect_for_a_regular_user(): void
    {
        $user = $this->createUser(false, 'google-member-role-redirect');
        $this->fakeGoogleProfile($this->googleProfile([
            'sub' => $user->google_id,
            'email' => $user->email,
            'name' => $user->full_name,
        ]));

        $this->withSession([
            'google_oauth_state' => 'valid-member-state',
            'url.intended' => route('requests.show', 15),
        ])->get(route('auth.google.callback', [
            'state' => 'valid-member-state',
            'code' => 'valid-member-code',
        ]))
            ->assertRedirect(route('requests.show', 15));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_admin_is_redirected_from_login_without_a_loop(): void
    {
        $admin = $this->createUser(true, 'google-admin-login-route');

        $this->actingAs($admin)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertRedirectToRoute('admin.dashboard');
    }

    public function test_authenticated_regular_user_keeps_the_existing_login_page_behavior(): void
    {
        $user = $this->createUser(false, 'google-member-login-route');

        $this->actingAs($user)
            ->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_admin_logout_still_invalidates_authentication_and_returns_home(): void
    {
        $admin = $this->createUser(true, 'google-admin-logout');

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_disabled_google_account_cannot_login_or_be_modified_or_duplicated(): void
    {
        $user = $this->createUser(false, 'google-disabled-account');
        $originalName = $user->full_name;
        $user->status = User::STATUS_DISABLED;
        $user->save();

        $this->fakeGoogleProfile($this->googleProfile([
            'sub' => $user->google_id,
            'email' => $user->email,
            'name' => 'Tên mới không được ghi',
            'picture' => 'https://lh3.googleusercontent.com/blocked-avatar.jpg',
        ]));

        $this->callbackFor($user->google_id)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', EnsureAccountIsActive::DISABLED_MESSAGE);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $user->refresh();
        $this->assertSame(User::STATUS_DISABLED, $user->status);
        $this->assertSame($originalName, $user->full_name);
        $this->assertNull($user->avatar_url);
        $this->assertNull($user->last_login);
    }

    public function test_disabled_authenticated_session_is_invalidated_on_the_next_web_request(): void
    {
        $user = $this->createUser(false, 'google-stale-disabled-session');
        $user->status = User::STATUS_DISABLED;
        $user->save();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', EnsureAccountIsActive::DISABLED_MESSAGE);

        $this->assertGuest();
    }

    public function test_reactivated_google_account_can_login_again_without_creating_a_duplicate(): void
    {
        $user = $this->createUser(false, 'google-reactivated-account');
        $user->status = User::STATUS_DISABLED;
        $user->save();
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        $this->fakeGoogleProfile($this->googleProfile([
            'sub' => $user->google_id,
            'email' => $user->email,
            'name' => $user->full_name,
        ]));

        $this->callbackFor($user->google_id)
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'user_id' => $user->getKey(),
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function createUser(bool $isAdmin, string $googleId): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => $googleId,
            'email' => $googleId.'@example.test',
            'full_name' => $isAdmin ? 'Quản trị GiaSu' : 'Thành viên GiaSu',
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function callbackFor(string $googleId)
    {
        return $this->withSession([
            'google_oauth_state' => "valid-{$googleId}-state",
        ])->get(route('auth.google.callback', [
            'state' => "valid-{$googleId}-state",
            'code' => "valid-{$googleId}-code",
        ]));
    }

    /**
     * @param array{sub?: string, email?: string, email_verified?: bool, name?: string, picture?: string} $overrides
     * @return array{sub: string, email: string, email_verified: bool, name: string, picture?: string}
     */
    private function googleProfile(array $overrides = []): array
    {
        return array_merge([
            'sub' => 'google-default',
            'email' => 'google-default@example.test',
            'email_verified' => true,
            'name' => 'Người dùng Google',
        ], $overrides);
    }

    private function fakeGoogleProfile(array $profile): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response($profile),
        ]);
    }
}
