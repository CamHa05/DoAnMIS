<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicRequestDetailTest extends TestCase
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

    public function test_owner_sees_real_application_count_and_owner_action_without_apply_action(): void
    {
        $owner = $this->createUser('Nguyễn Văn A');
        $levelId = $this->createSubjectLevel('IELTS', 'Foundation');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $this->insertTutorApplication($requestId, 91);
        $this->insertTutorApplication($requestId, 92);

        Model::preventLazyLoading();

        try {
            $this->actingAs($owner)
                ->get(route('requests.show', $requestId))
                ->assertOk()
                ->assertSeeText('Nguyễn Văn A')
                ->assertSeeText('Xem gia sư ứng tuyển (2)')
                ->assertSee('href="'.route('my-requests.applications.index', $requestId).'"', false)
                ->assertDontSeeText('Ứng tuyển ngay');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_guest_sees_apply_cta_and_is_sent_to_login_with_the_request_as_intended_url(): void
    {
        $owner = $this->createUser('Chủ yêu cầu guest');
        $levelId = $this->createSubjectLevel('Toán guest', 'Lớp 8');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $requestUrl = route('requests.show', $requestId);

        $this->get($requestUrl)
            ->assertOk()
            ->assertSeeText('Ứng tuyển ngay')
            ->assertSeeText('Bạn cần đăng nhập để tiếp tục ứng tuyển.')
            ->assertSee('href="'.route('requests.applications.login', $requestId).'"', false);

        $this->get(route('requests.applications.login', $requestId))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $requestUrl);
    }

    public function test_owner_can_open_the_application_directory_and_candidate_profile_without_lazy_loading(): void
    {
        $owner = $this->createUser('Người học xem ứng viên');
        $tutor = $this->createUser('Gia sư đồng ý học phí');
        $levelId = $this->createSubjectLevel('IELTS', 'Foundation');
        $requestId = $this->createRequest($owner->user_id, $levelId, [
            'expected_fee' => 180000,
        ]);
        $profileId = $this->createTutor($tutor->user_id, $levelId, profileAttributes: [
            'headline' => 'Dạy nền tảng IELTS rõ ràng',
            'bio' => 'Có lộ trình học phù hợp với người mới.',
            'education_summary' => 'Cử nhân Ngôn ngữ Anh',
            'teaching_experience' => 'Kinh nghiệm dạy nền tảng.',
        ]);
        $applicationId = $this->insertTutorApplication($requestId, $profileId, [
            'message' => 'Tôi sẵn sàng hỗ trợ học viên bắt đầu từ nền tảng.',
            'proposed_fee' => null,
            'status' => 'PENDING',
            'applied_at' => now()->subHours(2),
        ]);

        Model::preventLazyLoading();

        try {
            $this->actingAs($owner)
                ->get(route('my-requests.applications.index', $requestId))
                ->assertOk()
                ->assertSeeText('Gia sư ứng tuyển')
                ->assertSeeText('Gia sư đồng ý học phí')
                ->assertSeeText('Đồng ý mức học phí dự kiến')
                ->assertSeeText('180.000 VNĐ/giờ')
                ->assertSeeText('Chờ xem xét')
                ->assertSee('href="'.route('my-requests.applications.show', [$requestId, $applicationId]).'"', false)
                ->assertSee('data-select-tutor-open', false)
                ->assertSee('data-select-tutor-action="'.route('my-requests.applications.select', [$requestId, $applicationId]).'"', false)
                ->assertSeeText('Chọn gia sư này?');

            $this->actingAs($owner)
                ->get(route('my-requests.applications.show', [$requestId, $applicationId]))
                ->assertOk()
                ->assertSeeText('Dạy nền tảng IELTS rõ ràng')
                ->assertSeeText('Tôi sẵn sàng hỗ trợ học viên bắt đầu từ nền tảng.')
                ->assertSeeText('Kinh nghiệm dạy nền tảng.');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_my_request_detail_only_exposes_the_application_cta_not_candidate_data(): void
    {
        $owner = $this->createUser('Chủ yêu cầu riêng');
        $tutor = $this->createUser('Ứng viên không hiện tại detail');
        $levelId = $this->createSubjectLevel('Toán', 'Lớp 8');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $profileId = $this->createTutor($tutor->user_id, $levelId);
        $this->insertTutorApplication($requestId, $profileId, [
            'message' => 'Lời nhắn chỉ thuộc trang ứng viên.',
        ]);

        Model::preventLazyLoading();

        try {
            $this->actingAs($owner)
                ->get(route('my-requests.show', $requestId))
                ->assertOk()
                ->assertSeeText('Xem gia sư ứng tuyển (1)')
                ->assertSee('href="'.route('my-requests.applications.index', $requestId).'"', false)
                ->assertDontSeeText('Ứng viên không hiện tại detail')
                ->assertDontSeeText('Lời nhắn chỉ thuộc trang ứng viên.');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_only_the_request_owner_can_view_application_routes(): void
    {
        $owner = $this->createUser('Chủ yêu cầu phân quyền');
        $otherLearner = $this->createUser('Người học khác');
        $tutor = $this->createUser('Gia sư ứng tuyển');
        $admin = $this->createUser('Quản trị viên');
        $levelId = $this->createSubjectLevel('Ngữ văn', 'Lớp 9');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $profileId = $this->createTutor($tutor->user_id, $levelId);
        $applicationId = $this->insertTutorApplication($requestId, $profileId);
        DB::table('users')->where('user_id', $admin->user_id)->update(['is_admin' => true]);

        $this->get(route('my-requests.applications.index', $requestId))
            ->assertRedirect(route('login'));

        foreach ([$otherLearner, $tutor, $admin->fresh()] as $nonOwner) {
            $this->actingAs($nonOwner)
                ->get(route('my-requests.applications.index', $requestId))
                ->assertForbidden();
            $this->actingAs($nonOwner)
                ->get(route('my-requests.applications.show', [$requestId, $applicationId]))
                ->assertForbidden();
        }
    }

    public function test_application_sort_uses_the_effective_fee_and_preserves_the_selected_query(): void
    {
        $owner = $this->createUser('Chủ yêu cầu sort');
        $levelId = $this->createSubjectLevel('Tiếng Anh', 'Giao tiếp');
        $requestId = $this->createRequest($owner->user_id, $levelId, ['expected_fee' => 180000]);
        $lowTutor = $this->createUser('Gia sư phí thấp');
        $expectedTutor = $this->createUser('Gia sư đồng ý mức dự kiến');
        $highTutor = $this->createUser('Gia sư phí cao');

        $this->insertTutorApplication($requestId, $this->createTutor($lowTutor->user_id, $levelId), [
            'proposed_fee' => 120000,
            'applied_at' => now()->subHours(1),
        ]);
        $this->insertTutorApplication($requestId, $this->createTutor($expectedTutor->user_id, $levelId), [
            'proposed_fee' => null,
            'applied_at' => now()->subHours(2),
        ]);
        $this->insertTutorApplication($requestId, $this->createTutor($highTutor->user_id, $levelId), [
            'proposed_fee' => 240000,
            'applied_at' => now()->subHours(3),
        ]);

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', [$requestId, 'sort' => 'fee_low']))
            ->assertOk()
            ->assertSeeInOrder(['Gia sư phí thấp', 'Gia sư đồng ý mức dự kiến', 'Gia sư phí cao'])
            ->assertSeeText('Đồng ý mức học phí dự kiến');

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', [$requestId, 'sort' => 'fee_high']))
            ->assertOk()
            ->assertSeeInOrder(['Gia sư phí cao', 'Gia sư đồng ý mức dự kiến', 'Gia sư phí thấp']);

        foreach (range(1, 8) as $number) {
            $extraTutor = $this->createUser('Gia sư phân trang '.$number);
            $this->insertTutorApplication(
                $requestId,
                $this->createTutor($extraTutor->user_id, $levelId),
                ['applied_at' => now()->subDays($number)]
            );
        }

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', [$requestId, 'sort' => 'oldest']))
            ->assertOk()
            ->assertSee('sort=oldest&amp;page=2', false);
    }

    public function test_application_sidebar_uses_real_status_counts(): void
    {
        $owner = $this->createUser('Chủ yêu cầu đếm trạng thái');
        $levelId = $this->createSubjectLevel('Sinh học', 'Lớp 10');
        $requestId = $this->createRequest($owner->user_id, $levelId);

        foreach (['PENDING', 'ACCEPTED', 'REJECTED'] as $status) {
            $tutor = $this->createUser('Gia sư trạng thái '.$status);
            $this->insertTutorApplication(
                $requestId,
                $this->createTutor($tutor->user_id, $levelId),
                ['status' => $status]
            );
        }

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', $requestId))
            ->assertOk()
            ->assertSeeInOrder([
                'Tổng số gia sư ứng tuyển', '3',
                'Đang chờ xem xét', '1',
                'Đã chọn', '1',
            ])
            ->assertSeeText('Không được chọn');
    }

    public function test_empty_application_directory_and_cross_request_candidate_are_handled_safely(): void
    {
        $owner = $this->createUser('Chủ yêu cầu trống');
        $tutor = $this->createUser('Gia sư yêu cầu khác');
        $levelId = $this->createSubjectLevel('Hóa học', 'Lớp 11');
        $emptyRequestId = $this->createRequest($owner->user_id, $levelId);
        $otherRequestId = $this->createRequest($owner->user_id, $levelId);
        $applicationId = $this->insertTutorApplication(
            $otherRequestId,
            $this->createTutor($tutor->user_id, $levelId)
        );

        $this->actingAs($owner)
            ->get(route('my-requests.applications.index', $emptyRequestId))
            ->assertOk()
            ->assertSeeText('Chưa có gia sư ứng tuyển')
            ->assertSeeText('Tổng số gia sư ứng tuyển')
            ->assertSeeText('0');

        $this->actingAs($owner)
            ->get(route('my-requests.applications.show', [$emptyRequestId, $applicationId]))
            ->assertNotFound();
    }

    public function test_approved_tutor_sees_apply_state_even_when_specialty_does_not_match(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutorUser = $this->createUser('Gia sư phù hợp');
        $requestLevelId = $this->createSubjectLevel('Toán', 'Lớp 8');
        $tutorLevelId = $this->createSubjectLevel('Ngữ văn', 'Lớp 12');
        $requestId = $this->createRequest($owner->user_id, $requestLevelId);
        $slotId = $this->createTimeSlot('18:00:00', '20:00:00');
        $this->createSchedule($requestId, $slotId, 2);
        $this->createTutor($tutorUser->user_id, $tutorLevelId, $slotId, 2);

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Ứng tuyển ngay')
            ->assertSeeText('Bạn đáp ứng điều kiện ứng tuyển')
            ->assertDontSeeText('Xem gia sư ứng tuyển');
    }

    public function test_approved_tutor_sees_apply_state_even_when_availability_does_not_match(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutorUser = $this->createUser('Gia sư khác lịch');
        $levelId = $this->createSubjectLevel('Toán', 'Lớp 8');
        $requestSlotId = $this->createTimeSlot('18:00:00', '20:00:00');
        $tutorSlotId = $this->createTimeSlot('08:00:00', '10:00:00');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $this->createSchedule($requestId, $requestSlotId, 2);
        $this->createTutor($tutorUser->user_id, $levelId, $tutorSlotId, 6);

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Ứng tuyển ngay');
    }

    public function test_pending_and_rejected_tutors_do_not_see_apply_action(): void
    {
        foreach ([TutorProfile::STATUS_PENDING, TutorProfile::STATUS_REJECTED] as $status) {
            $owner = $this->createUser('Chủ yêu cầu '.$status);
            $tutorUser = $this->createUser('Gia sư '.$status);
            $levelId = $this->createSubjectLevel('Toán '.$status, 'Lớp 8');
            $requestId = $this->createRequest($owner->user_id, $levelId);
            $this->createTutor($tutorUser->user_id, $levelId, profileAttributes: [
                'approval_status' => $status,
            ]);

            $this->actingAs($tutorUser)
                ->get(route('requests.show', $requestId))
                ->assertOk()
                ->assertDontSeeText('Ứng tuyển ngay');
        }
    }

    public function test_approved_but_unsubmitted_tutor_does_not_see_apply_action(): void
    {
        $owner = $this->createUser('Chủ yêu cầu chưa submit');
        $tutorUser = $this->createUser('Gia sư chưa submit');
        $levelId = $this->createSubjectLevel('Toán chưa submit', 'Lớp 9');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $this->createTutor($tutorUser->user_id, $levelId, profileAttributes: [
            'submitted_at' => null,
        ]);

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertDontSeeText('Ứng tuyển ngay');
    }

    public function test_tutor_with_existing_application_sees_applied_status_without_apply_action(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutorUser = $this->createUser('Gia sư đã ứng tuyển');
        $levelId = $this->createSubjectLevel('Vật lý', 'Lớp 10');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $profileId = $this->createTutor($tutorUser->user_id, $levelId);
        $this->insertTutorApplication($requestId, $profileId, [
            'proposed_fee' => 190000,
            'status' => 'PENDING',
        ]);

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Đã ứng tuyển')
            ->assertSeeText('Đang chờ phản hồi')
            ->assertSeeText('190.000 VNĐ/giờ')
            ->assertDontSeeText('Ứng tuyển ngay')
            ->assertDontSeeText('Xem gia sư ứng tuyển');
    }

    public function test_other_learner_gets_readonly_context_without_private_actions_or_count(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $otherLearner = $this->createUser('Người học khác');
        $levelId = $this->createSubjectLevel('Ngữ văn', 'Lớp 9');
        $requestId = $this->createRequest($owner->user_id, $levelId);
        $this->insertTutorApplication($requestId, 99);

        $this->actingAs($otherLearner)
            ->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Đây là yêu cầu học công khai')
            ->assertDontSeeText('Xem gia sư ứng tuyển')
            ->assertDontSeeText('Ứng tuyển ngay');
    }

    public function test_expired_and_matched_requests_do_not_offer_an_invalid_apply_action(): void
    {
        $owner = $this->createUser('Chủ yêu cầu');
        $tutorUser = $this->createUser('Gia sư');
        $levelId = $this->createSubjectLevel('Hóa học', 'Lớp 11');
        $this->createTutor($tutorUser->user_id, $levelId);
        $expiredId = $this->createRequest($owner->user_id, $levelId, [
            'status' => 'EXPIRED',
            'expires_at' => now()->subDay(),
        ]);
        $matchedId = $this->createRequest($owner->user_id, $levelId, [
            'status' => 'MATCHED',
        ]);

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $expiredId))
            ->assertOk()
            ->assertSeeText('Đã hết hạn')
            ->assertSeeText('Yêu cầu hiện không nhận thêm ứng tuyển.')
            ->assertDontSeeText('Ứng tuyển ngay');

        $this->actingAs($tutorUser)
            ->get(route('requests.show', $matchedId))
            ->assertOk()
            ->assertSeeText('Đã ghép gia sư')
            ->assertSeeText('Yêu cầu hiện không nhận thêm ứng tuyển.')
            ->assertDontSeeText('Ứng tuyển ngay');
    }

    public function test_online_request_with_null_ward_is_safe_and_hides_location_rows(): void
    {
        $owner = $this->createUser('Người học Online');
        $levelId = $this->createSubjectLevel('Tiếng Anh', 'Giao tiếp');
        $requestId = $this->createRequest($owner->user_id, $levelId, [
            'learning_mode' => 'ONLINE',
            'ward_id' => null,
        ]);

        $this->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Trực tuyến')
            ->assertDontSeeText('Khu vực');
    }

    public function test_offline_request_shows_ward_and_province_without_exact_address(): void
    {
        $owner = $this->createUser('Người học Offline');
        $levelId = $this->createSubjectLevel('Toán', 'Lớp 6');
        $wardId = $this->createWard('Hồ Chí Minh', 'Phường Bến Nghé');
        $requestId = $this->createRequest($owner->user_id, $levelId, [
            'learning_mode' => 'OFFLINE',
            'ward_id' => $wardId,
            'address_detail' => 'Số 12 đường riêng tư',
        ]);

        $this->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Khu vực')
            ->assertSeeText('Phường Bến Nghé, Hồ Chí Minh')
            ->assertDontSeeText('Số 12 đường riêng tư');
    }

    public function test_null_expiration_is_safe_and_uses_one_source_for_deadline_copy(): void
    {
        $owner = $this->createUser('Người học không đặt hạn');
        $levelId = $this->createSubjectLevel('Tin học', 'Cơ bản');
        $requestId = $this->createRequest($owner->user_id, $levelId, [
            'expires_at' => null,
        ]);

        $this->get(route('requests.show', $requestId))
            ->assertOk()
            ->assertSeeText('Không có thời hạn')
            ->assertSeeText('Không giới hạn');
    }

    private function createUser(string $name): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => 'google-'.str()->slug($name).'-'.DB::table('users')->count(),
            'email' => str()->slug($name).'-'.DB::table('users')->count().'@example.test',
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => false,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('identity_verifications')->insert([
            'user_id' => $userId,
            'document_type' => 'CCCD',
            'document_number' => str_pad((string) $userId, 12, '0', STR_PAD_LEFT),
            'front_image_path' => 'identity-verifications/test/front.jpg',
            'back_image_path' => 'identity-verifications/test/back.jpg',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now()->subDay(),
            'verified_at' => now(),
            'verified_by' => null,
            'rejection_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createSubjectLevel(string $subjectName, string $levelName): int
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $subjectName,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => $levelName,
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWard(string $provinceName, string $wardName): int
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => $provinceName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => $wardName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createRequest(int $ownerId, int $levelId, array $attributes = []): int
    {
        return DB::table('tutoring_requests')->insertGetId(array_merge([
            'user_id' => $ownerId,
            'target_tutor_profile_id' => null,
            'subject_level_id' => $levelId,
            'ward_id' => null,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'preferred_tutor_gender' => 'ANY',
            'address_detail' => null,
            'expected_fee' => 180000,
            'fee_type' => 'HOURLY',
            'description' => 'Mô tả nhu cầu học từ cơ sở dữ liệu.',
            'status' => 'OPEN',
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subHours(7),
            'updated_at' => now(),
        ], $attributes));
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

    private function createSchedule(int $requestId, int $slotId, int $day): void
    {
        DB::table('request_schedules')->insert([
            'request_id' => $requestId,
            'time_slot_id' => $slotId,
            'day_of_week' => $day,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTutor(
        int $userId,
        int $levelId,
        ?int $slotId = null,
        ?int $day = null,
        ?int $wardId = null,
        array $profileAttributes = []
    ): int {
        $profileId = DB::table('tutor_profiles')->insertGetId(array_merge([
            'user_id' => $userId,
            'headline' => null,
            'bio' => null,
            'education_summary' => null,
            'teaching_experience' => null,
            'hourly_rate' => 180000,
            'supports_online' => true,
            'supports_offline' => $wardId !== null,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now(),
            'submitted_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $profileAttributes));
        $subjectId = (int) DB::table('subject_levels')->where('subject_level_id', $levelId)->value('subject_id');
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $profileId,
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

        if ($slotId !== null && $day !== null) {
            DB::table('tutor_availabilities')->insert([
                'tutor_profile_id' => $profileId,
                'time_slot_id' => $slotId,
                'day_of_week' => $day,
                'is_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($wardId !== null) {
            DB::table('tutor_teaching_areas')->insert([
                'tutor_profile_id' => $profileId,
                'ward_id' => $wardId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $profileId;
    }

    /** @param array<string, mixed> $attributes */
    private function insertTutorApplication(int $requestId, int $profileId, array $attributes = []): int
    {
        return DB::table('tutor_applications')->insertGetId(array_merge([
            'request_id' => $requestId,
            'tutor_profile_id' => $profileId,
            'message' => null,
            'proposed_fee' => null,
            'status' => 'PENDING',
            'applied_at' => now()->subHour(),
            'updated_at' => now(),
        ], $attributes));
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
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('request_id')->unique();
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
        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->text('message')->nullable();
            $table->decimal('proposed_fee', 12, 2)->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamp('applied_at');
            $table->timestamp('updated_at');
        });
    }
}
