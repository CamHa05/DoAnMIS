<?php

namespace Tests\Feature;

use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\TutorProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TutorProfileManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->createSchema();
    }

    public function test_approved_tutor_updates_education_and_experience_immediately(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);
        $wardId = $this->createWard();
        $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_EDUCATION,
            ['value' => 'Bản học vấn cũ đang chờ duyệt.']
        );
        $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_EXPERIENCE,
            ['value' => 'Bản kinh nghiệm cũ đang chờ duyệt.']
        );

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.update'), [
                'headline' => 'Gia sư Toán luyện thi đại học',
                'bio' => 'Giới thiệu mới được áp dụng ngay.',
                'education_summary' => 'Thạc sĩ Sư phạm Toán.',
                'teaching_experience' => 'Năm năm luyện thi THPT.',
                'hourly_rate' => '220000',
                'supports_online' => '1',
                'supports_offline' => '1',
                'ward_ids' => [$wardId],
                'subject_ids' => [$subjectId],
                'subject_levels' => [$subjectId => [$levelId]],
            ])
            ->assertRedirect(route('tutor-area.profile.edit'));

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $profileId,
            'headline' => 'Gia sư Toán luyện thi đại học',
            'bio' => 'Giới thiệu mới được áp dụng ngay.',
            'hourly_rate' => 220000,
            'supports_online' => true,
            'supports_offline' => true,
            'education_summary' => 'Thạc sĩ Sư phạm Toán.',
            'teaching_experience' => 'Năm năm luyện thi THPT.',
            'approval_status' => TutorProfile::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
            'ward_id' => $wardId,
        ]);
        $this->assertDatabaseMissing('tutor_profile_change_requests', [
            'tutor_profile_id' => $profileId,
            'change_type' => TutorProfileChangeRequest::TYPE_EDUCATION,
        ]);
        $this->assertDatabaseMissing('tutor_profile_change_requests', [
            'tutor_profile_id' => $profileId,
            'change_type' => TutorProfileChangeRequest::TYPE_EXPERIENCE,
        ]);
    }

    public function test_edit_view_shows_direct_fields_without_unlocking_initial_flow(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();

        $this->actingAs($tutor)
            ->get(route('tutor-area.profile.edit'))
            ->assertOk()
            ->assertViewIs('tutor-area.profile-edit')
            ->assertSeeText('Chỉnh sửa hồ sơ gia sư')
            ->assertSeeText('Thông tin này được cập nhật ngay sau khi bạn lưu hồ sơ.')
            ->assertSee('Cử nhân Sư phạm Toán.')
            ->assertDontSeeText('Thông tin mới chỉ thay thế dữ liệu hiện tại sau khi Admin duyệt.');

        DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->update([
            'approval_status' => TutorProfile::STATUS_PENDING,
        ]);

        $this->actingAs($tutor)
            ->get(route('tutor-area.profile.edit'))
            ->assertRedirect(route('tutor-registration.confirmation.edit'));
    }

    public function test_public_profile_only_reads_approved_snapshot_and_approved_documents(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_EDUCATION,
            ['value' => 'Dữ liệu học vấn đang chờ duyệt.']
        );
        $approvedPath = TutorDocument::STORAGE_PREFIX.'/'.$profileId.'/approved.png';
        $pendingPath = TutorDocument::STORAGE_PREFIX.'/'.$profileId.'/pending.png';
        Storage::disk('local')->put($approvedPath, 'approved');
        Storage::disk('local')->put($pendingPath, 'pending');
        DB::table('tutor_documents')->insert([
            [
                'tutor_profile_id' => $profileId,
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'TOEIC 700',
                'file_url' => $approvedPath,
                'verification_status' => TutorDocument::STATUS_APPROVED,
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tutor_profile_id' => $profileId,
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'HSK 5 đang chờ',
                'file_url' => $pendingPath,
                'verification_status' => TutorDocument::STATUS_PENDING,
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('tutors.show', $profileId))
            ->assertOk()
            ->assertSeeText('Cử nhân Sư phạm Toán.')
            ->assertSeeText('TOEIC 700')
            ->assertDontSeeText('Dữ liệu học vấn đang chờ duyệt.')
            ->assertDontSeeText('HSK 5 đang chờ');
    }

    public function test_admin_approves_or_rejects_each_sensitive_change_independently(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        $admin = $this->createUser('Admin', 'admin-profile@example.test', true);
        $educationChange = $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_EDUCATION,
            ['value' => 'Thạc sĩ Sư phạm Toán.']
        );
        $experienceChange = $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_EXPERIENCE,
            ['value' => 'Năm năm giảng dạy.']
        );

        $this->actingAs($admin)
            ->get(route('admin.tutors.show', $profileId))
            ->assertOk()
            ->assertSeeText('Thay đổi chờ xét duyệt')
            ->assertSeeText('Cử nhân Sư phạm Toán.')
            ->assertSeeText('Thạc sĩ Sư phạm Toán.')
            ->assertSeeText('Ba năm giảng dạy.')
            ->assertSeeText('Năm năm giảng dạy.');

        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.approve', [$profileId, $educationChange]))
            ->assertRedirect(route('admin.tutors.show', $profileId));
        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.reject', [$profileId, $experienceChange]), [
                'change_rejection_reason' => 'Vui lòng bổ sung mô tả kinh nghiệm cụ thể hơn.',
            ])
            ->assertRedirect(route('admin.tutors.show', $profileId));

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $profileId,
            'education_summary' => 'Thạc sĩ Sư phạm Toán.',
            'teaching_experience' => 'Ba năm giảng dạy.',
            'approval_status' => TutorProfile::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('tutor_profile_change_requests', [
            'change_request_id' => $educationChange,
            'status' => TutorProfileChangeRequest::STATUS_APPROVED,
            'reviewer_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('tutor_profile_change_requests', [
            'change_request_id' => $experienceChange,
            'status' => TutorProfileChangeRequest::STATUS_REJECTED,
        ]);
    }

    public function test_specialization_updates_immediately_without_admin_change_request(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$mathId, $mathLevelId] = $this->createSubject('Toán', 'THPT');
        [$physicsId, $physicsLevelId] = $this->createSubject('Vật lý', 'THPT');
        $this->attachSpecialization($profileId, $mathId, $mathLevelId);
        $this->createChange(
            $profileId,
            TutorProfileChangeRequest::TYPE_SPECIALIZATION,
            ['specializations' => [
                ['subject_id' => $mathId, 'subject_level_ids' => [$mathLevelId]],
                ['subject_id' => $physicsId, 'subject_level_ids' => [$physicsLevelId]],
            ]],
            TutorProfileChangeRequest::ACTION_REPLACE
        );

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.update'), [
                'headline' => 'Gia sư Toán và Vật lý',
                'bio' => 'Giới thiệu hiện tại.',
                'education_summary' => 'Cử nhân Sư phạm Toán.',
                'teaching_experience' => 'Ba năm giảng dạy.',
                'hourly_rate' => '180000',
                'supports_online' => '1',
                'subject_ids' => [$mathId, $physicsId],
                'subject_levels' => [
                    $mathId => [$mathLevelId],
                    $physicsId => [$physicsLevelId],
                ],
            ])
            ->assertRedirect(route('tutor-area.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tutor_subjects', [
            'tutor_profile_id' => $profileId,
            'subject_id' => $mathId,
        ]);
        $this->assertDatabaseHas('tutor_subjects', [
            'tutor_profile_id' => $profileId,
            'subject_id' => $physicsId,
        ]);
        $this->assertDatabaseMissing('tutor_profile_change_requests', [
            'tutor_profile_id' => $profileId,
            'change_type' => TutorProfileChangeRequest::TYPE_SPECIALIZATION,
        ]);

        $this->actingAs($tutor)
            ->get(route('tutor-area.profile'))
            ->assertOk()
            ->assertSeeText('Vật lý');
        $this->get(route('tutors.show', $profileId))
            ->assertOk()
            ->assertSeeText('Vật lý');
    }

    public function test_specialization_editor_uses_compact_add_subject_then_level_flow(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);

        $this->actingAs($tutor)
            ->get(route('tutor-area.profile.edit'))
            ->assertOk()
            ->assertSee('data-specialty-manager', false)
            ->assertSeeText('Thêm chuyên môn')
            ->assertSeeText('Chọn môn học')
            ->assertDontSee('tutor-profile-editor__subject-picker', false);
    }

    public function test_teaching_area_editor_uses_province_then_ward_flow_instead_of_a_large_multi_select(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        $wardId = $this->createWard();
        DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->update([
            'supports_offline' => true,
        ]);
        DB::table('tutor_teaching_areas')->insert([
            'tutor_profile_id' => $profileId,
            'ward_id' => $wardId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->get(route('tutor-area.profile.edit'))
            ->assertOk()
            ->assertViewHas('teachingAreaCatalog', function (array $catalog) use ($wardId): bool {
                return data_get($catalog, '0.name') === 'TP. Hồ Chí Minh'
                    && data_get($catalog, '0.wards.0.id') === $wardId
                    && data_get($catalog, '0.wards.0.name') === 'Phường Bến Thành';
            })
            ->assertSee('data-teaching-area-manager', false)
            ->assertSeeText('Thêm khu vực')
            ->assertSeeText('Chọn tỉnh/thành phố')
            ->assertSeeText('Phường Bến Thành')
            ->assertSeeText('TP. Hồ Chí Minh')
            ->assertDontSee('tutor-profile-editor__area-field', false)
            ->assertDontSee('name="ward_ids[]" multiple', false);
    }

    public function test_online_only_update_does_not_require_areas_and_clears_old_areas(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);
        $wardId = $this->createWard();
        DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->update([
            'supports_offline' => true,
        ]);
        DB::table('tutor_teaching_areas')->insert([
            'tutor_profile_id' => $profileId,
            'ward_id' => $wardId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.update'), $this->validProfilePayload($subjectId, $levelId, [
                'supports_online' => '1',
            ]))
            ->assertRedirect(route('tutor-area.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $profileId,
            'supports_online' => true,
            'supports_offline' => false,
        ]);
        $this->assertDatabaseMissing('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
        ]);
    }

    public function test_offline_update_requires_at_least_one_teaching_area(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);

        $this->actingAs($tutor)
            ->from(route('tutor-area.profile.edit'))
            ->put(route('tutor-area.profile.update'), $this->validProfilePayload($subjectId, $levelId, [
                'headline' => 'Không được lưu khi thiếu khu vực',
                'supports_offline' => '1',
            ]))
            ->assertRedirect(route('tutor-area.profile.edit'))
            ->assertSessionHasErrors('ward_ids');

        $this->assertDatabaseHas('tutor_profiles', [
            'tutor_profile_id' => $profileId,
            'headline' => 'Gia sư Toán',
            'supports_offline' => false,
        ]);
        $this->assertDatabaseMissing('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
        ]);
    }

    public function test_offline_update_rejects_duplicate_teaching_areas(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);
        $wardId = $this->createWard();

        $this->actingAs($tutor)
            ->from(route('tutor-area.profile.edit'))
            ->put(route('tutor-area.profile.update'), $this->validProfilePayload($subjectId, $levelId, [
                'supports_offline' => '1',
                'ward_ids' => [$wardId, $wardId],
            ]))
            ->assertRedirect(route('tutor-area.profile.edit'))
            ->assertSessionHasErrors('ward_ids.1');

        $this->assertDatabaseMissing('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
        ]);
    }

    public function test_offline_update_syncs_added_edited_and_deleted_teaching_areas(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$subjectId, $levelId] = $this->createSubject('Toán', 'THPT');
        $this->attachSpecialization($profileId, $subjectId, $levelId);
        $removedWardId = $this->createWard('TP. Hồ Chí Minh', 'Phường Bến Thành');
        $keptWardId = $this->createWard('TP. Hà Nội', 'Phường Ba Đình');
        $addedWardId = $this->createWard('TP. Đà Nẵng', 'Phường Hải Châu');
        DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->update([
            'supports_offline' => true,
        ]);
        DB::table('tutor_teaching_areas')->insert([
            [
                'tutor_profile_id' => $profileId,
                'ward_id' => $removedWardId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tutor_profile_id' => $profileId,
                'ward_id' => $keptWardId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.update'), $this->validProfilePayload($subjectId, $levelId, [
                'supports_online' => '1',
                'supports_offline' => '1',
                'ward_ids' => [$keptWardId, $addedWardId],
            ]))
            ->assertRedirect(route('tutor-area.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
            'ward_id' => $removedWardId,
        ]);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
            'ward_id' => $keptWardId,
        ]);
        $this->assertDatabaseHas('tutor_teaching_areas', [
            'tutor_profile_id' => $profileId,
            'ward_id' => $addedWardId,
        ]);
        $this->assertSame(
            2,
            DB::table('tutor_teaching_areas')->where('tutor_profile_id', $profileId)->count()
        );
    }

    public function test_approved_document_is_not_replaced_or_deleted_before_admin_approval(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        $admin = $this->createUser('Admin', 'admin-document@example.test', true);
        $oldPath = TutorDocument::STORAGE_PREFIX.'/'.$profileId.'/old-proof.png';
        Storage::disk('local')->put($oldPath, 'old-file');
        $documentId = DB::table('tutor_documents')->insertGetId([
            'tutor_profile_id' => $profileId,
            'document_type' => TutorDocument::TYPE_CERTIFICATE,
            'document_name' => 'TOEIC 700',
            'file_url' => $oldPath,
            'verification_status' => TutorDocument::STATUS_APPROVED,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.documents.update', $documentId), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'TOEIC 800',
                'document' => UploadedFile::fake()->image('new-proof.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'document_name' => 'TOEIC 700',
            'file_url' => $oldPath,
            'verification_status' => TutorDocument::STATUS_APPROVED,
        ]);
        Storage::disk('local')->assertExists($oldPath);
        $replaceChange = DB::table('tutor_profile_change_requests')
            ->where('target_id', $documentId)
            ->where('change_action', TutorProfileChangeRequest::ACTION_REPLACE)
            ->value('change_request_id');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->getKey(),
            'notification_type' => 'PROFILE_CHANGE_SUBMITTED',
            'related_type' => 'TUTOR_PROFILE',
            'related_id' => $profileId,
        ]);
        $adminNotificationId = DB::table('notifications')
            ->where('user_id', $admin->getKey())
            ->where('notification_type', 'PROFILE_CHANGE_SUBMITTED')
            ->value('notification_id');
        $this->actingAs($admin)
            ->post(route('notifications.open', $adminNotificationId))
            ->assertRedirect(route('admin.tutors.show', $profileId));

        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.approve', [$profileId, $replaceChange]))
            ->assertRedirect(route('admin.tutors.show', $profileId));

        $updatedPath = DB::table('tutor_documents')->where('document_id', $documentId)->value('file_url');
        $this->assertNotSame($oldPath, $updatedPath);
        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'document_name' => 'TOEIC 800',
            'verification_status' => TutorDocument::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $tutor->getKey(),
            'notification_type' => 'PROFILE_CHANGE_APPROVED',
            'related_type' => 'TUTOR_PROFILE_CHANGE',
            'related_id' => $replaceChange,
        ]);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($updatedPath);

        $this->actingAs($tutor)
            ->delete(route('tutor-area.profile.documents.destroy', $documentId))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tutor_documents', ['document_id' => $documentId]);
        $deleteChange = DB::table('tutor_profile_change_requests')
            ->where('target_id', $documentId)
            ->where('status', TutorProfileChangeRequest::STATUS_PENDING)
            ->value('change_request_id');

        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.approve', [$profileId, $deleteChange]))
            ->assertRedirect(route('admin.tutors.show', $profileId));
        $this->assertDatabaseMissing('tutor_documents', ['document_id' => $documentId]);
        Storage::disk('local')->assertMissing($updatedPath);
    }

    public function test_new_document_can_be_rejected_edited_resubmitted_and_approved(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        $admin = $this->createUser('Admin', 'admin-resubmit@example.test', true);

        $this->actingAs($tutor)
            ->post(route('tutor-area.profile.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'HSK 5',
                'document' => UploadedFile::fake()->image('hsk.png'),
            ])
            ->assertSessionHasNoErrors();

        $documentId = DB::table('tutor_documents')->where('document_name', 'HSK 5')->value('document_id');
        $changeId = DB::table('tutor_profile_change_requests')->where('target_id', $documentId)->value('change_request_id');
        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'verification_status' => TutorDocument::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.reject', [$profileId, $changeId]), [
                'change_rejection_reason' => 'Ảnh minh chứng chưa rõ, vui lòng gửi lại.',
            ])
            ->assertRedirect(route('admin.tutors.show', $profileId));
        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'verification_status' => TutorDocument::STATUS_REJECTED,
        ]);

        $this->actingAs($tutor)
            ->put(route('tutor-area.profile.documents.update', $documentId), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'HSK 5 bản rõ',
                'document' => UploadedFile::fake()->image('hsk-clear.png'),
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'document_name' => 'HSK 5 bản rõ',
            'verification_status' => TutorDocument::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('tutor_profile_change_requests', [
            'change_request_id' => $changeId,
            'status' => TutorProfileChangeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tutors.changes.approve', [$profileId, $changeId]))
            ->assertRedirect(route('admin.tutors.show', $profileId));
        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $documentId,
            'verification_status' => TutorDocument::STATUS_APPROVED,
        ]);
    }

    public function test_availability_slots_are_updated_directly_and_scoped_to_owner(): void
    {
        [$tutor, $profileId] = $this->createApprovedTutor();
        [$otherTutor, $otherProfileId] = $this->createApprovedTutor();
        $slotId = DB::table('time_slots')->insertGetId([
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'slot_name' => 'Buổi sáng',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->post(route('tutor-area.profile.availability.store'), [
                'day_of_week' => 1,
                'time_slot_id' => $slotId,
            ])
            ->assertSessionHasNoErrors();
        $availabilityId = DB::table('tutor_availabilities')
            ->where('tutor_profile_id', $profileId)
            ->value('availability_id');

        $this->actingAs($tutor)
            ->patch(route('tutor-area.profile.availability.update', $availabilityId), [
                'day_of_week' => 3,
                'time_slot_id' => $slotId,
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $availabilityId,
            'tutor_profile_id' => $profileId,
            'day_of_week' => 3,
            'is_available' => true,
        ]);

        $this->actingAs($otherTutor)
            ->delete(route('tutor-area.profile.availability.destroy', $availabilityId))
            ->assertNotFound();
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $availabilityId,
            'is_available' => true,
        ]);

        $this->actingAs($tutor)
            ->delete(route('tutor-area.profile.availability.destroy', $availabilityId))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tutor_availabilities', [
            'availability_id' => $availabilityId,
            'is_available' => false,
        ]);
        $this->assertNotSame($profileId, $otherProfileId);
    }

    private function createApprovedTutor(): array
    {
        $user = $this->createUser('Gia sư kiểm thử', fake()->unique()->safeEmail());
        $profileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $user->getKey(),
            'headline' => 'Gia sư Toán',
            'bio' => 'Giới thiệu hiện tại.',
            'education_summary' => 'Cử nhân Sư phạm Toán.',
            'teaching_experience' => 'Ba năm giảng dạy.',
            'hourly_rate' => 180000,
            'supports_online' => true,
            'supports_offline' => false,
            'approval_status' => TutorProfile::STATUS_APPROVED,
            'approved_at' => now(),
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $profileId];
    }

    private function createUser(string $name, string $email, bool $admin = false): User
    {
        $user = User::query()->create([
            'email' => $email,
            'full_name' => $name,
        ]);
        $user->forceFill([
            'is_admin' => $admin,
            'status' => 'ACTIVE',
        ])->save();

        return $user->refresh();
    }

    private function createSubject(string $name, string $level): array
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => $name,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'level_name' => $level,
            'sort_order' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$subjectId, $levelId];
    }

    private function attachSpecialization(int $profileId, int $subjectId, int $levelId): void
    {
        $tutorSubjectId = DB::table('tutor_subjects')->insertGetId([
            'tutor_profile_id' => $profileId,
            'subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tutor_subject_levels')->insert([
            'tutor_subject_id' => $tutorSubjectId,
            'subject_level_id' => $levelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validProfilePayload(int $subjectId, int $levelId, array $overrides = []): array
    {
        return array_replace([
            'headline' => 'Gia sư Toán',
            'bio' => 'Giới thiệu hiện tại.',
            'education_summary' => 'Cử nhân Sư phạm Toán.',
            'teaching_experience' => 'Ba năm giảng dạy.',
            'hourly_rate' => '180000',
            'subject_ids' => [$subjectId],
            'subject_levels' => [$subjectId => [$levelId]],
        ], $overrides);
    }

    private function createWard(
        string $provinceName = 'TP. Hồ Chí Minh',
        string $wardName = 'Phường Bến Thành'
    ): int
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

    private function createChange(
        int $profileId,
        string $type,
        array $payload,
        string $action = TutorProfileChangeRequest::ACTION_UPDATE
    ): int {
        return DB::table('tutor_profile_change_requests')->insertGetId([
            'tutor_profile_id' => $profileId,
            'change_type' => $type,
            'change_action' => $action,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => TutorProfileChangeRequest::STATUS_PENDING,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('email')->unique();
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->unsignedBigInteger('user_id')->unique();
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
            $table->string('subject_name');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });
        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name');
            $table->integer('sort_order')->nullable();
            $table->string('status', 20)->default('ACTIVE');
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
            $table->string('province_name');
            $table->timestamps();
        });
        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_name');
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
            $table->string('slot_name')->nullable();
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
        Schema::create('contracts', function (Blueprint $table): void {
            $table->bigIncrements('contract_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->timestamps();
        });
        Schema::create('tutoring_classes', function (Blueprint $table): void {
            $table->bigIncrements('class_id');
            $table->unsignedBigInteger('contract_id');
            $table->string('status', 30);
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
