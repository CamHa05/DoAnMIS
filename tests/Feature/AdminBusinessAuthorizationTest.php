<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTutorRegistrationEditable;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotAdmin;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminBusinessAuthorizationTest extends TestCase
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
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_admin_is_forbidden_from_every_existing_user_business_route(): void
    {
        $admin = $this->createAdmin();

        $businessRoutes = [
            ['GET', 'tutor-registration.basic.edit', []],
            ['PUT', 'tutor-registration.basic.update', []],
            ['GET', 'tutor-registration.specialization.edit', []],
            ['PUT', 'tutor-registration.specialization.update', []],
            ['GET', 'tutor-registration.teaching-preferences.edit', []],
            ['PUT', 'tutor-registration.teaching-preferences.update', []],
            ['GET', 'tutor-registration.availability.edit', []],
            ['PUT', 'tutor-registration.availability.update', []],
            ['GET', 'tutor-registration.documents.edit', []],
            ['POST', 'tutor-registration.documents.store', []],
            ['POST', 'tutor-registration.documents.continue', []],
            ['DELETE', 'tutor-registration.documents.destroy', ['document' => 1]],
            ['GET', 'tutor-registration.confirmation.edit', []],
            ['POST', 'tutor-registration.confirmation.submit', []],
            ['GET', 'requests.create', []],
            ['POST', 'requests.store', []],
        ];

        foreach ($businessRoutes as [$method, $name, $parameters]) {
            $this->actingAs($admin)
                ->call($method, route($name, $parameters))
                ->assertForbidden();
        }
    }

    public function test_business_route_middleware_is_scoped_without_blocking_admin_or_read_routes(): void
    {
        $protectedRoutes = [
            'tutor-registration.basic.edit',
            'tutor-registration.basic.update',
            'tutor-registration.specialization.edit',
            'tutor-registration.specialization.update',
            'tutor-registration.teaching-preferences.edit',
            'tutor-registration.teaching-preferences.update',
            'tutor-registration.availability.edit',
            'tutor-registration.availability.update',
            'tutor-registration.documents.edit',
            'tutor-registration.documents.store',
            'tutor-registration.documents.continue',
            'tutor-registration.documents.destroy',
            'tutor-registration.confirmation.edit',
            'tutor-registration.confirmation.submit',
            'requests.create',
            'requests.store',
        ];

        foreach ($protectedRoutes as $name) {
            $this->assertContains(
                EnsureUserIsNotAdmin::class,
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Route [{$name}] must reject admin accounts."
            );
        }

        $editableRegistrationRoute = Route::getRoutes()
            ->getByName('tutor-registration.basic.edit');

        $this->assertSame(
            [
                EnsureUserIsNotAdmin::class,
                EnsureTutorRegistrationEditable::class,
            ],
            array_values(array_filter(
                $editableRegistrationRoute->gatherMiddleware(),
                fn (string $middleware): bool => in_array($middleware, [
                    EnsureUserIsNotAdmin::class,
                    EnsureTutorRegistrationEditable::class,
                ], true)
            ))
        );

        $adminRouteMiddleware = Route::getRoutes()
            ->getByName('admin.dashboard')
            ->gatherMiddleware();

        $this->assertContains(EnsureUserIsAdmin::class, $adminRouteMiddleware);
        $this->assertNotContains(EnsureUserIsNotAdmin::class, $adminRouteMiddleware);

        foreach ([
            'home',
            'tutors.index',
            'tutors.show',
            'requests.index',
            'requests.show',
            'my-requests.index',
            'my-requests.show',
            'classes.index',
            'tutor-registration.documents.download',
        ] as $readRoute) {
            $this->assertNotContains(
                EnsureUserIsNotAdmin::class,
                Route::getRoutes()->getByName($readRoute)->gatherMiddleware(),
                "Read route [{$readRoute}] must not be blocked for admin accounts."
            );
        }
    }

    private function createAdmin(): User
    {
        $admin = new User;
        $admin->forceFill([
            'google_id' => 'google-admin-business-authorization',
            'email' => 'admin-business-authorization@example.com',
            'full_name' => 'Quản trị viên',
            'is_admin' => true,
            'status' => 'ACTIVE',
        ]);
        $admin->save();

        return $admin;
    }
}
