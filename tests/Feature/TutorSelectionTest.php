<?php

namespace Tests\Feature;

use App\Exceptions\TutorSelectionException;
use App\Models\Contract;
use App\Models\IdentityVerification;
use App\Models\TutorApplication;
use App\Models\TutorProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Services\DirectTutoringRequestService;
use App\Services\TutorSelectionService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TutorSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_selects_pending_application_and_creates_pending_contract_with_expected_fee(): void
    {
        [$owner, $requestId, $applicationId, $profileId] = $this->selectionContext();

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertRedirect(route('my-requests.applications.index', $requestId))
            ->assertSessionHas('success', 'Đã chọn gia sư thành công.');

        $this->assertDatabaseHas('tutor_applications', [
            'application_id' => $applicationId,
            'status' => TutorApplication::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $requestId,
            'status' => TutoringRequest::STATUS_MATCHED,
        ]);
        $this->assertDatabaseHas('request_status_history', [
            'request_id' => $requestId,
            'initiated_by_user_id' => $owner->getKey(),
            'old_status' => TutoringRequest::STATUS_OPEN,
            'new_status' => TutoringRequest::STATUS_MATCHED,
        ]);
        $this->assertDatabaseHas('contracts', [
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'agreed_fee' => 180000,
            'agreed_fee_type' => 'HOURLY',
            'payment_method' => null,
            'learning_mode' => 'ONLINE',
            'start_date' => null,
            'end_date' => null,
            'terms' => null,
            'status' => Contract::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('contract_confirmations', 0);
        $this->assertDatabaseCount('tutoring_classes', 0);
    }

    public function test_proposed_fee_takes_priority_over_expected_fee(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext(proposedFee: 235000);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertRedirect(route('my-requests.applications.index', $requestId));

        $this->assertDatabaseHas('contracts', [
            'request_id' => $requestId,
            'agreed_fee' => 235000,
            'status' => Contract::STATUS_PENDING,
        ]);
    }

    public function test_only_other_pending_applications_are_rejected(): void
    {
        [$owner, $requestId, $selectedId] = $this->selectionContext();
        $pendingId = $this->createTutorApplication($requestId, $this->createTutor('Gia sư chờ')->getKey());
        $rejectedId = $this->createTutorApplication($requestId, $this->createTutor('Gia sư đã từ chối')->getKey(), 'REJECTED');
        $expiredId = $this->createTutorApplication($requestId, $this->createTutor('Gia sư hết hiệu lực')->getKey(), 'EXPIRED');

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $selectedId]))
            ->assertRedirect(route('my-requests.applications.index', $requestId));

        $this->assertSame(TutorApplication::STATUS_ACCEPTED, DB::table('tutor_applications')->where('application_id', $selectedId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_REJECTED, DB::table('tutor_applications')->where('application_id', $pendingId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_REJECTED, DB::table('tutor_applications')->where('application_id', $rejectedId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_EXPIRED, DB::table('tutor_applications')->where('application_id', $expiredId)->value('status'));
        $this->assertSame(1, DB::table('tutor_applications')->where('request_id', $requestId)->where('status', TutorApplication::STATUS_ACCEPTED)->count());
    }

    public function test_guest_other_learner_applicant_and_admin_cannot_select(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext();
        $otherLearner = $this->createUser('Người học khác');
        $applicant = User::query()->findOrFail(
            DB::table('tutor_profiles')
                ->where('tutor_profile_id', DB::table('tutor_applications')->where('application_id', $applicationId)->value('tutor_profile_id'))
                ->value('user_id')
        );
        $admin = $this->createUser('Quản trị viên', true);
        $route = route('my-requests.applications.select', [$requestId, $applicationId]);

        $this->patch($route)->assertRedirect(route('login'));
        $this->actingAs($otherLearner)->patch($route)->assertForbidden();
        $this->actingAs($applicant)->patch($route)->assertForbidden();
        $this->actingAs($admin)->patch($route)->assertForbidden();

        $this->assertSame(TutoringRequest::STATUS_OPEN, DB::table('tutoring_requests')->where('request_id', $requestId)->value('status'));
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_application_from_another_request_returns_not_found(): void
    {
        [$owner, $requestId] = $this->selectionContext();
        $otherRequestId = $this->createRequest($owner);
        $otherApplicationId = $this->createTutorApplication($otherRequestId, $this->createTutor('Gia sư yêu cầu khác')->getKey());

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $otherApplicationId]))
            ->assertNotFound();

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_stale_or_double_submit_does_not_create_a_second_contract(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext();
        $route = route('my-requests.applications.select', [$requestId, $applicationId]);

        $this->actingAs($owner)->patch($route)->assertSessionHasNoErrors();
        $this->actingAs($owner)
            ->patch($route)
            ->assertRedirect()
            ->assertSessionHas('error', 'Yêu cầu học không còn ở trạng thái chờ chọn gia sư. Vui lòng tải lại trang.');

        $this->assertDatabaseCount('contracts', 1);
        $this->assertSame(1, DB::table('tutor_applications')->where('request_id', $requestId)->where('status', TutorApplication::STATUS_ACCEPTED)->count());
    }

    public function test_non_pending_application_and_expired_request_are_blocked(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext(applicationStatus: TutorApplication::STATUS_REJECTED);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas('error', 'Hồ sơ ứng tuyển này không còn ở trạng thái chờ xem xét.');

        DB::table('tutor_applications')->where('application_id', $applicationId)->update(['status' => TutorApplication::STATUS_PENDING]);
        DB::table('tutoring_requests')->where('request_id', $requestId)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas('error', 'Yêu cầu học đã hết hạn nên không thể chọn gia sư.');

        $this->assertDatabaseCount('contracts', 0);
        $this->assertSame(TutoringRequest::STATUS_OPEN, DB::table('tutoring_requests')->where('request_id', $requestId)->value('status'));
    }

    public function test_existing_contract_blocks_selection_without_duplicate(): void
    {
        [$owner, $requestId, $applicationId, $profileId] = $this->selectionContext();
        $this->createContract($requestId, $profileId);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas('error', 'Yêu cầu học này đã có hợp đồng. Hệ thống không tạo hợp đồng trùng lặp.');

        $this->assertDatabaseCount('contracts', 1);
        $this->assertSame(TutorApplication::STATUS_PENDING, DB::table('tutor_applications')->where('application_id', $applicationId)->value('status'));
    }

    public function test_existing_accepted_application_blocks_a_second_acceptance(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext();
        $acceptedId = $this->createTutorApplication(
            $requestId,
            $this->createTutor('Gia sư đã được chọn trước')->getKey(),
            TutorApplication::STATUS_ACCEPTED
        );

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas('error', 'Yêu cầu học này đã có gia sư được chọn. Hệ thống không thể chọn thêm gia sư khác.');

        $this->assertSame(TutorApplication::STATUS_PENDING, DB::table('tutor_applications')->where('application_id', $applicationId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_ACCEPTED, DB::table('tutor_applications')->where('application_id', $acceptedId)->value('status'));
        $this->assertSame(1, DB::table('tutor_applications')->where('request_id', $requestId)->where('status', TutorApplication::STATUS_ACCEPTED)->count());
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_disabled_tutor_application_cannot_be_selected_for_a_new_contract(): void
    {
        [$owner, $requestId, $applicationId, $profileId] = $this->selectionContext();
        $tutorUserId = (int) DB::table('tutor_profiles')
            ->where('tutor_profile_id', $profileId)
            ->value('user_id');
        DB::table('users')
            ->where('user_id', $tutorUserId)
            ->update(['status' => User::STATUS_DISABLED]);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas(
                'error',
                'Tài khoản gia sư đã bị vô hiệu hóa nên không thể tạo hợp đồng mới.'
            );

        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseHas('tutor_applications', [
            'application_id' => $applicationId,
            'status' => TutorApplication::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $requestId,
            'status' => TutoringRequest::STATUS_OPEN,
        ]);
    }

    public function test_disabled_tutor_application_stays_visible_with_an_unavailable_selection_action(): void
    {
        [$owner, $requestId, $applicationId, $profileId] = $this->selectionContext();
        $tutorUserId = (int) DB::table('tutor_profiles')
            ->where('tutor_profile_id', $profileId)
            ->value('user_id');
        DB::table('users')
            ->where('user_id', $tutorUserId)
            ->update(['status' => User::STATUS_DISABLED]);
        $selectionAction = route('my-requests.applications.select', [$requestId, $applicationId]);

        foreach ([
            route('my-requests.applications.index', $requestId),
            route('my-requests.applications.show', [$requestId, $applicationId]),
        ] as $page) {
            $response = $this->actingAs($owner)->get($page);

            $response
                ->assertOk()
                ->assertSeeText('Gia sư được chọn')
                ->assertSeeText('Không thể chọn')
                ->assertSee('data-select-tutor-disabled', false)
                ->assertSee('disabled', false)
                ->assertDontSee('data-select-tutor-action="'.$selectionAction.'"', false)
                ->assertDontSee('data-select-tutor-open', false);
        }

        $this->assertDatabaseHas('tutor_applications', [
            'application_id' => $applicationId,
            'status' => TutorApplication::STATUS_PENDING,
        ]);
    }

    public function test_disabled_tutor_does_not_affect_another_active_application_action(): void
    {
        [$owner, $requestId, $disabledApplicationId, $disabledProfileId] = $this->selectionContext();
        $activeProfile = $this->createTutor('Gia sư váº«n hoạt động');
        $activeApplicationId = $this->createTutorApplication($requestId, $activeProfile->getKey());
        $disabledTutorUserId = (int) DB::table('tutor_profiles')
            ->where('tutor_profile_id', $disabledProfileId)
            ->value('user_id');
        DB::table('users')
            ->where('user_id', $disabledTutorUserId)
            ->update(['status' => User::STATUS_DISABLED]);

        $response = $this->actingAs($owner)
            ->get(route('my-requests.applications.index', $requestId));

        $response
            ->assertOk()
            ->assertSeeText('Không thể chọn')
            ->assertSee(
                'data-select-tutor-action="'.route('my-requests.applications.select', [$requestId, $activeApplicationId]).'"',
                false
            )
            ->assertDontSee(
                'data-select-tutor-action="'.route('my-requests.applications.select', [$requestId, $disabledApplicationId]).'"',
                false
            );

        $this->assertSame(1, substr_count($response->getContent(), 'data-select-tutor-open'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-select-tutor-disabled'));
        $this->assertDatabaseCount('tutor_applications', 2);
    }

    public function test_overlapping_active_class_schedule_blocks_selection_and_rolls_back(): void
    {
        [$owner, $requestId, $applicationId, $profileId, $slotId] = $this->selectionContext();
        $otherOwner = $this->createUser('Chủ lớp hiện tại');
        $otherRequestId = $this->createRequest($otherOwner, expectedFee: 200000, start: '19:00:00', end: '21:00:00');
        $contractId = $this->createContract($otherRequestId, $profileId, 'CONFIRMED');
        $classId = DB::table('tutoring_classes')->insertGetId([
            'contract_id' => $contractId,
            'class_name' => 'Lớp đang dạy',
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $overlappingSlotId = $this->createTimeSlot('19:00:00', '21:00:00');
        DB::table('class_schedules')->insert([
            'class_id' => $classId,
            'time_slot_id' => $overlappingSlotId,
            'day_of_week' => 2,
            'effective_from' => now()->subWeek()->toDateString(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $applicationId]))
            ->assertSessionHas('error', 'Khung giờ này không còn khả dụng vì bạn đã có lớp học khác trùng lịch.');

        $this->assertSame(TutoringRequest::STATUS_OPEN, DB::table('tutoring_requests')->where('request_id', $requestId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_PENDING, DB::table('tutor_applications')->where('application_id', $applicationId)->value('status'));
        $this->assertSame(1, DB::table('contracts')->count());
        $this->assertSame($slotId, (int) DB::table('request_schedules')->where('request_id', $requestId)->value('time_slot_id'));
    }

    public function test_contract_creation_failure_rolls_back_every_status_change(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext();
        $otherApplicationId = $this->createTutorApplication($requestId, $this->createTutor('Gia sư thứ hai')->getKey());

        $this->app->bind(TutorSelectionService::class, fn () => new class(
            resolve(SystemNotificationService::class)
        ) extends TutorSelectionService
        {
            protected function createContract(
                TutoringRequest $tutoringRequest,
                TutorApplication $application,
                string $effectiveFee
            ): Contract {
                throw new RuntimeException('Giả lập lỗi tạo contract.');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->patch(route('my-requests.applications.select', [$requestId, $applicationId]));

            $this->fail('Expected the simulated contract failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Giả lập lỗi tạo contract.', $exception->getMessage());
        }

        $this->assertSame(TutoringRequest::STATUS_OPEN, DB::table('tutoring_requests')->where('request_id', $requestId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_PENDING, DB::table('tutor_applications')->where('application_id', $applicationId)->value('status'));
        $this->assertSame(TutorApplication::STATUS_PENDING, DB::table('tutor_applications')->where('application_id', $otherApplicationId)->value('status'));
        $this->assertDatabaseCount('request_status_history', 0);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_index_and_detail_share_one_modal_and_the_same_backend_action(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext();
        $action = route('my-requests.applications.select', [$requestId, $applicationId]);

        foreach ([
            route('my-requests.applications.index', $requestId),
            route('my-requests.applications.show', [$requestId, $applicationId]),
        ] as $page) {
            $response = $this->actingAs($owner)->get($page);

            $response
                ->assertOk()
                ->assertSeeText('Chọn gia sư')
                ->assertSeeText('Chọn gia sư này?')
                ->assertSee('data-select-tutor-action="'.$action.'"', false)
                ->assertSee('data-select-tutor-dialog', false)
                ->assertSeeText('Đồng ý mức học phí dự kiến')
                ->assertSeeText('Xác nhận chọn');

            $this->assertSame(1, substr_count($response->getContent(), '<dialog'));
        }
    }

    public function test_modal_payload_shows_expected_and_proposed_fee_without_null_copy(): void
    {
        [$owner, $requestId, $applicationId] = $this->selectionContext(proposedFee: 225000);

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', $requestId))
            ->assertOk()
            ->assertSee('data-expected-fee="180.000 VNĐ/giờ"', false)
            ->assertSee('data-proposed-fee="225.000 VNĐ/giờ"', false)
            ->assertSee('data-has-proposed-fee="true"', false)
            ->assertSeeText('Học phí dự kiến của yêu cầu')
            ->assertSeeText('Học phí gia sư đề xuất')
            ->assertDontSeeText('Không đề xuất');
    }

    public function test_matched_ui_updates_counts_badges_and_removes_all_selection_ctas(): void
    {
        [$owner, $requestId, $selectedId] = $this->selectionContext();
        $otherId = $this->createTutorApplication($requestId, $this->createTutor('Gia sư không được chọn')->getKey());

        $this->actingAs($owner)
            ->patch(route('my-requests.applications.select', [$requestId, $selectedId]));

        $contractId = (int) DB::table('contracts')
            ->where('request_id', $requestId)
            ->value('contract_id');
        $index = $this->actingAs($owner)->get(route('my-requests.applications.index', $requestId));
        $index
            ->assertOk()
            ->assertSeeText('Đã ghép gia sư')
            ->assertSeeText('Xem hợp đồng')
            ->assertSee('href="'.route('contracts.show', $contractId).'"', false)
            ->assertSeeText('Đã chọn')
            ->assertSeeText('Không được chọn')
            ->assertSeeInOrder([
                'Tổng số gia sư ứng tuyển', '2',
                'Đang chờ xem xét', '0',
                'Đã chọn', '1',
            ])
            ->assertDontSee('data-select-tutor-open', false);

        $this->actingAs($owner)
            ->get(route('my-requests.applications.show', [$requestId, $selectedId]))
            ->assertOk()
            ->assertSeeText('Đã chọn')
            ->assertDontSee('data-select-tutor-open', false);

        $this->actingAs($owner)
            ->get(route('my-requests.applications.show', [$requestId, $otherId]))
            ->assertOk()
            ->assertSeeText('Không được chọn')
            ->assertDontSee('data-select-tutor-open', false);
    }

    public function test_applications_page_hides_contract_cta_when_matched_request_has_no_contract(): void
    {
        [$owner, $requestId] = $this->selectionContext();

        DB::table('tutoring_requests')
            ->where('request_id', $requestId)
            ->update(['status' => TutoringRequest::STATUS_MATCHED]);

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', $requestId))
            ->assertOk()
            ->assertSeeText('Đã ghép gia sư')
            ->assertDontSeeText('Xem hợp đồng');

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_disabled_tutor_is_not_available_for_a_new_direct_request(): void
    {
        $learner = $this->createUser('Người học gửi yêu cầu');
        $learner->phone = '0900000001';
        $learner->save();
        DB::table('identity_verifications')->insert([
            'user_id' => $learner->getKey(),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profile = $this->createTutor('Gia sư đã bị vô hiệu hóa');
        DB::table('users')
            ->where('user_id', $profile->user_id)
            ->update(['status' => User::STATUS_DISABLED]);

        $this->actingAs($learner)
            ->get(route('requests.direct.create', $profile))
            ->assertNotFound();

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_disabled_tutor_cannot_accept_or_reject_an_existing_direct_request(): void
    {
        [$learner, $tutor, $requestId] = $this->directRequestContext();
        DB::table('users')
            ->where('user_id', $tutor->getKey())
            ->update(['status' => User::STATUS_DISABLED]);
        $service = resolve(DirectTutoringRequestService::class);

        foreach ([
            fn () => $service->accept($requestId, (int) $tutor->getKey(), true),
            fn () => $service->reject($requestId, (int) $tutor->getKey(), 'Không thể nhận lớp.'),
        ] as $action) {
            try {
                $action();
                $this->fail('Expected a disabled tutor to be rejected.');
            } catch (TutorSelectionException) {
                // The account gate must stop both state-changing actions.
            }
        }

        $this->assertDatabaseHas('users', [
            'user_id' => $learner->getKey(),
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $requestId,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseCount('request_status_history', 0);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_reactivated_tutor_can_continue_a_valid_direct_request_action(): void
    {
        [, $tutor, $requestId] = $this->directRequestContext();
        DB::table('users')
            ->where('user_id', $tutor->getKey())
            ->update(['status' => User::STATUS_DISABLED]);

        try {
            resolve(DirectTutoringRequestService::class)
                ->reject($requestId, (int) $tutor->getKey(), null);
            $this->fail('Expected a disabled tutor to be rejected.');
        } catch (TutorSelectionException) {
            $this->assertDatabaseHas('tutoring_requests', [
                'request_id' => $requestId,
                'status' => 'PENDING',
            ]);
        }

        DB::table('users')
            ->where('user_id', $tutor->getKey())
            ->update(['status' => User::STATUS_ACTIVE]);

        resolve(DirectTutoringRequestService::class)
            ->reject($requestId, (int) $tutor->getKey(), 'Lịch dạy không phù hợp.');

        $this->assertDatabaseHas('tutoring_requests', [
            'request_id' => $requestId,
            'status' => TutoringRequest::STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('request_status_history', [
            'request_id' => $requestId,
            'initiated_by_user_id' => $tutor->getKey(),
            'old_status' => 'PENDING',
            'new_status' => TutoringRequest::STATUS_REJECTED,
        ]);
        $this->assertDatabaseCount('contracts', 0);
    }

    /** @return array{User, int, int, int, int} */
    private function selectionContext(
        ?int $proposedFee = null,
        string $applicationStatus = TutorApplication::STATUS_PENDING
    ): array {
        $owner = $this->createUser('Chủ yêu cầu');
        $requestId = $this->createRequest($owner);
        $slotId = (int) DB::table('request_schedules')->where('request_id', $requestId)->value('time_slot_id');
        $profile = $this->createTutor('Gia sư được chọn');
        $applicationId = $this->createTutorApplication(
            $requestId,
            $profile->getKey(),
            $applicationStatus,
            $proposedFee
        );

        return [$owner, $requestId, $applicationId, (int) $profile->getKey(), $slotId];
    }

    /** @return array{User, User, int} */
    private function directRequestContext(): array
    {
        $learner = $this->createUser('Người học gửi yêu cầu trực tiếp');
        $profile = $this->createTutor('Gia sư nhận yêu cầu trực tiếp');
        $requestId = $this->createRequest($learner);
        DB::table('tutoring_requests')
            ->where('request_id', $requestId)
            ->update([
                'target_tutor_profile_id' => $profile->getKey(),
                'request_type' => 'DIRECT',
                'status' => 'PENDING',
            ]);

        return [
            $learner,
            User::query()->findOrFail($profile->user_id),
            $requestId,
        ];
    }

    private function createUser(string $name, bool $isAdmin = false): User
    {
        $number = DB::table('users')->count() + 1;
        $id = DB::table('users')->insertGetId([
            'google_id' => 'selection-google-'.$number,
            'email' => 'selection-'.$number.'@example.test',
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createTutor(string $name): TutorProfile
    {
        $user = $this->createUser($name);
        $id = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->getKey(),
            'headline' => 'Gia sư nền tảng tận tâm',
            'bio' => 'Giới thiệu gia sư.',
            'education_summary' => 'Cử nhân Sư phạm',
            'teaching_experience' => 'Ba năm giảng dạy.',
            'hourly_rate' => 180000,
            'supports_online' => true,
            'supports_offline' => false,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
            'submitted_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);

        return TutorProfile::query()->findOrFail($id);
    }

    private function createRequest(
        User $owner,
        int $expectedFee = 180000,
        string $start = '18:00:00',
        string $end = '20:00:00'
    ): int {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'Kế toán',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => 'Cơ bản',
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('tutoring_requests')->insertGetId([
            'user_id' => $owner->getKey(),
            'target_tutor_profile_id' => null,
            'subject_level_id' => $levelId,
            'ward_id' => null,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'ANY',
            'address_detail' => null,
            'expected_fee' => $expectedFee,
            'fee_type' => 'HOURLY',
            'description' => 'Cần gia sư hỗ trợ kiến thức cơ bản.',
            'status' => TutoringRequest::STATUS_OPEN,
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subHours(4),
            'updated_at' => now(),
        ]);
        $slotId = $this->createTimeSlot($start, $end);
        DB::table('request_schedules')->insert([
            'request_id' => $requestId,
            'time_slot_id' => $slotId,
            'day_of_week' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $requestId;
    }

    private function createTimeSlot(string $start, string $end): int
    {
        return DB::table('time_slots')->insertGetId([
            'start_time' => $start,
            'end_time' => $end,
            'slot_name' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTutorApplication(
        int $requestId,
        int $profileId,
        string $status = TutorApplication::STATUS_PENDING,
        ?int $proposedFee = null
    ): int {
        return DB::table('tutor_applications')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'message' => 'Rất mong được đồng hành cùng bạn trong quá trình học.',
            'proposed_fee' => $proposedFee,
            'status' => $status,
            'applied_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
    }

    private function createContract(int $requestId, int $profileId, string $status = Contract::STATUS_PENDING): int
    {
        return DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'agreed_fee' => 180000,
            'learning_mode' => 'ONLINE',
            'start_date' => null,
            'end_date' => null,
            'terms' => null,
            'status' => $status,
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
        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_name');
            $table->timestamps();
        });
        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_name');
            $table->timestamps();
        });
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->text('education_summary')->nullable();
            $table->text('teaching_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status')->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->bigIncrements('verification_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status')->default(IdentityVerification::STATUS_PENDING);
            $table->timestamps();
        });
        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();
        });
        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
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
            $table->timestamp('expires_at')->nullable();
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
        Schema::create('request_schedules', function (Blueprint $table): void {
            $table->bigIncrements('request_schedule_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();
        });
        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->text('message')->nullable();
            $table->decimal('proposed_fee', 12, 2)->nullable();
            $table->string('status')->default(TutorApplication::STATUS_PENDING);
            $table->timestamp('applied_at');
            $table->timestamp('updated_at');
            $table->unique(['request_id', 'tutor_profile_id']);
        });
        Schema::create('request_status_history', function (Blueprint $table): void {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->string('reason', 500)->nullable();
            $table->timestamp('changed_at');
        });
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id')->unique();
            $table->unsignedBigInteger('tutor_profile_id');
            $table->decimal('agreed_fee', 12, 2);
            $table->string('agreed_fee_type', 20)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('learning_mode');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default(Contract::STATUS_PENDING);
            $table->timestamps();
        });
        Schema::create('contract_confirmations', function (Blueprint $table): void {
            $table->bigIncrements('confirmation_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('user_id');
            $table->string('confirmation_status');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id')->unique();
            $table->string('class_name')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
        Schema::create('class_schedules', function (Blueprint $table): void {
            $table->bigIncrements('class_schedule_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }
}
