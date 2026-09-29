<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MyRequestTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_guest_cannot_view_the_request_list_or_detail(): void
    {
        $this->get(route('requests.create'))
            ->assertRedirect(route('login'));

        $this->get(route('my-requests.index'))
            ->assertRedirect(route('login'));

        $this->get(route('my-requests.show', 999))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_identity_verification_cannot_open_or_submit_request_creation(): void
    {
        $learner = $this->createUser('unverified', ['identity_status' => null]);

        $this->actingAs($learner)
            ->get(route('requests.create'))
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas('error', 'Bạn cần xác minh danh tính trước khi tạo yêu cầu học.');

        $this->actingAs($learner)
            ->post(route('requests.store'), $this->createStorePayload('unverified'))
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas('error', 'Bạn cần xác minh danh tính trước khi tạo yêu cầu học.');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_user_with_pending_identity_verification_cannot_create_request(): void
    {
        $learner = $this->createUser('pending', [
            'identity_status' => IdentityVerification::STATUS_PENDING,
        ]);

        $this->actingAs($learner)
            ->get(route('requests.create'))
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas('error', 'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.');

        $this->actingAs($learner)
            ->post(route('requests.store'), $this->createStorePayload('pending'))
            ->assertRedirect(route('identity-verification.show'));

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_user_with_rejected_identity_verification_cannot_create_request(): void
    {
        $learner = $this->createUser('rejected', [
            'identity_status' => IdentityVerification::STATUS_REJECTED,
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $this->createStorePayload('rejected'))
            ->assertRedirect(route('identity-verification.show'))
            ->assertSessionHas(
                'error',
                'Hồ sơ xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật và gửi lại.'
            );

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_authenticated_user_can_view_the_public_request_form_with_catalog_data(): void
    {
        $learner = $this->createUser('learner');
        $this->createRequest($learner, [
            'subject_name' => 'Tin hoc',
            'level_name' => 'Nhap mon',
            'province_name' => 'Da Nang',
            'ward_name' => 'Hai Chau',
            'with_schedule' => true,
        ]);

        $subjectId = DB::table('subjects')->where('subject_name', 'Tin hoc')->value('subject_id');
        $provinceId = DB::table('provinces')->where('province_name', 'Da Nang')->value('province_id');

        $this->actingAs($learner)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSeeText('Tạo yêu cầu học')
            ->assertSeeText('Tin hoc')
            ->assertSeeText('Nhap mon')
            ->assertSeeText('Da Nang')
            ->assertSeeText('Hai Chau')
            ->assertSee('data-subject-id="'.$subjectId.'"', false)
            ->assertSee('data-province-id="'.$provinceId.'"', false)
            ->assertSee('name="schedule_time_slot[]"', false)
            ->assertSee('value="ONLINE"', false)
            ->assertSee('value="OFFLINE"', false)
            ->assertSee('name="preferred_tutor_gender"', false)
            ->assertSee('value="ANY"', false)
            ->assertSee('value="MALE"', false)
            ->assertSee('value="FEMALE"', false)
            ->assertSee('name="fee_type"', false)
            ->assertSee('value="HOURLY"', false)
            ->assertSee('value="MONTHLY"', false)
            ->assertSee('data-request-create-fee-suffix', false)
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('requests.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="expires_at"', false)
            ->assertSee('class="button request-create-submit" type="submit"', false)
            ->assertDontSeeText('Chức năng lưu yêu cầu sẽ được hoàn thiện ở bước tiếp theo.');
    }

    public function test_any_tutor_gender_preference_is_stored(): void
    {
        $this->assertSame('ANY', $this->storePublicRequestWith([
            'preferred_tutor_gender' => 'ANY',
        ])->preferred_tutor_gender);
    }

    public function test_male_tutor_gender_preference_is_stored(): void
    {
        $this->assertSame('MALE', $this->storePublicRequestWith([
            'preferred_tutor_gender' => 'MALE',
        ])->preferred_tutor_gender);
    }

    public function test_female_tutor_gender_preference_is_stored(): void
    {
        $this->assertSame('FEMALE', $this->storePublicRequestWith([
            'preferred_tutor_gender' => 'FEMALE',
        ])->preferred_tutor_gender);
    }

    public function test_invalid_tutor_gender_preference_is_rejected(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'preferred_tutor_gender' => 'INVALID',
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('preferred_tutor_gender');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_hourly_fee_type_is_stored(): void
    {
        $this->assertSame('HOURLY', $this->storePublicRequestWith([
            'fee_type' => 'HOURLY',
        ])->fee_type);
    }

    public function test_monthly_fee_type_is_stored(): void
    {
        $this->assertSame('MONTHLY', $this->storePublicRequestWith([
            'fee_type' => 'MONTHLY',
        ])->fee_type);
    }

    public function test_invalid_fee_type_is_rejected(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'fee_type' => 'WEEKLY',
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('fee_type');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_guest_cannot_create_a_public_request(): void
    {
        $this->post(route('requests.store'), [])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_authenticated_user_creates_an_open_public_request_with_server_controlled_fields(): void
    {
        $learner = $this->createUser('learner');
        $otherUser = $this->createUser('other');
        $payload = $this->createStorePayload();
        $subjectName = DB::table('subjects')
            ->where('subject_id', $payload['subject_id'])
            ->value('subject_name');

        $response = $this->actingAs($learner)->post(route('requests.store'), array_merge($payload, [
            'user_id' => $otherUser->user_id,
            'request_type' => 'DIRECT',
            'target_tutor_profile_id' => 987654,
            'status' => 'MATCHED',
        ]));

        $response
            ->assertRedirect(route('my-requests.index'))
            ->assertSessionHas('success', 'Yêu cầu học đã được đăng thành công.');

        $storedRequest = TutoringRequest::query()->sole();

        $this->assertSame((int) $learner->user_id, (int) $storedRequest->user_id);
        $this->assertSame('PUBLIC', $storedRequest->request_type);
        $this->assertNull($storedRequest->target_tutor_profile_id);
        $this->assertSame('OPEN', $storedRequest->status);
        $this->assertSame((int) $payload['subject_level_id'], (int) $storedRequest->subject_level_id);
        $this->assertSame((int) $payload['ward_id'], (int) $storedRequest->ward_id);
        $this->assertSame('OFFLINE', $storedRequest->learning_mode);
        $this->assertSame('Số 12 đường Test', $storedRequest->address_detail);
        $this->assertSame('Cần củng cố kiến thức nền tảng.', $storedRequest->description);
        $this->assertSame($payload['expires_at'], $storedRequest->expires_at->toDateTimeString());
        $this->assertDatabaseCount('request_schedules', 1);
        $this->assertDatabaseHas('request_schedules', [
            'request_id' => $storedRequest->request_id,
            'time_slot_id' => $payload['schedule_time_slot'][0],
            'day_of_week' => $payload['schedule_day'][0],
        ]);

        $this->get(route('my-requests.index'))
            ->assertOk()
            ->assertSeeText('Yêu cầu học đã được đăng thành công.')
            ->assertSeeText($subjectName)
            ->assertSeeText('Công khai')
            ->assertSeeText('Đang tìm gia sư');

        $this->get(route('my-requests.index', ['status' => 'active']))
            ->assertOk()
            ->assertSeeText($subjectName);
    }

    public function test_public_request_stores_all_selected_schedules_and_existing_fields(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();
        $secondTimeSlotId = $this->createTimeSlot('second');
        $payload = array_merge($payload, [
            'preferred_tutor_gender' => 'FEMALE',
            'fee_type' => 'MONTHLY',
            'schedule_day' => [1, 3, 7],
            'schedule_time_slot' => [
                $payload['schedule_time_slot'][0],
                $secondTimeSlotId,
                $payload['schedule_time_slot'][0],
            ],
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertRedirect(route('my-requests.index'));

        $storedRequest = TutoringRequest::query()->sole();

        $this->assertSame('FEMALE', $storedRequest->preferred_tutor_gender);
        $this->assertSame('MONTHLY', $storedRequest->fee_type);
        $this->assertDatabaseCount('request_schedules', 3);

        foreach (array_keys($payload['schedule_day']) as $index) {
            $this->assertDatabaseHas('request_schedules', [
                'request_id' => $storedRequest->request_id,
                'time_slot_id' => $payload['schedule_time_slot'][$index],
                'day_of_week' => $payload['schedule_day'][$index],
            ]);
        }
    }

    public function test_schedule_day_must_be_between_one_and_seven(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'schedule_day' => [8],
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('schedules.0.day_of_week');

        $this->assertDatabaseCount('tutoring_requests', 0);
        $this->assertDatabaseCount('request_schedules', 0);
    }

    public function test_schedule_time_slot_must_exist_and_be_active(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, [
                'schedule_time_slot' => [999999],
            ]))
            ->assertSessionHasErrors('schedules');

        $inactiveTimeSlotId = $this->createTimeSlot('inactive', ['status' => 'INACTIVE']);

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, [
                'schedule_time_slot' => [$inactiveTimeSlotId],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertDatabaseCount('tutoring_requests', 0);
        $this->assertDatabaseCount('request_schedules', 0);
    }

    public function test_public_request_requires_at_least_one_schedule(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();
        unset($payload['schedule_day'], $payload['schedule_time_slot']);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('schedules');

        $this->assertDatabaseCount('tutoring_requests', 0);
        $this->assertDatabaseCount('request_schedules', 0);
    }

    public function test_duplicate_schedule_pair_is_rejected_before_insert(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();
        $timeSlotId = $payload['schedule_time_slot'][0];
        $payload = array_merge($payload, [
            'schedule_day' => [2, 2],
            'schedule_time_slot' => [$timeSlotId, $timeSlotId],
        ]);

        $this->actingAs($learner)
            ->from(route('requests.create'))
            ->post(route('requests.store'), $payload)
            ->assertRedirect(route('requests.create'))
            ->assertSessionHasErrors('schedules')
            ->assertSessionHasInput('schedule_day', [2, 2])
            ->assertSessionHasInput('schedule_time_slot', [$timeSlotId, $timeSlotId]);

        $this->followingRedirects()
            ->from(route('requests.create'))
            ->post(route('requests.store'), $payload)
            ->assertOk()
            ->assertSeeText('Mỗi cặp ngày học và khung giờ chỉ được chọn một lần.')
            ->assertSee('id="schedules-error"', false);

        $this->assertDatabaseCount('tutoring_requests', 0);
        $this->assertDatabaseCount('request_schedules', 0);
    }

    public function test_client_request_id_cannot_attach_schedules_to_another_request(): void
    {
        $learner = $this->createUser('learner');
        $otherLearner = $this->createUser('other');
        $otherRequestId = $this->createRequest($otherLearner);
        $payload = array_merge($this->createStorePayload(), [
            'request_id' => $otherRequestId,
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertRedirect(route('my-requests.index'));

        $storedRequest = TutoringRequest::query()
            ->where('user_id', $learner->user_id)
            ->sole();

        $this->assertDatabaseMissing('request_schedules', [
            'request_id' => $otherRequestId,
        ]);
        $this->assertDatabaseHas('request_schedules', [
            'request_id' => $storedRequest->request_id,
            'time_slot_id' => $payload['schedule_time_slot'][0],
            'day_of_week' => $payload['schedule_day'][0],
        ]);
    }

    public function test_online_request_discards_all_client_location_values(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'learning_mode' => 'ONLINE',
            'province_id' => 999999,
            'ward_id' => 999999,
            'address_detail' => 'Địa chỉ không được lưu',
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertRedirect(route('my-requests.index'));

        $storedRequest = TutoringRequest::query()->sole();

        $this->assertSame('ONLINE', $storedRequest->learning_mode);
        $this->assertNull($storedRequest->ward_id);
        $this->assertNull($storedRequest->address_detail);
    }

    public function test_learning_mode_only_accepts_online_or_offline(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'learning_mode' => 'HYBRID',
        ]);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('learning_mode');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_nonexistent_and_mismatched_subject_levels_are_rejected(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload('first');

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, ['subject_level_id' => 999999]))
            ->assertSessionHasErrors('subject_level_id');

        $otherPayload = $this->createStorePayload('second');

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, [
                'subject_level_id' => $otherPayload['subject_level_id'],
            ]))
            ->assertSessionHasErrors('subject_level_id');

        DB::table('subjects')
            ->where('subject_id', $payload['subject_id'])
            ->update(['status' => 'INACTIVE']);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('subject_level_id');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_offline_request_requires_a_ward_and_address(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();
        $withoutWard = $payload;
        unset($withoutWard['ward_id']);

        $this->actingAs($learner)
            ->post(route('requests.store'), $withoutWard)
            ->assertSessionHasErrors('ward_id');

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, ['address_detail' => '   ']))
            ->assertSessionHasErrors('address_detail');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_offline_request_rejects_a_ward_from_another_province(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload('first');
        $otherPayload = $this->createStorePayload('second');

        $this->actingAs($learner)
            ->post(route('requests.store'), array_merge($payload, [
                'ward_id' => $otherPayload['ward_id'],
            ]))
            ->assertSessionHasErrors('ward_id');

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_expected_fee_must_be_positive_and_within_the_upper_bound(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();

        foreach ([0, -1, 10000001] as $invalidFee) {
            $this->actingAs($learner)
                ->post(route('requests.store'), array_merge($payload, ['expected_fee' => $invalidFee]))
                ->assertSessionHasErrors('expected_fee');
        }

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_expiration_must_be_a_future_datetime_within_seven_days(): void
    {
        $learner = $this->createUser('learner');
        $payload = $this->createStorePayload();

        foreach (['not-a-date', now()->subMinute()->toDateTimeString(), now()->addDays(8)->toDateTimeString()] as $invalidExpiry) {
            $this->actingAs($learner)
                ->post(route('requests.store'), array_merge($payload, ['expires_at' => $invalidExpiry]))
                ->assertSessionHasErrors('expires_at');
        }

        $this->assertDatabaseCount('tutoring_requests', 0);
    }

    public function test_validation_failure_keeps_old_input_and_renders_the_field_error(): void
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), [
            'preferred_tutor_gender' => 'FEMALE',
            'fee_type' => 'MONTHLY',
            'expected_fee' => 325000,
        ]);
        unset($payload['description']);

        $response = $this->actingAs($learner)
            ->from(route('requests.create'))
            ->post(route('requests.store'), $payload);

        $response
            ->assertRedirect(route('requests.create'))
            ->assertSessionHasErrors('description')
            ->assertSessionHasInput('subject_id', $payload['subject_id'])
            ->assertSessionHasInput('preferred_tutor_gender', 'FEMALE')
            ->assertSessionHasInput('fee_type', 'MONTHLY')
            ->assertSessionHasInput('expected_fee', 325000)
            ->assertSessionHasInput('schedule_day', $payload['schedule_day'])
            ->assertSessionHasInput('schedule_time_slot', $payload['schedule_time_slot']);

        $this->followingRedirects()
            ->from(route('requests.create'))
            ->post(route('requests.store'), $payload)
            ->assertOk()
            ->assertSeeText('Vui lòng mô tả nhu cầu học.')
            ->assertSee('value="'.$payload['subject_id'].'" selected', false)
            ->assertSee('value="'.$payload['ward_id'].'"', false)
            ->assertSee('value="FEMALE" required checked', false)
            ->assertSee('value="MONTHLY" required checked', false)
            ->assertSee('value="325000"', false)
            ->assertSee('value="'.$payload['schedule_day'][0].'" selected', false)
            ->assertSee('value="'.$payload['schedule_time_slot'][0].'" selected', false)
            ->assertSeeText('đ / tháng');
    }

    public function test_user_sees_only_their_requests_without_lazy_loading(): void
    {
        $learner = $this->createUser('learner');
        $otherLearner = $this->createUser('other');
        $requestId = $this->createRequest($learner, [
            'subject_name' => 'Toán của tôi',
            'level_name' => 'Lớp 10',
        ]);
        $emptyRequestId = $this->createRequest($learner, [
            'subject_name' => 'Ngữ văn chưa có ứng viên',
            'level_name' => 'Lớp 9',
        ]);
        $this->createRequest($otherLearner, [
            'subject_name' => 'Vật lý riêng của người khác',
            'level_name' => 'Lớp 11',
        ]);

        $this->createTutorApplication($requestId, 'first-applicant');
        $this->createTutorApplication($requestId, 'second-applicant');

        Model::preventLazyLoading();

        try {
            $this->actingAs($learner)
                ->get(route('my-requests.index'))
                ->assertOk()
                ->assertSeeText('Yêu cầu của tôi')
                ->assertSeeText('Toán của tôi · Lớp 10')
                ->assertSeeText('2 gia sư đã ứng tuyển')
                ->assertSeeText('Xem gia sư ứng tuyển (2)')
                ->assertSeeText('Xem gia sư ứng tuyển (0)')
                ->assertSee('href="'.route('my-requests.applications.index', $requestId).'"', false)
                ->assertSee('href="'.route('my-requests.applications.index', $emptyRequestId).'"', false)
                ->assertSee('href="'.route('my-requests.show', $requestId).'"', false)
                ->assertDontSeeText('Vật lý riêng của người khác');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_status_filters_use_the_expected_mapping(): void
    {
        $learner = $this->createUser('learner');
        $this->createRequest($learner, ['status' => 'OPEN', 'subject_name' => 'Môn OPEN']);
        $this->createRequest($learner, ['status' => 'PENDING', 'subject_name' => 'Môn PENDING']);
        $this->createRequest($learner, ['status' => 'MATCHED', 'subject_name' => 'Môn MATCHED']);
        $this->createRequest($learner, ['status' => 'EXPIRED', 'subject_name' => 'Môn EXPIRED']);

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'active']))
            ->assertOk()
            ->assertSeeText('Môn OPEN')
            ->assertSeeText('Môn PENDING')
            ->assertDontSeeText('Môn MATCHED')
            ->assertDontSeeText('Môn EXPIRED');

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'matched']))
            ->assertOk()
            ->assertSeeText('Môn MATCHED')
            ->assertDontSeeText('Môn OPEN');

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSeeText('Môn EXPIRED')
            ->assertDontSeeText('Môn PENDING');
    }

    public function test_expiration_sync_updates_only_eligible_owned_requests_and_is_idempotent(): void
    {
        $learner = $this->createUser('expiry-owner');
        $otherLearner = $this->createUser('expiry-other');
        $expiredPublicId = $this->createRequest($learner, [
            'status' => 'OPEN',
            'request_type' => 'PUBLIC',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'PUBLIC đã quá hạn',
        ]);
        $expiredDirectId = $this->createRequest($learner, [
            'status' => 'PENDING',
            'request_type' => 'DIRECT',
            'learning_mode' => 'ONLINE',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'DIRECT đã quá hạn',
        ]);
        $futurePublicId = $this->createRequest($learner, [
            'status' => 'OPEN',
            'expires_at' => now()->addDay(),
            'subject_name' => 'PUBLIC còn hạn',
        ]);
        $matchedId = $this->createRequest($learner, [
            'status' => 'MATCHED',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'MATCHED giữ nguyên',
        ]);
        $rejectedId = $this->createRequest($learner, [
            'status' => 'REJECTED',
            'request_type' => 'DIRECT',
            'learning_mode' => 'ONLINE',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'REJECTED giữ nguyên',
        ]);
        $alreadyExpiredId = $this->createRequest($learner, [
            'status' => 'EXPIRED',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'EXPIRED có sẵn',
        ]);
        $otherRequestId = $this->createRequest($otherLearner, [
            'status' => 'OPEN',
            'expires_at' => now()->subMinute(),
            'subject_name' => 'Yêu cầu của người khác',
        ]);

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSeeText('PUBLIC đã quá hạn')
            ->assertSeeText('DIRECT đã quá hạn')
            ->assertSeeText('EXPIRED có sẵn')
            ->assertDontSeeText('PUBLIC còn hạn')
            ->assertDontSeeText('MATCHED giữ nguyên')
            ->assertDontSeeText('REJECTED giữ nguyên')
            ->assertDontSeeText('Yêu cầu của người khác');

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'active']))
            ->assertOk()
            ->assertSeeText('PUBLIC còn hạn')
            ->assertDontSeeText('PUBLIC đã quá hạn')
            ->assertDontSeeText('DIRECT đã quá hạn');

        $this->actingAs($learner)
            ->get(route('my-requests.index'))
            ->assertOk()
            ->assertSeeText('PUBLIC đã quá hạn')
            ->assertSeeText('DIRECT đã quá hạn')
            ->assertSeeText('PUBLIC còn hạn')
            ->assertDontSeeText('Yêu cầu của người khác');

        $this->assertSame('EXPIRED', DB::table('tutoring_requests')->where('request_id', $expiredPublicId)->value('status'));
        $this->assertSame('EXPIRED', DB::table('tutoring_requests')->where('request_id', $expiredDirectId)->value('status'));
        $this->assertSame('OPEN', DB::table('tutoring_requests')->where('request_id', $futurePublicId)->value('status'));
        $this->assertSame('MATCHED', DB::table('tutoring_requests')->where('request_id', $matchedId)->value('status'));
        $this->assertSame('REJECTED', DB::table('tutoring_requests')->where('request_id', $rejectedId)->value('status'));
        $this->assertSame('EXPIRED', DB::table('tutoring_requests')->where('request_id', $alreadyExpiredId)->value('status'));
        $this->assertSame('OPEN', DB::table('tutoring_requests')->where('request_id', $otherRequestId)->value('status'));

        $this->assertDatabaseHas('request_status_history', [
            'request_id' => $expiredPublicId,
            'initiated_by_user_id' => null,
            'old_status' => 'OPEN',
            'new_status' => 'EXPIRED',
            'reason' => 'Yêu cầu đã hết thời hạn xử lý.',
        ]);
        $this->assertDatabaseHas('request_status_history', [
            'request_id' => $expiredDirectId,
            'initiated_by_user_id' => null,
            'old_status' => 'PENDING',
            'new_status' => 'EXPIRED',
            'reason' => 'Yêu cầu đã hết thời hạn xử lý.',
        ]);

        $this->assertSame(2, DB::table('request_status_history')->count());

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'expired']))
            ->assertOk();

        $this->assertSame(2, DB::table('request_status_history')->count());
    }

    public function test_invalid_filter_and_unknown_status_fall_back_safely(): void
    {
        $learner = $this->createUser('learner');
        $this->createRequest($learner, [
            'status' => 'UNEXPECTED',
            'subject_name' => 'Môn trạng thái lạ',
        ]);

        $this->actingAs($learner)
            ->get(route('my-requests.index', ['status' => 'not-valid']))
            ->assertOk()
            ->assertSeeText('Môn trạng thái lạ')
            ->assertSeeText('Trạng thái khác');
    }

    public function test_user_receives_403_for_another_users_request(): void
    {
        $learner = $this->createUser('learner');
        $otherLearner = $this->createUser('other');
        $otherRequestId = $this->createRequest($otherLearner);

        $this->actingAs($learner)
            ->get(route('my-requests.show', $otherRequestId))
            ->assertForbidden();
    }

    public function test_public_request_detail_shows_offline_schedule_and_application_cta(): void
    {
        $learner = $this->createUser('learner');
        $requestId = $this->createRequest($learner, [
            'request_type' => 'PUBLIC',
            'learning_mode' => 'OFFLINE',
            'address_detail' => 'Đường Tô Ngọc Vân',
            'ward_name' => 'Tam Bình',
            'province_name' => 'Hồ Chí Minh',
            'subject_name' => 'Toán',
            'level_name' => 'Lớp 10',
            'with_schedule' => true,
        ]);
        $this->createTutorApplication($requestId, 'applicant', [
            'full_name' => 'Gia sư Nguyễn An',
            'headline' => 'Gia sư Toán THPT',
            'proposed_fee' => 175000,
            'message' => 'Mình có thể đồng hành cùng bạn.',
            'status' => 'PENDING',
        ]);

        Model::preventLazyLoading();

        try {
            $this->actingAs($learner)
                ->get(route('my-requests.show', $requestId))
                ->assertOk()
                ->assertSeeText('Toán')
                ->assertSeeText('Lớp 10')
                ->assertDontSeeText('Đường Tô Ngọc Vân')
                ->assertSeeText('Tam Bình, Hồ Chí Minh')
                ->assertSeeText('Thứ 2')
                ->assertSeeText('18:00–20:00')
                ->assertSeeText('Xem gia sư ứng tuyển (1)')
                ->assertSee('href="'.route('my-requests.applications.index', $requestId).'"', false)
                ->assertDontSeeText('Gia sư Nguyễn An')
                ->assertDontSeeText('Mình có thể đồng hành cùng bạn.');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_direct_online_request_shows_target_tutor_without_location(): void
    {
        $learner = $this->createUser('learner');
        $targetTutorId = $this->createTutor('target', [
            'full_name' => 'Gia sư Trần Minh',
            'headline' => 'Đồng hành học tiếng Anh',
        ]);
        $requestId = $this->createRequest($learner, [
            'request_type' => 'DIRECT',
            'learning_mode' => 'ONLINE',
            'target_tutor_profile_id' => $targetTutorId,
            'address_detail' => 'Địa chỉ không được hiển thị',
            'ward_name' => 'Phường không dùng',
            'subject_name' => 'Tiếng Anh',
            'level_name' => 'Lớp 6',
            'status' => 'PENDING',
        ]);

        $this->actingAs($learner)
            ->get(route('my-requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Đã gửi trực tiếp')
            ->assertSeeText('Chờ gia sư phản hồi')
            ->assertSeeText('Trực tuyến')
            ->assertSeeText('Gia sư Trần Minh')
            ->assertSeeText('Đồng hành học tiếng Anh')
            ->assertDontSeeText('Địa chỉ không được hiển thị')
            ->assertDontSeeText('Phường không dùng');
    }

    public function test_empty_state_links_to_the_real_create_route(): void
    {
        $learner = $this->createUser('learner');

        $this->actingAs($learner)
            ->get(route('my-requests.index'))
            ->assertOk()
            ->assertSeeText('Bạn chưa có yêu cầu học nào')
            ->assertSeeText('Tạo yêu cầu học')
            ->assertSee('href="'.route('requests.create').'"', false)
            ->assertDontSee('aria-disabled="true"', false);
    }

    public function test_empty_state_uses_identity_verification_cta_and_status_message_when_not_verified(): void
    {
        foreach ([
            null => 'Bạn cần xác minh danh tính trước khi tạo yêu cầu học.',
            IdentityVerification::STATUS_PENDING => 'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.',
            IdentityVerification::STATUS_REJECTED => 'Hồ sơ xác minh danh tính chưa được chấp nhận. Vui lòng cập nhật và gửi lại.',
        ] as $status => $message) {
            $learner = $this->createUser('cta-'.($status ?: 'none'), [
                'identity_status' => $status ?: null,
            ]);

            $this->actingAs($learner)
                ->get(route('my-requests.index'))
                ->assertOk()
                ->assertSeeText($message)
                ->assertSeeText('Xác minh danh tính để tạo yêu cầu')
                ->assertSee('href="'.route('identity-verification.show').'"', false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createStorePayload(string $label = 'public'): array
    {
        $this->sequence++;
        $suffix = $this->sequence;
        $now = now();

        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => "Môn {$label} {$suffix}",
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'level_name' => "Trình độ {$label} {$suffix}",
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => "Tỉnh {$label} {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => "Phường {$label} {$suffix}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $timeSlotId = $this->createTimeSlot("{$label}-{$suffix}");

        return [
            'subject_id' => $subjectId,
            'subject_level_id' => $subjectLevelId,
            'learning_mode' => 'OFFLINE',
            'preferred_tutor_gender' => 'ANY',
            'province_id' => $provinceId,
            'ward_id' => $wardId,
            'address_detail' => '  Số 12 đường Test  ',
            'expected_fee' => 275000,
            'fee_type' => 'HOURLY',
            'description' => '  Cần củng cố kiến thức nền tảng.  ',
            'schedule_day' => [1],
            'schedule_time_slot' => [$timeSlotId],
            'expires_at' => $now->copy()->addDays(6)->toDateTimeString(),
        ];
    }

    private function createTimeSlot(string $label, array $attributes = []): int
    {
        $this->sequence++;
        $minute = str_pad((string) ($this->sequence % 60), 2, '0', STR_PAD_LEFT);

        return DB::table('time_slots')->insertGetId(array_merge([
            'start_time' => "08:{$minute}:00",
            'end_time' => "09:{$minute}:00",
            'slot_name' => "Khung giờ {$label}",
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storePublicRequestWith(array $overrides): TutoringRequest
    {
        $learner = $this->createUser('learner');
        $payload = array_merge($this->createStorePayload(), $overrides);

        $this->actingAs($learner)
            ->post(route('requests.store'), $payload)
            ->assertRedirect(route('my-requests.index'));

        return TutoringRequest::query()->sole();
    }

    private function createUser(string $label, array $attributes = []): User
    {
        $this->sequence++;
        $identityStatus = array_key_exists('identity_status', $attributes)
            ? $attributes['identity_status']
            : IdentityVerification::STATUS_VERIFIED;
        unset($attributes['identity_status']);

        $user = User::query()->create(array_merge([
            'google_id' => "google-{$label}-{$this->sequence}",
            'email' => "{$label}-{$this->sequence}@example.com",
            'full_name' => "Người dùng {$label}",
            'avatar_url' => null,
            'phone' => '0900000000',
        ], $attributes));

        if ($identityStatus !== null) {
            DB::table('identity_verifications')->insert([
                'user_id' => $user->getKey(),
                'document_type' => 'CCCD',
                'document_number' => str_pad((string) $user->getKey(), 12, '0', STR_PAD_LEFT),
                'front_image_path' => 'identity-verifications/test/front.jpg',
                'back_image_path' => 'identity-verifications/test/back.jpg',
                'status' => $identityStatus,
                'submitted_at' => now()->subDay(),
                'verified_at' => $identityStatus === IdentityVerification::STATUS_VERIFIED ? now() : null,
                'verified_by' => null,
                'rejection_reason' => $identityStatus === IdentityVerification::STATUS_REJECTED
                    ? 'Ảnh giấy tờ chưa rõ.'
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function createTutor(string $label, array $attributes = []): int
    {
        $user = $this->createUser($label, [
            'full_name' => $attributes['full_name'] ?? "Gia sư {$label}",
        ]);

        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->user_id,
            'headline' => $attributes['headline'] ?? null,
            'approval_status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRequest(User $learner, array $attributes = []): int
    {
        $this->sequence++;
        $suffix = $this->sequence;
        $now = now();
        $mode = $attributes['learning_mode'] ?? 'OFFLINE';

        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $attributes['subject_name'] ?? "Toán {$suffix}",
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'level_name' => $attributes['level_name'] ?? "Lớp 10 - {$suffix}",
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wardId = null;
        if (array_key_exists('ward_name', $attributes) || $mode === 'OFFLINE') {
            $provinceId = DB::table('provinces')->insertGetId([
                'province_name' => $attributes['province_name'] ?? "Hà Nội {$suffix}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $wardId = DB::table('wards')->insertGetId([
                'province_id' => $provinceId,
                'ward_name' => $attributes['ward_name'] ?? "Phường Test {$suffix}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $requestId = DB::table('tutoring_requests')->insertGetId([
            'user_id' => $learner->user_id,
            'target_tutor_profile_id' => $attributes['target_tutor_profile_id'] ?? null,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'request_type' => $attributes['request_type'] ?? 'PUBLIC',
            'learning_mode' => $mode,
            'address_detail' => $attributes['address_detail'] ?? ($mode === 'OFFLINE' ? "Số 1, đường Test {$suffix}" : null),
            'expected_fee' => $attributes['expected_fee'] ?? 150000,
            'description' => $attributes['description'] ?? "Yêu cầu học {$suffix}",
            'status' => $attributes['status'] ?? 'OPEN',
            'expires_at' => $attributes['expires_at'] ?? $now->copy()->addDays(7),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($attributes['with_schedule'] ?? false) {
            $timeSlotId = DB::table('time_slots')->insertGetId([
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'slot_name' => 'Buổi tối',
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('request_schedules')->insert([
                'request_id' => $requestId,
                'time_slot_id' => $timeSlotId,
                'day_of_week' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $requestId;
    }

    private function createTutorApplication(int $requestId, string $label, array $attributes = []): int
    {
        $tutorProfileId = $this->createTutor($label, $attributes);

        return DB::table('tutor_applications')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
            'message' => $attributes['message'] ?? null,
            'proposed_fee' => $attributes['proposed_fee'] ?? null,
            'status' => $attributes['status'] ?? 'PENDING',
            'applied_at' => now(),
            'updated_at' => now(),
        ]);
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
                $table->string('status')->default('ACTIVE');
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutor_profiles')) {
            Schema::create('tutor_profiles', function (Blueprint $table): void {
                $table->bigIncrements('tutor_profile_id');
                $table->unsignedBigInteger('user_id');
                $table->string('headline', 150)->nullable();
                $table->string('approval_status', 30)->default('PENDING');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('identity_verifications')) {
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
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table): void {
                $table->bigIncrements('subject_id');
                $table->string('subject_name', 100);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subject_levels')) {
            Schema::create('subject_levels', function (Blueprint $table): void {
                $table->bigIncrements('subject_level_id');
                $table->unsignedBigInteger('subject_id');
                $table->string('level_name', 100);
                $table->unsignedInteger('sort_order')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table): void {
                $table->bigIncrements('province_id');
                $table->string('province_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wards')) {
            Schema::create('wards', function (Blueprint $table): void {
                $table->bigIncrements('ward_id');
                $table->unsignedBigInteger('province_id');
                $table->string('ward_name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tutoring_requests')) {
            Schema::create('tutoring_requests', function (Blueprint $table): void {
                $table->bigIncrements('request_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('target_tutor_profile_id')->nullable();
                $table->unsignedBigInteger('subject_level_id');
                $table->unsignedBigInteger('ward_id')->nullable();
                $table->string('request_type', 20);
                $table->string('learning_mode', 20);
                $table->string('preferred_tutor_gender', 10)->default('ANY');
                $table->string('address_detail')->nullable();
                $table->decimal('expected_fee', 12, 2)->nullable();
                $table->string('fee_type', 20)->default('HOURLY');
                $table->text('description')->nullable();
                $table->string('status', 30);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('time_slots')) {
            Schema::create('time_slots', function (Blueprint $table): void {
                $table->bigIncrements('time_slot_id');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('slot_name', 50)->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('request_schedules')) {
            Schema::create('request_schedules', function (Blueprint $table): void {
                $table->bigIncrements('request_schedule_id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('time_slot_id');
                $table->unsignedTinyInteger('day_of_week');
                $table->timestamps();
                $table->unique(['request_id', 'day_of_week', 'time_slot_id']);
            });
        }

        if (! Schema::hasTable('tutor_applications')) {
            Schema::create('tutor_applications', function (Blueprint $table): void {
                $table->bigIncrements('application_id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('tutor_profile_id');
                $table->text('message')->nullable();
                $table->decimal('proposed_fee', 12, 2)->nullable();
                $table->string('status', 30)->default('PENDING');
                $table->timestamp('applied_at');
                $table->timestamp('updated_at');
            });
        }

        if (! Schema::hasTable('request_status_history')) {
            Schema::create('request_status_history', function (Blueprint $table): void {
                $table->bigIncrements('history_id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('initiated_by_user_id')->nullable();
                $table->string('old_status')->nullable();
                $table->string('new_status');
                $table->string('reason')->nullable();
                $table->timestamp('changed_at');
            });
        }

        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table): void {
                $table->bigIncrements('contract_id');
                $table->unsignedBigInteger('request_id')->unique();
                $table->unsignedBigInteger('tutor_profile_id');
                $table->decimal('agreed_fee', 12, 2);
                $table->string('agreed_fee_type', 20)->nullable();
                $table->string('payment_method', 20)->nullable();
                $table->string('learning_mode', 20);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('terms')->nullable();
                $table->string('status', 30)->default('PENDING');
                $table->timestamps();
            });
        }
    }
}
