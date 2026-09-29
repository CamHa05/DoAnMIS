<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
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
            $table->unsignedBigInteger('user_id');
            $table->text('teaching_experience')->nullable();
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->string('learning_mode', 20);
            $table->timestamps();
        });

        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id');
            $table->date('start_date');
            $table->timestamps();
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_access_the_admin_shell(): void
    {
        $this->actingAs($this->createUser())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_the_reusable_shell_with_dashboard_content(): void
    {
        $admin = $this->createUser(isAdmin: true);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.index')
            ->assertSeeText('Tổng quan')
            ->assertSeeText('Theo dõi nhanh tình trạng hoạt động của hệ thống GiaSu')
            ->assertSeeText($admin->full_name)
            ->assertSee('class="admin-sidebar"', false)
            ->assertSee('class="admin-header"', false)
            ->assertSee('class="admin-content"', false)
            ->assertSee('class="admin-footer"', false)
            ->assertSee('admin-header__notification', false)
            ->assertSee('aria-label="Thông báo"', false)
            ->assertSee('class="admin-header__divider"', false)
            ->assertSee('class="admin-header__identity-copy"', false)
            ->assertSeeText('Admin')
            ->assertDontSee('Tìm kiếm — sắp triển khai')
            ->assertDontSee('id="admin-search"', false)
            ->assertDontSee('admin-header__notification-badge', false)
            ->assertDontSee('data-admin-tutor-menu-trigger', false)
            ->assertDontSee('admin-tutor-submenu', false)
            ->assertSee('data-admin-stat="users"', false)
            ->assertSee('data-admin-activity-chart', false);

        $html = $response->getContent();
        $sidebarPosition = strpos($html, 'class="admin-sidebar"');
        $headerPosition = strpos($html, 'class="admin-header"');
        $mainPosition = strpos($html, 'class="admin-content"');
        $footerPosition = strpos($html, 'class="admin-footer"');

        $this->assertNotFalse($sidebarPosition);
        $this->assertNotFalse($headerPosition);
        $this->assertNotFalse($mainPosition);
        $this->assertNotFalse($footerPosition);
        $this->assertLessThan($headerPosition, $sidebarPosition);
        $this->assertLessThan($mainPosition, $headerPosition);
        $this->assertLessThan($footerPosition, $mainPosition);
    }

    public function test_admin_components_accept_page_title_slot_and_active_section(): void
    {
        $admin = $this->createUser(isAdmin: true);
        $this->actingAs($admin);

        $layout = Blade::render(<<<'BLADE'
            <x-admin-layout title="Kiểm duyệt" active-section="users">
                <div data-admin-slot>Nội dung trang con</div>
            </x-admin-layout>
        BLADE);

        $this->assertStringContainsString('<title>Kiểm duyệt · GiaSu</title>', $layout);
        $this->assertStringContainsString('<strong>Kiểm duyệt</strong>', $layout);
        $this->assertStringContainsString('data-admin-slot', $layout);
        $this->assertStringContainsString('Nội dung trang con', $layout);
        $this->assertStringContainsString('class="admin-navigation__item is-active"', $layout);
        $this->assertStringContainsString('aria-disabled="true"', $layout);
    }

    public function test_tutor_route_marks_the_top_level_item_active_without_a_submenu(): void
    {
        $route = (new Route(['GET'], '/admin/tutors', fn () => null))
            ->name('admin.tutors.index');

        $this->app['request']->setRouteResolver(fn () => $route);

        $sidebar = Blade::render('<x-admin-sidebar />');

        $this->assertStringContainsString('class="admin-navigation__item is-active"', $sidebar);
        $this->assertStringContainsString('href="'.route('admin.tutors.index').'"', $sidebar);
        $this->assertStringContainsString('aria-current="page"', $sidebar);
        $this->assertStringNotContainsString('admin-navigation__submenu', $sidebar);
    }

    public function test_admin_header_only_renders_a_notification_badge_for_a_real_unread_count(): void
    {
        $admin = $this->createUser(isAdmin: true);
        $this->actingAs($admin);

        $withoutUnreadNotifications = Blade::render(
            '<x-admin-header title="Tổng quan" :admin="$admin" />',
            compact('admin'),
        );
        DB::table('notifications')->insert(collect(range(1, 3))->map(fn (int $index): array => [
            'user_id' => $admin->getKey(),
            'notification_type' => 'TEST_'.$index,
            'title' => 'Thông báo '.$index,
            'message' => 'Nội dung kiểm tra.',
            'related_type' => null,
            'related_id' => null,
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        $withUnreadNotifications = Blade::render(
            '<x-admin-header title="Tổng quan" :admin="$admin" />',
            compact('admin'),
        );

        $this->assertStringContainsString('<strong>Tổng quan</strong>', $withoutUnreadNotifications);
        $this->assertStringNotContainsString('admin-header__notification-badge', $withoutUnreadNotifications);
        $this->assertStringContainsString('aria-label="Thông báo, 3 chưa đọc"', $withUnreadNotifications);
        $this->assertStringContainsString('admin-header__notification-badge', $withUnreadNotifications);
        $this->assertStringContainsString('>3</span>', preg_replace('/\s+/', '', $withUnreadNotifications));
    }

    private function createUser(bool $isAdmin = false): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => $isAdmin ? 'admin@example.test' : 'member@example.test',
            'full_name' => $isAdmin ? 'Quản trị GiaSu' : 'Thành viên GiaSu',
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }
}
