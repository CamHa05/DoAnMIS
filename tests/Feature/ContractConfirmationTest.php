<?php

namespace Tests\Feature;

use App\Exceptions\ContractConfirmationException;
use App\Models\Contract;
use App\Models\ContractConfirmation;
use App\Models\TutoringClass;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\ContractConfirmationService;
use App\Services\ContractTermsGenerator;
use App\Services\SystemNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ContractConfirmationTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_disabled_participant_is_blocked_until_reactivated_without_losing_the_contract(): void
    {
        $scenario = $this->createScenario();
        DB::table('users')
            ->where('user_id', $scenario['learner']->getKey())
            ->update(['status' => User::STATUS_DISABLED]);

        try {
            resolve(ContractConfirmationService::class)->confirm(
                $scenario['contract_id'],
                (int) $scenario['learner']->getKey(),
                Contract::PAYMENT_METHOD_CASH,
                '2026-09-20',
                '2026-12-20'
            );

            $this->fail('Expected a disabled participant to be rejected.');
        } catch (ContractConfirmationException $exception) {
            $this->assertSame(
                'Tài khoản đã bị vô hiệu hóa nên không thể xác nhận hợp đồng.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('contract_confirmations', 0);
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'status' => Contract::STATUS_PENDING,
        ]);

        DB::table('users')
            ->where('user_id', $scenario['learner']->getKey())
            ->update(['status' => User::STATUS_ACTIVE]);

        $fullyConfirmed = resolve(ContractConfirmationService::class)->confirm(
            $scenario['contract_id'],
            (int) $scenario['learner']->getKey(),
            Contract::PAYMENT_METHOD_CASH,
            '2026-09-20',
            '2026-12-20'
        );

        $this->assertFalse($fullyConfirmed);
        $this->assertDatabaseHas('contract_confirmations', [
            'contract_id' => $scenario['contract_id'],
            'user_id' => $scenario['learner']->getKey(),
            'confirmation_status' => ContractConfirmation::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'status' => Contract::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('tutoring_classes', 0);
    }

    public function test_learner_and_tutor_can_view_their_contract_with_real_agreement_data(): void
    {
        $scenario = $this->createScenario([
            'agreed_fee' => 175000,
            'agreed_fee_type' => 'HOURLY',
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'terms' => 'Hai bên thống nhất tuân thủ lịch học đã ghi nhận.',
        ]);
        $this->createConfirmation($scenario['contract_id'], $scenario['learner']->user_id);

        foreach ([
            [$scenario['learner'], $scenario['tutor']->email],
            [$scenario['tutor'], $scenario['learner']->email],
        ] as [$participant, $otherPartyEmail]) {
            $this->actingAs($participant)
                ->get(route('contracts.show', $scenario['contract_id']))
                ->assertOk()
                ->assertSeeText('Chi tiết hợp đồng')
                ->assertSeeText('175.000 VNĐ / giờ')
                ->assertSeeText('Chuyển khoản')
                ->assertSeeText('Toán')
                ->assertSeeText('Lớp 10')
                ->assertSeeText('Hai bên thống nhất tuân thủ lịch học đã ghi nhận.')
                ->assertDontSeeText($otherPartyEmail);
        }
    }

    public function test_guest_other_user_other_tutor_and_admin_cannot_view_or_confirm(): void
    {
        $scenario = $this->createScenario();
        $other = $this->createUser('other');
        $otherTutor = $this->createUser('other-tutor');
        $this->createTutorProfile($otherTutor);
        $admin = $this->createUser('admin', true);
        $showRoute = route('contracts.show', $scenario['contract_id']);
        $confirmRoute = route('contracts.confirm', $scenario['contract_id']);

        $this->get($showRoute)->assertRedirect(route('login'));
        $this->patch($confirmRoute)->assertRedirect(route('login'));

        foreach ([$other, $otherTutor, $admin] as $forbiddenUser) {
            $this->actingAs($forbiddenUser)->get($showRoute)->assertForbidden();
            $this->actingAs($forbiddenUser)->patch($confirmRoute)->assertForbidden();
        }

        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_offline_location_and_schedules_render_but_exact_address_does_not(): void
    {
        $scenario = $this->createScenario([
            'learning_mode' => 'OFFLINE',
            'address_detail' => 'Số 12 đường riêng tư',
        ]);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText('Phường Test, Hồ Chí Minh')
            ->assertSeeText('Thứ 2 · 18:00–20:00')
            ->assertDontSeeText('Số 12 đường riêng tư');
    }

    public function test_unconfirmed_learner_sees_complete_terms_preview_with_neutral_missing_values(): void
    {
        $scenario = $this->createScenario([
            'learning_mode' => 'ONLINE',
            'payment_method' => null,
            'start_date' => null,
            'end_date' => null,
            'terms' => null,
        ]);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText('Trực tuyến')
            ->assertDontSeeText('Khu vực học')
            ->assertDontSeeText('Phường Test, Hồ Chí Minh')
            ->assertSee('id="contract-terms-title"', false)
            ->assertSee('data-contract-terms-preview', false)
            ->assertSeeText('Bản xem trước điều khoản')
            ->assertSeeText('Điều 1. Nội dung học tập')
            ->assertSeeText('Điều 2. Lịch học theo thỏa thuận')
            ->assertSeeText('Điều 3. Học phí và phương thức thanh toán')
            ->assertSeeText('Điều 4. Thời hạn hợp đồng')
            ->assertSeeText('Điều 5. Xác nhận hợp đồng')
            ->assertSeeText('Chưa chọn phương thức thanh toán')
            ->assertSeeText('Chưa chọn ngày bắt đầu')
            ->assertSeeText('Chưa chọn ngày kết thúc')
            ->assertDontSeeText('Điều khoản hoàn chỉnh sẽ được tạo từ thông tin hợp đồng khi người học xác nhận.')
            ->assertDontSeeText('01/01/1970');
    }

    public function test_terms_preview_uses_current_contract_request_schedule_and_payment_data(): void
    {
        $scenario = $this->createScenario([
            'subject_name' => 'Vật lý',
            'level_name' => 'Lớp 11',
            'province_name' => 'Đà Nẵng',
            'ward_name' => 'Phường Hải Châu',
            'agreed_fee' => 225000,
            'payment_method' => Contract::PAYMENT_METHOD_CASH,
            'start_date' => '2026-11-01',
            'end_date' => '2027-02-28',
            'schedule_count' => 2,
        ]);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSee('data-contract-terms-preview', false)
            ->assertSeeText('môn Vật lý, trình độ Lớp 11')
            ->assertSeeText('Khu vực học: Phường Hải Châu, Đà Nẵng.')
            ->assertSeeText('Thứ 2 · 18:00–20:00')
            ->assertSeeText('Thứ 3 · 20:00–21:30')
            ->assertSeeText('225.000 VNĐ / giờ')
            ->assertSeeText('Tiền mặt')
            ->assertSeeText('01/11/2026')
            ->assertSeeText('28/02/2027');
    }

    public function test_only_unconfirmed_learner_sees_editable_prefilled_start_date(): void
    {
        $scenario = $this->createScenario([
            'start_date' => '2026-10-05',
            'end_date' => '2026-12-20',
        ]);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSee('name="start_date"', false)
            ->assertSee('value="2026-10-05"', false)
            ->assertSee('data-contract-start-date', false)
            ->assertSee('name="end_date"', false)
            ->assertSee('value="2026-12-20"', false);

        $this->actingAs($scenario['tutor'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false)
            ->assertSeeText('05/10/2026');

        $this->createConfirmation($scenario['contract_id'], $scenario['learner']->user_id);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false)
            ->assertSeeText('05/10/2026');
    }

    public function test_confirmation_status_and_confirmed_at_are_mapped_by_participant_user_id(): void
    {
        $scenario = $this->createScenario();
        $confirmedAt = now()->subHour()->startOfMinute();
        $this->createConfirmation(
            $scenario['contract_id'],
            $scenario['tutor']->user_id,
            $confirmedAt
        );

        $response = $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText($scenario['learner']->full_name)
            ->assertSeeText($scenario['tutor']->full_name)
            ->assertSeeText($confirmedAt->format('d/m/Y H:i'));

        $this->assertSame(1, substr_count($response->getContent(), 'Đã xác nhận'));
        $this->assertStringContainsString('Chưa xác nhận', $response->getContent());
    }

    public function test_learner_must_choose_a_supported_payment_method(): void
    {
        $scenario = $this->createScenario();
        $route = route('contracts.confirm', $scenario['contract_id']);

        $this->actingAs($scenario['learner'])
            ->from(route('contracts.show', $scenario['contract_id']))
            ->patch($route, [])
            ->assertSessionHasErrors([
                'payment_method' => 'Vui lòng chọn phương thức thanh toán.',
            ]);

        $this->actingAs($scenario['learner'])
            ->patch($route, ['payment_method' => 'CRYPTO'])
            ->assertSessionHasErrors([
                'payment_method' => 'Vui lòng chọn phương thức thanh toán.',
            ]);

        $this->assertNull(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('payment_method'));
        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_learner_can_confirm_with_bank_transfer_or_cash(): void
    {
        foreach ([Contract::PAYMENT_METHOD_BANK_TRANSFER, Contract::PAYMENT_METHOD_CASH] as $paymentMethod) {
            $scenario = $this->createScenario();

            $this->actingAs($scenario['learner'])
                ->patch(route('contracts.confirm', $scenario['contract_id']), [
                    'payment_method' => $paymentMethod,
                    'start_date' => '2026-09-20',
                    'end_date' => '2026-12-20',
                ])
                ->assertRedirect(route('contracts.show', $scenario['contract_id']))
                ->assertSessionHas('success', 'Xác nhận hợp đồng thành công.');

            $this->assertDatabaseHas('contracts', [
                'contract_id' => $scenario['contract_id'],
                'payment_method' => $paymentMethod,
                'status' => Contract::STATUS_PENDING,
            ]);
            $this->assertDatabaseHas('contract_confirmations', [
                'contract_id' => $scenario['contract_id'],
                'user_id' => $scenario['learner']->user_id,
                'confirmation_status' => ContractConfirmation::STATUS_CONFIRMED,
            ]);
            $this->assertNotNull(DB::table('contract_confirmations')
                ->where('contract_id', $scenario['contract_id'])
                ->value('confirmed_at'));
        }
    }

    public function test_learner_must_choose_a_valid_start_date_before_confirming(): void
    {
        $scenario = $this->createScenario(['start_date' => null]);
        $route = route('contracts.confirm', $scenario['contract_id']);

        $this->actingAs($scenario['learner'])
            ->from(route('contracts.show', $scenario['contract_id']))
            ->patch($route, [
                'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
                'end_date' => '2026-12-20',
            ])
            ->assertSessionHasErrors([
                'start_date' => 'Vui lòng chọn ngày bắt đầu.',
            ]);

        $this->actingAs($scenario['learner'])
            ->patch($route, [
                'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
                'start_date' => '2026-02-30',
                'end_date' => '2026-12-20',
            ])
            ->assertSessionHasErrors([
                'start_date' => 'Ngày bắt đầu không hợp lệ.',
            ]);

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'payment_method' => null,
            'start_date' => null,
        ]);
        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_learner_must_choose_a_valid_end_date_after_start_date(): void
    {
        $scenario = $this->createScenario([
            'start_date' => null,
            'end_date' => null,
        ]);
        $route = route('contracts.confirm', $scenario['contract_id']);
        $validBase = [
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'start_date' => '2026-10-05',
        ];

        $this->actingAs($scenario['learner'])
            ->patch($route, $validBase)
            ->assertSessionHasErrors([
                'end_date' => 'Vui lòng chọn ngày kết thúc.',
            ]);

        $this->actingAs($scenario['learner'])
            ->patch($route, $validBase + ['end_date' => '2026-02-30'])
            ->assertSessionHasErrors([
                'end_date' => 'Ngày kết thúc không hợp lệ.',
            ]);

        foreach (['2026-10-05', '2026-10-04'] as $invalidEndDate) {
            $this->actingAs($scenario['learner'])
                ->patch($route, $validBase + ['end_date' => $invalidEndDate])
                ->assertSessionHasErrors([
                    'end_date' => 'Ngày kết thúc phải sau ngày bắt đầu.',
                ]);
        }

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'payment_method' => null,
            'start_date' => null,
            'end_date' => null,
            'terms' => null,
        ]);
        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_learner_can_save_start_date_while_confirming(): void
    {
        $scenario = $this->createScenario(['start_date' => null]);

        $this->actingAs($scenario['learner'])
            ->patch(route('contracts.confirm', $scenario['contract_id']), [
                'payment_method' => Contract::PAYMENT_METHOD_CASH,
                'start_date' => '2026-10-05',
                'end_date' => '2026-12-20',
            ])
            ->assertRedirect(route('contracts.show', $scenario['contract_id']))
            ->assertSessionHas('success', 'Xác nhận hợp đồng thành công.');

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'payment_method' => Contract::PAYMENT_METHOD_CASH,
            'status' => Contract::STATUS_PENDING,
        ]);
        $this->assertSame(
            '2026-10-05',
            substr(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('start_date'), 0, 10)
        );
        $this->assertSame(
            '2026-12-20',
            substr(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('end_date'), 0, 10)
        );
        $this->assertDatabaseHas('contract_confirmations', [
            'contract_id' => $scenario['contract_id'],
            'user_id' => $scenario['learner']->user_id,
            'confirmation_status' => ContractConfirmation::STATUS_CONFIRMED,
        ]);
        $this->assertNotNull(
            DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('terms')
        );
    }

    public function test_learner_confirmation_generates_terms_from_this_contract_and_request_data(): void
    {
        $scenario = $this->createScenario([
            'subject_name' => 'Vật lý',
            'level_name' => 'Lớp 11',
            'province_name' => 'Đà Nẵng',
            'ward_name' => 'Phường Hải Châu',
            'agreed_fee' => 225000,
            'start_date' => null,
            'end_date' => null,
            'schedule_count' => 2,
        ]);
        $expectedTerms = resolve(ContractTermsGenerator::class)->preview(
            Contract::query()->findOrFail($scenario['contract_id']),
            Contract::PAYMENT_METHOD_BANK_TRANSFER,
            '2026-11-01',
            '2027-02-28'
        );

        $this->actingAs($scenario['learner'])
            ->patch(route('contracts.confirm', $scenario['contract_id']), [
                'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
                'start_date' => '2026-11-01',
                'end_date' => '2027-02-28',
            ])
            ->assertSessionHas('success', 'Xác nhận hợp đồng thành công.');

        $terms = (string) DB::table('contracts')
            ->where('contract_id', $scenario['contract_id'])
            ->value('terms');

        $this->assertSame($expectedTerms, $terms);
        $this->assertStringContainsString('Điều 1. Nội dung học tập', $terms);
        $this->assertStringContainsString('môn Vật lý, trình độ Lớp 11', $terms);
        $this->assertStringContainsString('Khu vực học: Phường Hải Châu, Đà Nẵng.', $terms);
        $this->assertStringContainsString('Điều 2. Lịch học theo thỏa thuận', $terms);
        $this->assertStringContainsString('- Thứ 2 · 18:00–20:00', $terms);
        $this->assertStringContainsString('- Thứ 3 · 20:00–21:30', $terms);
        $this->assertStringContainsString('225.000 VNĐ / giờ', $terms);
        $this->assertStringContainsString('Chuyển khoản', $terms);
        $this->assertStringContainsString('01/11/2026', $terms);
        $this->assertStringContainsString('28/02/2027', $terms);
        $this->assertStringContainsString('Điều 5. Xác nhận hợp đồng', $terms);
        $this->assertStringNotContainsString('môn Toán', $terms);
        $this->assertStringNotContainsString('150.000 VNĐ', $terms);
        $this->assertStringNotContainsString('Phường Test', $terms);

        $this->actingAs($scenario['learner'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText('Bạn đã xác nhận hợp đồng.')
            ->assertDontSee('name="payment_method"', false)
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false);

        $tutorResponse = $this->actingAs($scenario['tutor'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText('môn Vật lý, trình độ Lớp 11')
            ->assertSeeText('Thứ 2 · 18:00–20:00')
            ->assertSeeText('Thứ 3 · 20:00–21:30')
            ->assertDontSeeText('+1 buổi')
            ->assertSee('data-contract-confirm-open', false)
            ->assertDontSee('name="payment_method"', false)
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false);

        $termsPosition = strpos($tutorResponse->getContent(), 'Điều 1. Nội dung học tập');
        $confirmButtonPosition = strpos($tutorResponse->getContent(), 'data-contract-confirm-open');

        $this->assertNotFalse($termsPosition);
        $this->assertNotFalse($confirmButtonPosition);
        $this->assertTrue($termsPosition < $confirmButtonPosition);
    }

    public function test_terms_generation_failure_rolls_back_contract_details_and_learner_confirmation(): void
    {
        $scenario = $this->createScenario([
            'start_date' => null,
            'end_date' => null,
        ]);
        $generator = new class extends ContractTermsGenerator
        {
            public function generate(Contract $contract): string
            {
                throw new RuntimeException('Giả lập lỗi sinh điều khoản.');
            }
        };
        $service = new ContractConfirmationService(
            $generator,
            resolve(SystemNotificationService::class)
        );

        try {
            $service->confirm(
                $scenario['contract_id'],
                $scenario['learner']->user_id,
                Contract::PAYMENT_METHOD_CASH,
                '2026-10-05',
                '2026-12-20'
            );
            $this->fail('Expected the simulated terms generation failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Giả lập lỗi sinh điều khoản.', $exception->getMessage());
        }

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'payment_method' => null,
            'start_date' => null,
            'end_date' => null,
            'terms' => null,
            'status' => Contract::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_tutor_cannot_choose_or_change_payment_method(): void
    {
        $scenario = $this->createScenario([
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'start_date' => '2026-09-20',
            'end_date' => '2026-12-20',
            'terms' => 'Snapshot do người học đã xác nhận.',
        ]);
        $this->createConfirmation($scenario['contract_id'], $scenario['learner']->user_id);

        $this->actingAs($scenario['tutor'])
            ->patch(route('contracts.confirm', $scenario['contract_id']), [
                'payment_method' => Contract::PAYMENT_METHOD_CASH,
                'start_date' => '2030-01-01',
                'end_date' => '2030-12-31',
                'terms' => 'Nội dung giả mạo từ tutor.',
            ])
            ->assertRedirect(route('contracts.show', $scenario['contract_id']))
            ->assertSessionHas('success', 'Hợp đồng đã được xác nhận đầy đủ và lớp học đã được tạo.');

        $this->assertSame(
            Contract::PAYMENT_METHOD_BANK_TRANSFER,
            DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('payment_method')
        );
        $this->assertSame(
            '2026-09-20',
            substr(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('start_date'), 0, 10)
        );
        $this->assertSame(
            '2026-12-20',
            substr(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('end_date'), 0, 10)
        );
        $this->assertSame(
            'Snapshot do người học đã xác nhận.',
            DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('terms')
        );
    }

    public function test_tutor_cannot_confirm_until_learner_sets_payment_method(): void
    {
        $scenario = $this->createScenario([
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'start_date' => '2026-09-20',
            'end_date' => '2026-12-20',
            'terms' => 'Snapshot chưa được người học xác nhận.',
        ]);

        $this->actingAs($scenario['tutor'])
            ->get(route('contracts.show', $scenario['contract_id']))
            ->assertOk()
            ->assertSeeText('Đang chờ người học xác nhận.')
            ->assertDontSee('data-contract-confirm-open', false);

        $this->actingAs($scenario['tutor'])
            ->from(route('contracts.show', $scenario['contract_id']))
            ->patch(route('contracts.confirm', $scenario['contract_id']))
            ->assertRedirect(route('contracts.show', $scenario['contract_id']))
            ->assertSessionHas('error', 'Đang chờ người học xác nhận.');

        $this->assertDatabaseCount('contract_confirmations', 0);
    }

    public function test_duplicate_confirmation_is_blocked_without_duplicate_record(): void
    {
        $scenario = $this->createScenario();
        $route = route('contracts.confirm', $scenario['contract_id']);

        $this->actingAs($scenario['learner'])->patch($route, [
            'payment_method' => Contract::PAYMENT_METHOD_CASH,
            'start_date' => '2026-09-20',
            'end_date' => '2026-12-20',
        ])->assertSessionHas('success');

        $this->actingAs($scenario['learner'])->patch($route, [
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
        ])->assertSessionHas('error', 'Bạn đã xác nhận hợp đồng này trước đó.');

        $this->assertDatabaseCount('contract_confirmations', 1);
        $this->assertSame(
            Contract::PAYMENT_METHOD_CASH,
            DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('payment_method')
        );
        $this->assertSame(
            '2026-09-20',
            substr(DB::table('contracts')->where('contract_id', $scenario['contract_id'])->value('start_date'), 0, 10)
        );
    }

    public function test_second_confirmation_confirms_contract_and_creates_one_class_with_copied_schedules(): void
    {
        $scenario = $this->createScenario([
            'start_date' => null,
            'schedule_count' => 2,
            'subject_name' => 'Địa lý',
            'level_name' => 'Lớp 12',
        ]);

        $this->actingAs($scenario['learner'])
            ->patch(route('contracts.confirm', $scenario['contract_id']), [
                'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
                'start_date' => '2026-10-05',
                'end_date' => '2026-12-20',
            ])
            ->assertSessionHas('success', 'Xác nhận hợp đồng thành công.');

        $this->actingAs($scenario['tutor'])
            ->patch(route('contracts.confirm', $scenario['contract_id']))
            ->assertRedirect(route('contracts.show', $scenario['contract_id']))
            ->assertSessionHas(
                'success',
                'Hợp đồng đã được xác nhận đầy đủ và lớp học đã được tạo.'
            );

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'status' => Contract::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseCount('contract_confirmations', 2);
        $this->assertDatabaseCount('tutoring_classes', 1);
        $this->assertDatabaseCount('class_schedules', 2);

        $class = DB::table('tutoring_classes')->first();
        $this->assertSame($scenario['contract_id'], (int) $class->contract_id);
        $this->assertSame('Địa lý - Lớp 12', $class->class_name);
        $this->assertSame('2026-10-05', substr($class->start_date, 0, 10));
        $this->assertSame('2026-12-20', substr($class->end_date, 0, 10));

        foreach (DB::table('class_schedules')->get() as $schedule) {
            $this->assertSame('2026-10-05', substr($schedule->effective_from, 0, 10));
            $this->assertSame('2026-12-20', substr($schedule->effective_to, 0, 10));
        }

        $this->actingAs($scenario['tutor'])
            ->patch(route('contracts.confirm', $scenario['contract_id']))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('tutoring_classes', 1);
        $this->assertDatabaseCount('class_schedules', 2);
    }

    public function test_class_creation_failure_rolls_back_second_confirmation_and_contract_status(): void
    {
        $scenario = $this->createScenario([
            'payment_method' => Contract::PAYMENT_METHOD_BANK_TRANSFER,
            'terms' => 'Snapshot hợp đồng đã được lưu.',
        ]);
        $this->createConfirmation($scenario['contract_id'], $scenario['learner']->user_id);

        $service = new class(
            resolve(ContractTermsGenerator::class),
            resolve(SystemNotificationService::class)
        ) extends ContractConfirmationService
        {
            protected function createTutoringClass(Contract $contract, TutoringRequest $tutoringRequest): TutoringClass
            {
                throw new RuntimeException('Giả lập lỗi tạo lớp.');
            }
        };

        try {
            $service->confirm($scenario['contract_id'], $scenario['tutor']->user_id, null);
            $this->fail('Expected the simulated class creation failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Giả lập lỗi tạo lớp.', $exception->getMessage());
        }

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'status' => Contract::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('contract_confirmations', 1);
        $this->assertDatabaseCount('tutoring_classes', 0);
        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_missing_contract_start_date_prevents_incomplete_class_and_rolls_back(): void
    {
        $scenario = $this->createScenario([
            'payment_method' => Contract::PAYMENT_METHOD_CASH,
            'start_date' => null,
            'terms' => 'Snapshot hợp đồng đã được lưu.',
        ]);
        $this->createConfirmation($scenario['contract_id'], $scenario['learner']->user_id);

        $this->actingAs($scenario['tutor'])
            ->patch(route('contracts.confirm', $scenario['contract_id']))
            ->assertSessionHas('error', 'Hợp đồng chưa có snapshot điều khoản hoàn chỉnh. Vui lòng liên hệ quản trị viên để kiểm tra dữ liệu.');

        $this->assertDatabaseCount('contract_confirmations', 1);
        $this->assertDatabaseCount('tutoring_classes', 0);
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $scenario['contract_id'],
            'status' => Contract::STATUS_PENDING,
        ]);
    }

    /**
     * @return array{learner: User, tutor: User, contract_id: int, request_id: int, tutor_profile_id: int}
     */
    private function createScenario(array $attributes = []): array
    {
        $this->sequence++;
        $now = now();
        $learner = $this->createUser('learner');
        $tutor = $this->createUser('tutor');
        $tutorProfileId = $this->createTutorProfile($tutor, [
            'headline' => 'Gia sư Toán THPT',
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $attributes['subject_name'] ?? 'Toán',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'level_name' => $attributes['level_name'] ?? 'Lớp 10',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => $attributes['province_name'] ?? 'Hồ Chí Minh',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => $attributes['ward_name'] ?? 'Phường Test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $learningMode = $attributes['learning_mode'] ?? 'OFFLINE';
        $requestId = DB::table('tutoring_requests')->insertGetId([
            'user_id' => $learner->user_id,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'request_type' => 'PUBLIC',
            'learning_mode' => $learningMode,
            'address_detail' => $attributes['address_detail'] ?? null,
            'fee_type' => 'HOURLY',
            'status' => 'MATCHED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scheduleCount = $attributes['schedule_count'] ?? 1;
        for ($index = 0; $index < $scheduleCount; $index++) {
            $timeSlotId = DB::table('time_slots')->insertGetId([
                'start_time' => $index === 0 ? '18:00:00' : '20:00:00',
                'end_time' => $index === 0 ? '20:00:00' : '21:30:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('request_schedules')->insert([
                'request_id' => $requestId,
                'time_slot_id' => $timeSlotId,
                'day_of_week' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $contractId = DB::table('contracts')->insertGetId([
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
            'agreed_fee' => $attributes['agreed_fee'] ?? 150000,
            'agreed_fee_type' => $attributes['agreed_fee_type'] ?? 'HOURLY',
            'payment_method' => $attributes['payment_method'] ?? null,
            'learning_mode' => $learningMode,
            'start_date' => array_key_exists('start_date', $attributes) ? $attributes['start_date'] : '2026-09-20',
            'end_date' => array_key_exists('end_date', $attributes) ? $attributes['end_date'] : '2026-12-20',
            'terms' => array_key_exists('terms', $attributes) ? $attributes['terms'] : null,
            'status' => $attributes['status'] ?? Contract::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return compact('learner', 'tutor', 'contractId', 'requestId', 'tutorProfileId') + [
            'contract_id' => $contractId,
            'request_id' => $requestId,
            'tutor_profile_id' => $tutorProfileId,
        ];
    }

    private function createUser(string $label, bool $isAdmin = false): User
    {
        $this->sequence++;

        $user = User::query()->create([
            'google_id' => "google-{$label}-{$this->sequence}",
            'email' => "{$label}-{$this->sequence}@example.com",
            'full_name' => "Người dùng {$label}",
            'avatar_url' => null,
            'phone' => '091'.str_pad((string) $this->sequence, 7, '0', STR_PAD_LEFT),
        ]);

        $user->is_admin = $isAdmin;
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        return $user;
    }

    private function createTutorProfile(User $user, array $attributes = []): int
    {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->user_id,
            'headline' => $attributes['headline'] ?? null,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createConfirmation(int $contractId, int $userId, $confirmedAt = null): int
    {
        $confirmedAt ??= now();

        return DB::table('contract_confirmations')->insertGetId([
            'contract_id' => $contractId,
            'user_id' => $userId,
            'confirmation_status' => ContractConfirmation::STATUS_CONFIRMED,
            'confirmed_at' => $confirmedAt,
            'created_at' => $confirmedAt,
            'updated_at' => $confirmedAt,
        ]);
    }

    private function createSchema(): void
    {
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
            $table->string('headline', 150)->nullable();
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name', 100);
            $table->timestamps();
        });
        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('level_name', 100);
            $table->timestamps();
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_name', 100);
            $table->timestamps();
        });
        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_name', 100);
            $table->timestamps();
        });
        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->string('request_type', 20);
            $table->string('learning_mode', 20);
            $table->string('address_detail')->nullable();
            $table->string('fee_type', 20)->default('HOURLY');
            $table->string('status', 30);
            $table->timestamps();
        });
        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('time_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
        Schema::create('request_schedules', function (Blueprint $table): void {
            $table->bigIncrements('request_schedule_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();
            $table->unique(['request_id', 'day_of_week', 'time_slot_id']);
        });
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
            $table->string('status', 30)->default(Contract::STATUS_PENDING);
            $table->timestamps();
        });
        Schema::create('contract_confirmations', function (Blueprint $table): void {
            $table->bigIncrements('confirmation_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('user_id');
            $table->string('confirmation_status', 20);
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'user_id']);
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id')->unique();
            $table->string('class_name', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('ACTIVE');
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
            $table->unique(['class_id', 'day_of_week', 'time_slot_id']);
        });
    }
}
