<?php

namespace Tests\Feature;

use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTutorShowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->createSchema();
    }

    public function test_admin_can_open_a_submitted_profile_with_database_relationship_data(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', 'admin-show@example.test', true);
        $tutorUser = $this->createUser('Trần Minh Khoa', 'khoa.tutor@example.test');
        $tutorId = $this->createTutor(
            $tutorUser,
            TutorProfile::STATUS_PENDING,
            '2026-09-10 08:24:00',
            true,
            true
        );

        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'Toán ứng dụng',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => null,
            'level_name' => 'Lớp 9',
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $tutorId,
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

        $provinceId = DB::table('provinces')->insertGetId([
            'province_code' => '79',
            'province_name' => 'Thành phố Hồ Chí Minh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_code' => 'ward-show',
            'ward_name' => 'Phường Bến Thành',
            'ward_type' => 'Phường',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_teaching_areas')->insert([
            'tutor_profile_id' => $tutorId,
            'ward_id' => $wardId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $timeSlotId = DB::table('time_slots')->insertGetId([
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'slot_name' => 'Buổi tối',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_availabilities')->insert([
            'tutor_profile_id' => $tutorId,
            'time_slot_id' => $timeSlotId,
            'day_of_week' => 3,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentPath = TutorDocument::STORAGE_PREFIX.'/'.$tutorId.'/bang-tot-nghiep.pdf';
        Storage::disk('local')->put($documentPath, 'private-proof');
        $documentId = DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $tutorId,
            'document_type' => TutorDocument::TYPE_DEGREE,
            'document_name' => 'Bằng tốt nghiệp',
            'file_url' => $documentPath,
            'verification_status' => TutorDocument::STATUS_APPROVED,
            'uploaded_at' => '2026-09-08 09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertOk()
            ->assertViewIs('admin.tutors.show')
            ->assertSeeText('Trần Minh Khoa')
            ->assertSeeText('khoa.tutor@example.test')
            ->assertSeeText('Chờ xét duyệt')
            ->assertSeeText('10/09/2026 08:24')
            ->assertSeeText('Toán ứng dụng')
            ->assertSeeText('Lớp 9')
            ->assertSeeText('Trực tuyến')
            ->assertSeeText('Trực tiếp')
            ->assertSeeText('Phường Bến Thành, Thành phố Hồ Chí Minh')
            ->assertSeeText('Thứ 4')
            ->assertSeeText('18:00–21:00')
            ->assertSeeText('Bằng tốt nghiệp')
            ->assertSeeText('Đã xác minh')
            ->assertSee(route('admin.tutors.documents.download', [$tutorId, $documentId]), false);
    }

    public function test_normal_user_is_forbidden_from_the_submitted_profile(): void
    {
        $member = $this->createUser('Người dùng thường', 'member-show@example.test');
        $tutor = $this->createUser('Gia sư đã gửi', 'submitted-show@example.test');
        $tutorId = $this->createTutor(
            $tutor,
            TutorProfile::STATUS_PENDING,
            '2026-09-10 08:24:00'
        );

        $this->actingAs($member)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertForbidden();
    }

    public function test_draft_profile_cannot_be_opened_by_admin(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-draft-show@example.test', true);
        $tutor = $this->createUser('Gia sư chưa gửi', 'draft-show@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, null);

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertNotFound();
    }

    public function test_each_profile_status_uses_the_existing_status_badge_mapping(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-status-show@example.test', true);
        $statuses = [
            TutorProfile::STATUS_PENDING => ['Chờ xét duyệt', 'pending', 'clock'],
            TutorProfile::STATUS_APPROVED => ['Đã duyệt', 'approved', 'circle-check'],
            TutorProfile::STATUS_REJECTED => ['Từ chối', 'rejected', 'x-circle'],
        ];

        foreach ($statuses as $status => [$label, $tone, $icon]) {
            $tutor = $this->createUser(
                'Gia sư '.$status,
                strtolower($status).'-show@example.test'
            );
            $tutorId = $this->createTutor(
                $tutor,
                $status,
                '2026-09-10 08:24:00'
            );

            $this->actingAs($admin)
                ->get(route('admin.tutors.show', $tutorId))
                ->assertOk()
                ->assertSeeText($label)
                ->assertSee('admin-status-badge--'.$tone, false)
                ->assertSee('data-status-icon="'.$icon.'"', false);
        }
    }

    public function test_show_relations_are_eager_loaded_without_lazy_queries(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-eager-show@example.test', true);
        $tutor = $this->createUser('Gia sư eager', 'eager-show@example.test');
        $tutorId = $this->createTutor(
            $tutor,
            TutorProfile::STATUS_APPROVED,
            '2026-09-10 08:24:00'
        );

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($admin)
                ->get(route('admin.tutors.show', $tutorId))
                ->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $profile = $response->viewData('tutorProfile');

        $this->assertTrue($profile->relationLoaded('user'));
        $this->assertTrue($profile->relationLoaded('tutorSubjects'));
        $this->assertTrue($profile->relationLoaded('teachingAreas'));
        $this->assertTrue($profile->relationLoaded('availabilities'));
        $this->assertTrue($profile->relationLoaded('documents'));
    }

    public function test_admin_can_download_a_private_document_from_a_submitted_profile(): void
    {
        $admin = $this->createUser('Quản trị', 'admin-document-show@example.test', true);
        $tutor = $this->createUser('Gia sư có tài liệu', 'document-show@example.test');
        $tutorId = $this->createTutor(
            $tutor,
            TutorProfile::STATUS_PENDING,
            '2026-09-10 08:24:00'
        );
        $path = TutorDocument::STORAGE_PREFIX.'/'.$tutorId.'/proof.pdf';
        Storage::disk('local')->put($path, 'private-proof');
        $documentId = DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $tutorId,
            'document_type' => TutorDocument::TYPE_CERTIFICATE,
            'document_name' => 'Chứng chỉ chuyên môn',
            'file_url' => $path,
            'verification_status' => TutorDocument::STATUS_PENDING,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tutors.documents.download', [$tutorId, $documentId]))
            ->assertOk()
            ->assertDownload('Chung chi chuyen mon.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_admin_can_preview_a_pdf_inline(): void
    {
        $admin = $this->createUser('Quản trị xem PDF', 'admin-preview-pdf@example.test', true);
        $tutor = $this->createUser('Gia sư PDF', 'tutor-preview-pdf@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-10 08:24:00');
        $documentId = $this->createDocument($tutorId, 'proof-inline.pdf', 'Minh chứng PDF');

        $response = $this->actingAs($admin)->get(route(
            'admin.tutors.documents.download',
            [
                'tutorProfile' => $tutorId,
                'document' => $documentId,
                'disposition' => 'inline',
            ]
        ));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith(
            'inline;',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_admin_can_preview_an_image_inline(): void
    {
        $admin = $this->createUser('Quản trị xem ảnh', 'admin-preview-image@example.test', true);
        $tutor = $this->createUser('Gia sư ảnh', 'tutor-preview-image@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-10 08:24:00');
        $documentId = $this->createDocument($tutorId, 'student-card.png', 'Thẻ sinh viên');

        $response = $this->actingAs($admin)
            ->get(route('admin.tutors.documents.download', [
                'tutorProfile' => $tutorId,
                'document' => $documentId,
                'disposition' => 'inline',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith(
            'inline;',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_document_from_another_profile_cannot_be_previewed(): void
    {
        $admin = $this->createUser('Quản trị ownership', 'admin-preview-owner@example.test', true);
        $firstTutor = $this->createUser('Gia sư thứ nhất', 'first-preview-owner@example.test');
        $secondTutor = $this->createUser('Gia sư thứ hai', 'second-preview-owner@example.test');
        $firstTutorId = $this->createTutor($firstTutor, TutorProfile::STATUS_PENDING, '2026-09-10 08:24:00');
        $secondTutorId = $this->createTutor($secondTutor, TutorProfile::STATUS_PENDING, '2026-09-10 08:25:00');
        $secondDocumentId = $this->createDocument($secondTutorId, 'private-proof.pdf', 'Tài liệu riêng');

        $this->actingAs($admin)
            ->get(route('admin.tutors.documents.download', [
                'tutorProfile' => $firstTutorId,
                'document' => $secondDocumentId,
                'disposition' => 'inline',
            ]))
            ->assertNotFound();
    }

    public function test_normal_user_cannot_preview_a_tutor_document(): void
    {
        $member = $this->createUser('Người dùng xem file', 'member-preview-document@example.test');
        $tutor = $this->createUser('Gia sư private', 'tutor-private-document@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-10 08:24:00');
        $documentId = $this->createDocument($tutorId, 'admin-only.pdf', 'Chỉ Admin');

        $this->actingAs($member)
            ->get(route('admin.tutors.documents.download', [
                'tutorProfile' => $tutorId,
                'document' => $documentId,
                'disposition' => 'inline',
            ]))
            ->assertForbidden();
    }

    public function test_approved_and_rejected_profile_documents_remain_previewable(): void
    {
        $admin = $this->createUser('Quản trị xem lại', 'admin-preview-terminal@example.test', true);

        foreach ([TutorProfile::STATUS_APPROVED, TutorProfile::STATUS_REJECTED] as $status) {
            $suffix = strtolower($status);
            $tutor = $this->createUser('Gia sư '.$status, 'tutor-preview-'.$suffix.'@example.test');
            $tutorId = $this->createTutor($tutor, $status, '2026-09-10 08:24:00');
            $documentId = $this->createDocument(
                $tutorId,
                $suffix.'-proof.pdf',
                'Minh chứng '.$status
            );

            $this->actingAs($admin)
                ->get(route('admin.tutors.documents.download', [
                    'tutorProfile' => $tutorId,
                    'document' => $documentId,
                    'disposition' => 'inline',
                ]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_document_section_has_separate_preview_and_download_actions(): void
    {
        $admin = $this->createUser('Quản trị xem actions', 'admin-preview-actions@example.test', true);
        $tutor = $this->createUser('Gia sư actions', 'tutor-preview-actions@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_APPROVED, '2026-09-10 08:24:00');
        $documentId = $this->createDocument($tutorId, 'actions-proof.pdf', 'Minh chứng actions');
        $downloadUrl = route('admin.tutors.documents.download', [$tutorId, $documentId]);
        $previewUrl = route('admin.tutors.documents.download', [
            'tutorProfile' => $tutorId,
            'document' => $documentId,
            'disposition' => 'inline',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertOk()
            ->assertSeeText('Xem')
            ->assertSeeText('Tải xuống')
            ->assertSee($downloadUrl, false)
            ->assertSee(e($previewUrl), false);
    }

    public function test_admin_can_approve_a_pending_submitted_profile(): void
    {
        $this->travelTo(Carbon::parse('2026-09-13 14:30:00'));
        $admin = $this->createUser('Quản trị duyệt', 'admin-approve@example.test', true);
        $tutor = $this->createUser('Gia sư được duyệt', 'approved-tutor@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-12 08:00:00');
        DB::table('tutor_profiles')->where('tutor_profile_id', $tutorId)->update([
            'rejected_at' => '2026-09-12 09:00:00',
            'rejection_reason' => 'Dữ liệu cũ cần được xóa.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tutors.approve', $tutorId))
            ->assertRedirect(route('admin.tutors.show', $tutorId))
            ->assertSessionHas('success', 'Đã duyệt hồ sơ gia sư thành công.');

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $tutorId,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => '2026-09-13 14:30:00',
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
        $this->assertDatabaseHas('tutor_profile_reviews', [
            'tutor_profile_id' => $tutorId,
            'reviewer_user_id' => $admin->user_id,
            'review_source' => 'ADMIN',
            'review_result' => 'PASSED',
            'notes' => null,
            'reviewed_at' => '2026-09-13 14:30:00',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $tutor->user_id,
            'notification_type' => 'PROFILE_APPROVED',
            'related_type' => 'TUTOR_PROFILE',
            'related_id' => $tutorId,
        ]);
    }

    public function test_admin_can_reject_a_pending_submitted_profile_with_a_reason(): void
    {
        $this->travelTo(Carbon::parse('2026-09-13 15:45:00'));
        $admin = $this->createUser('Quản trị từ chối', 'admin-reject@example.test', true);
        $tutor = $this->createUser('Gia sư bị từ chối', 'rejected-tutor@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-12 08:00:00');
        DB::table('tutor_profiles')->where('tutor_profile_id', $tutorId)->update([
            'approved_at' => '2026-09-12 09:00:00',
        ]);
        $reason = 'Minh chứng học vấn chưa đầy đủ và chưa rõ ràng.';

        $this->actingAs($admin)
            ->patch(route('admin.tutors.reject', $tutorId), ['rejection_reason' => $reason])
            ->assertRedirect(route('admin.tutors.show', $tutorId))
            ->assertSessionHas('success', 'Đã từ chối hồ sơ gia sư.');

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $tutorId,
            'approval_status' => TutorProfile::STATUS_REJECTED,
            'approved_at' => null,
            'rejected_at' => '2026-09-13 15:45:00',
            'rejection_reason' => $reason,
        ]);
        $this->assertDatabaseHas('tutor_profile_reviews', [
            'tutor_profile_id' => $tutorId,
            'reviewer_user_id' => $admin->user_id,
            'review_source' => 'ADMIN',
            'review_result' => 'REJECTED',
            'notes' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $tutor->user_id,
            'notification_type' => 'PROFILE_REJECTED',
            'related_type' => 'TUTOR_PROFILE',
            'related_id' => $tutorId,
        ]);
    }

    public function test_approving_profile_marks_its_pending_evidence_as_approved(): void
    {
        [$admin, $tutorId] = $this->createAdminAndPendingTutor('approve-evidence');
        $documentId = $this->createDocument(
            $tutorId,
            'pending-evidence.pdf',
            'Minh chứng chờ xác minh'
        );

        $this->actingAs($admin)
            ->patch(route('admin.tutors.approve', $tutorId))
            ->assertRedirect(route('admin.tutors.show', $tutorId));

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'tutor_profile_id' => $tutorId,
            'verification_status' => TutorDocument::STATUS_APPROVED,
        ]);

        $this->get(route('admin.tutors.show', $tutorId))
            ->assertOk()
            ->assertSeeText('Đã xác minh');
    }

    public function test_approving_profile_keeps_already_approved_evidence_approved(): void
    {
        [$admin, $tutorId] = $this->createAdminAndPendingTutor('keep-approved-evidence');
        $documentId = $this->createDocument(
            $tutorId,
            'approved-evidence.pdf',
            'Minh chứng đã xác minh',
            TutorDocument::STATUS_APPROVED
        );

        $this->actingAs($admin)
            ->patch(route('admin.tutors.approve', $tutorId))
            ->assertRedirect(route('admin.tutors.show', $tutorId));

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'verification_status' => TutorDocument::STATUS_APPROVED,
        ]);
    }

    public function test_approving_profile_does_not_update_another_profile_evidence(): void
    {
        [$admin, $approvedTutorId] = $this->createAdminAndPendingTutor('evidence-scope');
        $otherTutor = $this->createUser('Gia sư khác', 'other-evidence-scope@example.test');
        $otherTutorId = $this->createTutor(
            $otherTutor,
            TutorProfile::STATUS_PENDING,
            '2026-09-12 08:05:00'
        );
        $otherDocumentId = $this->createDocument(
            $otherTutorId,
            'other-profile-evidence.pdf',
            'Minh chứng hồ sơ khác'
        );

        $this->actingAs($admin)
            ->patch(route('admin.tutors.approve', $approvedTutorId))
            ->assertRedirect(route('admin.tutors.show', $approvedTutorId));

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $otherDocumentId,
            'tutor_profile_id' => $otherTutorId,
            'verification_status' => TutorDocument::STATUS_PENDING,
        ]);
    }

    public function test_rejecting_profile_does_not_change_pending_evidence_status(): void
    {
        [$admin, $tutorId] = $this->createAdminAndPendingTutor('reject-evidence');
        $documentId = $this->createDocument(
            $tutorId,
            'rejected-profile-evidence.pdf',
            'Minh chứng hồ sơ bị từ chối'
        );

        $this->actingAs($admin)
            ->patch(route('admin.tutors.reject', $tutorId), [
                'rejection_reason' => 'Minh chứng chưa đáp ứng yêu cầu xét duyệt.',
            ])
            ->assertRedirect(route('admin.tutors.show', $tutorId));

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'tutor_profile_id' => $tutorId,
            'verification_status' => TutorDocument::STATUS_PENDING,
        ]);
    }

    public function test_rejection_reason_is_required_and_the_modal_reopens_with_the_error(): void
    {
        $admin = $this->createUser('Quản trị validation', 'admin-required@example.test', true);
        $tutor = $this->createUser('Gia sư validation', 'tutor-required@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-12 08:00:00');

        $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('admin.tutors.show', $tutorId))
            ->patch(route('admin.tutors.reject', $tutorId), ['rejection_reason' => ''])
            ->assertOk()
            ->assertSee('data-auto-open="true"', false)
            ->assertSeeText('Vui lòng nhập lý do từ chối.');

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_rejection_reason_must_have_at_least_ten_characters(): void
    {
        [$admin, $tutorId] = $this->createAdminAndPendingTutor('short');

        $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('admin.tutors.show', $tutorId))
            ->patch(route('admin.tutors.reject', $tutorId), ['rejection_reason' => 'Quá ngắn'])
            ->assertOk()
            ->assertSee('data-auto-open="true"', false)
            ->assertSeeText('Lý do từ chối phải có ít nhất 10 ký tự.')
            ->assertSee('>Quá ngắn</textarea>', false);

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_rejection_reason_cannot_exceed_one_thousand_characters(): void
    {
        [$admin, $tutorId] = $this->createAdminAndPendingTutor('long');

        $this->actingAs($admin)
            ->patch(route('admin.tutors.reject', $tutorId), [
                'rejection_reason' => str_repeat('a', 1001),
            ])
            ->assertSessionHasErrors([
                'rejection_reason' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
            ]);

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_normal_user_cannot_approve_a_profile(): void
    {
        [$member, $tutorId] = $this->createMemberAndPendingTutor('approve');

        $this->actingAs($member)
            ->patch(route('admin.tutors.approve', $tutorId))
            ->assertForbidden();

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_normal_user_cannot_reject_a_profile(): void
    {
        [$member, $tutorId] = $this->createMemberAndPendingTutor('reject');

        $this->actingAs($member)
            ->patch(route('admin.tutors.reject', $tutorId), [
                'rejection_reason' => 'Lý do hợp lệ nhưng không có quyền.',
            ])
            ->assertForbidden();

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_admin_cannot_approve_a_draft_profile(): void
    {
        [$admin, $tutorId] = $this->createAdminAndDraftTutor('approve');

        $this->actingAs($admin)
            ->patch(route('admin.tutors.approve', $tutorId))
            ->assertNotFound();

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_admin_cannot_reject_a_draft_profile(): void
    {
        [$admin, $tutorId] = $this->createAdminAndDraftTutor('reject');

        $this->actingAs($admin)
            ->patch(route('admin.tutors.reject', $tutorId), [
                'rejection_reason' => 'Hồ sơ nháp không thể bị từ chối.',
            ])
            ->assertNotFound();

        $this->assertPendingWithoutReview($tutorId);
    }

    public function test_approved_profile_cannot_be_approved_again(): void
    {
        $this->assertTerminalProfileCannotBeProcessed(TutorProfile::STATUS_APPROVED, 'approve');
    }

    public function test_approved_profile_cannot_be_rejected(): void
    {
        $this->assertTerminalProfileCannotBeProcessed(TutorProfile::STATUS_APPROVED, 'reject');
    }

    public function test_rejected_profile_cannot_be_approved(): void
    {
        $this->assertTerminalProfileCannotBeProcessed(TutorProfile::STATUS_REJECTED, 'approve');
    }

    public function test_rejected_profile_cannot_be_rejected_again(): void
    {
        $this->assertTerminalProfileCannotBeProcessed(TutorProfile::STATUS_REJECTED, 'reject');
    }

    public function test_approved_profile_view_shows_result_time_without_actions(): void
    {
        $admin = $this->createUser('Quản trị xem duyệt', 'admin-view-approved@example.test', true);
        $tutor = $this->createUser('Gia sư đã duyệt', 'view-approved@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_APPROVED, '2026-09-10 08:24:00');

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertOk()
            ->assertSeeText('Hồ sơ đã được duyệt')
            ->assertSeeText('Duyệt lúc 11/09/2026 10:00')
            ->assertDontSee('data-admin-review-open=', false);
    }

    public function test_rejected_profile_view_shows_result_time_and_reason_without_actions(): void
    {
        $admin = $this->createUser('Quản trị xem từ chối', 'admin-view-rejected@example.test', true);
        $tutor = $this->createUser('Gia sư đã từ chối', 'view-rejected@example.test');
        $tutorId = $this->createTutor($tutor, TutorProfile::STATUS_REJECTED, '2026-09-10 08:24:00');
        DB::table('tutor_profiles')->where('tutor_profile_id', $tutorId)->update([
            'rejected_at' => '2026-09-12 16:20:00',
            'rejection_reason' => 'Minh chứng học vấn chưa đầy đủ.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $tutorId))
            ->assertOk()
            ->assertSeeText('Hồ sơ đã bị từ chối')
            ->assertSeeText('Từ chối lúc 12/09/2026 16:20')
            ->assertSeeText('Lý do: Minh chứng học vấn chưa đầy đủ.')
            ->assertDontSee('data-admin-review-open=', false);
    }

    private function createAdminAndPendingTutor(string $suffix): array
    {
        $admin = $this->createUser('Quản trị '.$suffix, 'admin-'.$suffix.'@example.test', true);
        $tutor = $this->createUser('Gia sư '.$suffix, 'tutor-'.$suffix.'@example.test');

        return [
            $admin,
            $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-12 08:00:00'),
        ];
    }

    private function createMemberAndPendingTutor(string $suffix): array
    {
        $member = $this->createUser('Thành viên '.$suffix, 'member-'.$suffix.'@example.test');
        $tutor = $this->createUser('Gia sư quyền '.$suffix, 'tutor-auth-'.$suffix.'@example.test');

        return [
            $member,
            $this->createTutor($tutor, TutorProfile::STATUS_PENDING, '2026-09-12 08:00:00'),
        ];
    }

    private function createAdminAndDraftTutor(string $suffix): array
    {
        $admin = $this->createUser('Quản trị draft '.$suffix, 'admin-draft-'.$suffix.'@example.test', true);
        $tutor = $this->createUser('Gia sư draft '.$suffix, 'tutor-draft-'.$suffix.'@example.test');

        return [
            $admin,
            $this->createTutor($tutor, TutorProfile::STATUS_PENDING, null),
        ];
    }

    private function assertTerminalProfileCannotBeProcessed(string $status, string $action): void
    {
        $suffix = strtolower($status).'-'.$action;
        $admin = $this->createUser('Quản trị terminal '.$suffix, 'admin-terminal-'.$suffix.'@example.test', true);
        $tutor = $this->createUser('Gia sư terminal '.$suffix, 'tutor-terminal-'.$suffix.'@example.test');
        $tutorId = $this->createTutor($tutor, $status, '2026-09-12 08:00:00');
        $attributes = $action === 'reject'
            ? ['rejection_reason' => 'Không được phép xử lý lại hồ sơ này.']
            : [];

        $this->actingAs($admin)
            ->patch(route('admin.tutors.'.$action, $tutorId), $attributes)
            ->assertRedirect(route('admin.tutors.show', $tutorId))
            ->assertSessionHas('error', 'Hồ sơ gia sư đã được xử lý trước đó.');

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $tutorId,
            'approval_status' => $status,
        ]);
        $this->assertDatabaseCount('tutor_profile_reviews', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function assertPendingWithoutReview(int $tutorId): void
    {
        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $tutorId,
            'approval_status' => TutorProfile::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('tutor_profile_reviews', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function createUser(string $name, string $email, bool $isAdmin = false): User
    {
        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => $email,
            'full_name' => $name,
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

    private function createDocument(
        int $tutorId,
        string $fileName,
        string $documentName,
        string $verificationStatus = TutorDocument::STATUS_PENDING
    ): int {
        $path = TutorDocument::STORAGE_PREFIX.'/'.$tutorId.'/'.$fileName;
        Storage::disk('local')->put($path, 'private-proof');

        return DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $tutorId,
            'document_type' => TutorDocument::TYPE_CERTIFICATE,
            'document_name' => $documentName,
            'file_url' => $path,
            'verification_status' => $verificationStatus,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTutor(
        User $user,
        string $status,
        ?string $submittedAt,
        bool $supportsOnline = true,
        bool $supportsOffline = false
    ): int {
        return DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->user_id,
            'headline' => 'Dạy chắc kiến thức, theo sát tiến độ',
            'bio' => 'Thông tin giới thiệu lấy từ hồ sơ kiểm thử.',
            'education_summary' => 'Cử nhân Sư phạm.',
            'teaching_experience' => 'Ba năm dạy kèm.',
            'hourly_rate' => 180000,
            'supports_online' => $supportsOnline,
            'supports_offline' => $supportsOffline,
            'approval_status' => $status,
            'approved_at' => $status === TutorProfile::STATUS_APPROVED
                ? '2026-09-11 10:00:00'
                : null,
            'submitted_at' => $submittedAt,
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
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('education_summary', 500)->nullable();
            $table->text('teaching_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('supports_online')->default(false);
            $table->boolean('supports_offline')->default(false);
            $table->string('approval_status', 30)->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
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
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name', 100);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('tutor_subject_level_id');
            $table->unsignedBigInteger('tutor_subject_id');
            $table->unsignedBigInteger('subject_level_id');
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_code', 20)->nullable();
            $table->string('province_name', 100);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_code', 20);
            $table->string('ward_name', 100);
            $table->string('ward_type', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_teaching_areas', function (Blueprint $table): void {
            $table->bigIncrements('teaching_area_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('ward_id');
            $table->timestamps();
        });

        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('time_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('slot_name', 50)->nullable();
            $table->string('status', 20)->default('ACTIVE');
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

        Schema::create('tutor_documents', function (Blueprint $table): void {
            $table->bigIncrements('document_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('document_type', 50);
            $table->string('document_name')->nullable();
            $table->string('file_url', 500);
            $table->string('verification_status', 30)->default('PENDING');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profile_change_requests', function (Blueprint $table): void {
            $table->bigIncrements('change_request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('change_type', 40);
            $table->string('change_action', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_profile_reviews', function (Blueprint $table): void {
            $table->bigIncrements('review_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('review_source', 20);
            $table->string('review_result', 30);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->bigIncrements('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->string('notification_type', 50);
            $table->string('title', 150);
            $table->text('message');
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
}
