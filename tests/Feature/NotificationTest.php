<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\IdentityVerification;
use App\Models\Notification;
use App\Models\TutorApplication;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\NotificationPresenter;
use App\Services\SystemNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_user_only_sees_their_notifications_and_can_filter_unread(): void
    {
        $owner = $this->createUser('owner@example.test', 'Người nhận');
        $other = $this->createUser('other@example.test', 'Người khác');
        $this->createNotification($owner, 'Thông báo đã đọc', true);
        $this->createNotification($owner, 'Thông báo chưa đọc');
        $this->createNotification($other, 'Thông báo riêng của người khác');

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Thông báo đã đọc')
            ->assertSeeText('Thông báo chưa đọc')
            ->assertDontSeeText('Thông báo riêng của người khác');

        $this->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSeeText('Thông báo chưa đọc')
            ->assertViewHas('notifications', function ($notifications): bool {
                return $notifications->total() === 1
                    && $notifications->first()['notification']->title === 'Thông báo chưa đọc';
            });
    }

    public function test_badge_uses_real_unread_count_and_is_hidden_at_zero(): void
    {
        $user = $this->createUser('badge@example.test', 'Người có thông báo');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="notification-center__badge', false);

        $this->createNotification($user, 'Một');
        $this->createNotification($user, 'Hai');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Thông báo, 2 chưa đọc"', false)
            ->assertSee('class="notification-center__badge notification-badge"', false);
    }

    public function test_popup_remains_bounded_to_the_latest_six_items_when_notification_count_grows(): void
    {
        $user = $this->createUser('many-notifications@example.test', 'Người có nhiều thông báo');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Bạn chưa có thông báo nào.');

        $this->createNotification($user, 'Thông báo 01');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Thông báo, 1 chưa đọc"', false)
            ->assertSeeText('Thông báo 01');

        foreach (range(2, 6) as $index) {
            $this->createNotification($user, sprintf('Thông báo %02d', $index));
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Thông báo, 6 chưa đọc"', false)
            ->assertSeeText('Thông báo 01')
            ->assertSeeText('Thông báo 06');

        foreach (range(7, 15) as $index) {
            $this->createNotification($user, sprintf('Thông báo %02d', $index));
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Thông báo, 15 chưa đọc"', false)
            ->assertSeeText('Thông báo 15')
            ->assertSeeText('Thông báo 10')
            ->assertDontSeeText('Thông báo 09');
    }

    public function test_mark_single_and_mark_all_are_scoped_to_the_authenticated_user(): void
    {
        $owner = $this->createUser('reader@example.test', 'Người đọc');
        $other = $this->createUser('outsider@example.test', 'Người ngoài');
        $first = $this->createNotification($owner, 'Một');
        $second = $this->createNotification($owner, 'Hai');
        $foreign = $this->createNotification($other, 'Riêng tư');

        $this->actingAs($owner)
            ->patch(route('notifications.read', $foreign))
            ->assertNotFound();

        $this->patch(route('notifications.read', $first))->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notification_id' => $first->getKey(),
            'is_read' => true,
        ]);

        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notification_id' => $second->getKey(),
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notification_id' => $foreign->getKey(),
            'is_read' => false,
        ]);
    }

    public function test_click_marks_owned_notification_read_and_redirects_without_trusting_a_stored_url(): void
    {
        $user = $this->createUser('click@example.test', 'Người nhấp');
        $notification = $this->createNotification($user, 'Mở thông báo');

        $this->actingAs($user)
            ->post(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_legacy_profile_change_notifications_remain_compatible_with_the_notification_center(): void
    {
        $tutor = $this->createUser('legacy-profile-change@example.test', 'Gia sư cập nhật hồ sơ');
        $approved = $this->createNotification(
            $tutor,
            'Thay đổi hồ sơ đã được duyệt',
            false,
            'PROFILE_CHANGE_APPROVED',
            'TUTOR_PROFILE_CHANGE',
            101
        );
        $rejected = $this->createNotification(
            $tutor,
            'Thay đổi hồ sơ cần bổ sung',
            false,
            'PROFILE_CHANGE_REJECTED',
            'TUTOR_PROFILE_CHANGE',
            102
        );

        $presented = resolve(NotificationPresenter::class)
            ->present(Notification::query()->orderBy('notification_id')->get())
            ->keyBy(fn (array $item): string => $item['notification']->notification_type);

        foreach (['PROFILE_CHANGE_APPROVED', 'PROFILE_CHANGE_REJECTED'] as $type) {
            $this->assertTrue($presented->has($type));
            $this->assertNull($presented->get($type)['actor']);
            $this->assertSame('bell', $presented->get($type)['icon']);
        }

        $this->actingAs($tutor)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Thay đổi hồ sơ đã được duyệt')
            ->assertSeeText('Thay đổi hồ sơ cần bổ sung')
            ->assertSee('aria-label="Thông báo, 2 chưa đọc"', false);

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Thay đổi hồ sơ đã được duyệt')
            ->assertSeeText('Thay đổi hồ sơ cần bổ sung');

        $this->patch(route('notifications.read', $approved))->assertRedirect();
        $this->assertTrue($approved->fresh()->is_read);
        $this->assertNotNull($approved->fresh()->read_at);

        $this->assertTrue(Route::has('tutor-area.profile'));
        $this->post(route('notifications.open', $rejected))
            ->assertRedirect(route('tutor-area.profile'));
        $this->assertTrue($rejected->fresh()->is_read);
        $this->assertNotNull($rejected->fresh()->read_at);
    }

    public function test_the_thirteen_requested_events_create_notifications_for_the_correct_recipients(): void
    {
        $learner = $this->createUser('learner@example.test', 'Nguyễn Người Học');
        $tutorUser = $this->createUser('tutor@example.test', 'Trần Gia Sư');
        $admin = $this->createUser('admin@example.test', 'Quản trị viên', true);
        $disabledAdmin = $this->createUser('disabled-admin@example.test', 'Admin đã khóa', true, 'DISABLED');
        $profile = new TutorProfile;
        $profile->forceFill([
            'user_id' => $tutorUser->getKey(),
            'approval_status' => TutorProfile::STATUS_PENDING,
        ])->save();
        $publicRequest = new TutoringRequest;
        $publicRequest->forceFill([
            'user_id' => $learner->getKey(),
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'status' => 'OPEN',
        ])->save();
        $directRequest = new TutoringRequest;
        $directRequest->forceFill([
            'user_id' => $learner->getKey(),
            'target_tutor_profile_id' => $profile->getKey(),
            'request_type' => 'DIRECT',
            'learning_mode' => 'ONLINE',
            'status' => 'PENDING',
        ])->save();
        $application = new TutorApplication;
        $application->forceFill([
            'request_id' => $publicRequest->getKey(),
            'tutor_profile_id' => $profile->getKey(),
            'message' => 'Tôi muốn nhận lớp.',
            'status' => TutorApplication::STATUS_PENDING,
            'applied_at' => now(),
        ])->save();
        $publicContract = Contract::query()->create([
            'request_id' => $publicRequest->getKey(),
            'tutor_profile_id' => $profile->getKey(),
            'status' => Contract::STATUS_PENDING,
        ]);
        $directContract = Contract::query()->create([
            'request_id' => $directRequest->getKey(),
            'tutor_profile_id' => $profile->getKey(),
            'status' => Contract::STATUS_PENDING,
        ]);
        $class = new TutoringClass;
        $class->forceFill([
            'contract_id' => $directContract->getKey(),
            'class_name' => 'Lớp kiểm tra',
            'start_date' => now()->toDateString(),
            'status' => TutoringClass::STATUS_ACTIVE,
        ])->save();
        $verification = IdentityVerification::query()->create([
            'user_id' => $learner->getKey(),
            'status' => IdentityVerification::STATUS_PENDING,
        ]);
        $service = resolve(SystemNotificationService::class);

        $service->publicApplicationCreated($application, $publicRequest, $profile);
        $service->publicTutorSelected($publicContract, $publicRequest, $profile);
        $service->directRequestCreated($directRequest, $profile);
        $service->directRequestAccepted($directContract, $directRequest);
        $service->directRequestRejected($directRequest);
        $service->learnerConfirmedContract($directContract, $profile);
        $service->classCreated($class, $directContract, $learner->getKey(), $tutorUser->getKey());
        $service->tutorProfileSubmitted($profile);
        $service->tutorProfileApproved($profile);
        $service->tutorProfileRejected($profile);
        $service->identitySubmitted($verification);
        $service->identityVerified($verification);
        $service->identityRejected($verification);

        $this->assertSame(14, Notification::query()->count());
        $this->assertSame(6, Notification::query()->where('user_id', $learner->getKey())->count());
        $this->assertSame(6, Notification::query()->where('user_id', $tutorUser->getKey())->count());
        $this->assertSame(2, Notification::query()->where('user_id', $admin->getKey())->count());
        $this->assertSame(0, Notification::query()->where('user_id', $disabledAdmin->getKey())->count());

        $expectedRecipients = [
            SystemNotificationService::PUBLIC_APPLICATION_CREATED => [$learner->getKey()],
            SystemNotificationService::PUBLIC_TUTOR_SELECTED => [$tutorUser->getKey()],
            SystemNotificationService::DIRECT_REQUEST_CREATED => [$tutorUser->getKey()],
            SystemNotificationService::DIRECT_REQUEST_ACCEPTED => [$learner->getKey()],
            SystemNotificationService::DIRECT_REQUEST_REJECTED => [$learner->getKey()],
            SystemNotificationService::CONTRACT_LEARNER_CONFIRMED => [$tutorUser->getKey()],
            SystemNotificationService::CLASS_CREATED => [$learner->getKey(), $tutorUser->getKey()],
            SystemNotificationService::PROFILE_SUBMITTED => [$admin->getKey()],
            SystemNotificationService::PROFILE_APPROVED => [$tutorUser->getKey()],
            SystemNotificationService::PROFILE_REJECTED => [$tutorUser->getKey()],
            SystemNotificationService::IDENTITY_SUBMITTED => [$admin->getKey()],
            SystemNotificationService::IDENTITY_VERIFIED => [$learner->getKey()],
            SystemNotificationService::IDENTITY_REJECTED => [$learner->getKey()],
        ];

        foreach ($expectedRecipients as $type => $recipientIds) {
            $this->assertSame(
                count($recipientIds),
                Notification::query()->where('notification_type', $type)->count(),
                "Unexpected recipient count for {$type}."
            );

            foreach ($recipientIds as $recipientId) {
                $this->assertDatabaseHas('notifications', [
                    'notification_type' => $type,
                    'user_id' => $recipientId,
                ]);
            }
        }
    }

    public function test_notification_write_rolls_back_with_the_business_transaction(): void
    {
        $learner = $this->createUser('rollback-learner@example.test', 'Người học');
        $tutor = $this->createUser('rollback-tutor@example.test', 'Gia sư');
        $profile = new TutorProfile;
        $profile->forceFill([
            'user_id' => $tutor->getKey(),
            'approval_status' => TutorProfile::STATUS_APPROVED,
        ])->save();
        $request = new TutoringRequest;
        $request->forceFill([
            'user_id' => $learner->getKey(),
            'target_tutor_profile_id' => $profile->getKey(),
            'request_type' => 'DIRECT',
            'learning_mode' => 'ONLINE',
            'status' => 'MATCHED',
        ])->save();
        $contract = Contract::query()->create([
            'request_id' => $request->getKey(),
            'tutor_profile_id' => $profile->getKey(),
            'status' => Contract::STATUS_CONFIRMED,
        ]);
        $class = new TutoringClass;
        $class->forceFill([
            'contract_id' => $contract->getKey(),
            'class_name' => 'Lớp rollback',
            'start_date' => now()->toDateString(),
            'status' => TutoringClass::STATUS_ACTIVE,
        ])->save();

        try {
            DB::transaction(function () use ($learner, $tutor, $contract, $class): void {
                resolve(SystemNotificationService::class)->classCreated(
                    $class,
                    $contract,
                    $learner->getKey(),
                    $tutor->getKey()
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('notifications', 0);
    }

    private function createUser(
        string $email,
        string $name,
        bool $isAdmin = false,
        string $status = User::STATUS_ACTIVE
    ): User {
        $user = new User;
        $user->forceFill([
            'email' => $email,
            'full_name' => $name,
            'is_admin' => $isAdmin,
            'status' => $status,
        ])->save();

        return $user;
    }

    private function createNotification(
        User $user,
        string $title,
        bool $read = false,
        string $type = 'TEST',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): Notification
    {
        $notification = new Notification;
        $notification->forceFill([
            'user_id' => $user->getKey(),
            'notification_type' => $type,
            'title' => $title,
            'message' => 'Nội dung '.$title,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'is_read' => $read,
            'read_at' => $read ? now() : null,
        ])->save();

        return $notification;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('email')->unique();
            $table->string('full_name');
            $table->string('avatar_url')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default(User::STATUS_ACTIVE);
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
            $table->string('approval_status')->default(TutorProfile::STATUS_PENDING);
            $table->timestamps();
        });
        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_tutor_profile_id')->nullable();
            $table->string('request_type');
            $table->string('learning_mode');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->text('message')->nullable();
            $table->decimal('proposed_fee', 12, 2)->nullable();
            $table->string('status');
            $table->dateTime('applied_at');
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id')->unique();
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id')->unique();
            $table->string('class_name')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->bigIncrements('verification_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status');
            $table->timestamps();
        });
    }
}
