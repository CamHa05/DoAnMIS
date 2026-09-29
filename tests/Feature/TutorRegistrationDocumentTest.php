<?php

namespace Tests\Feature;

use App\Models\TutorDocument;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TutorRegistrationDocumentTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->createSchema();
    }

    public function test_guest_cannot_access_step_five_actions(): void
    {
        $this->get(route('tutor-registration.documents.edit'))
            ->assertRedirect(route('login'));
        $this->post(route('tutor-registration.documents.store'))
            ->assertRedirect(route('login'));
        $this->post(route('tutor-registration.documents.continue'))
            ->assertRedirect(route('login'));
        $this->get(route('tutor-registration.documents.download', 1))
            ->assertRedirect(route('login'));
        $this->delete(route('tutor-registration.documents.destroy', 1))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_tutor_profile_is_redirected_to_step_one(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('tutor-registration.documents.edit'))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Chứng chỉ IELTS 6.5',
                'document' => UploadedFile::fake()->create('certificate.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('tutor-registration.documents.continue'))
            ->assertRedirect(route('tutor-registration.basic.edit'))
            ->assertSessionHas('error');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, TutorDocument::query()->count());
    }

    public function test_step_five_lists_only_current_user_documents_and_uses_the_expected_stepper(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        $downloadable = $this->createDocument(
            $profile,
            TutorDocument::STATUS_PENDING,
            TutorDocument::TYPE_DEGREE,
            null,
            'Bằng tốt nghiệp Đại học',
            true
        );
        $legacy = $this->createDocument(
            $profile,
            TutorDocument::STATUS_APPROVED,
            TutorDocument::TYPE_TRANSCRIPT,
            '/uploads/documents/legacy.pdf',
            'Bảng điểm học kỳ 1 năm 2025'
        );
        $otherDocument = $this->createDocument(
            $otherProfile,
            TutorDocument::STATUS_REJECTED,
            TutorDocument::TYPE_CERTIFICATE,
            null,
            'Chứng chỉ của người khác',
            true
        );

        $response = $this->actingAs($user)
            ->get(route('tutor-registration.documents.edit'))
            ->assertOk()
            ->assertSee('class="tutor-registration-shell"', false)
            ->assertSee('data-notification-center', false)
            ->assertDontSee('tutor-account-sidebar', false)
            ->assertDontSee('class="tutor-account-layout container"', false)
            ->assertSeeText('Minh chứng')
            ->assertSeeText('Bằng tốt nghiệp Đại học')
            ->assertSeeText('Bằng cấp')
            ->assertSeeText('Chờ xác minh')
            ->assertSeeText('Bảng điểm học kỳ 1 năm 2025')
            ->assertSeeText('Đã xác minh')
            ->assertSeeText('Tệp không khả dụng')
            ->assertDontSeeText('Chứng chỉ của người khác')
            ->assertDontSeeText('Bước 5/6')
            ->assertDontSee('/uploads/documents/legacy.pdf', false)
            ->assertSee(route('tutor-registration.documents.download', $downloadable->document_id), false)
            ->assertDontSee(route('tutor-registration.documents.download', $legacy->document_id), false)
            ->assertDontSee(route('tutor-registration.documents.download', $otherDocument->document_id), false)
            ->assertSeeInOrder([
                'Thông tin cơ bản',
                'Chuyên môn',
                'Hình thức &amp; khu vực',
                'Lịch rảnh',
                'Minh chứng',
                'Xác nhận',
            ], false);

        $this->assertSame(4, substr_count($response->getContent(), 'class="is-complete"'));
        $this->assertMatchesRegularExpression(
            '/<li class="is-active" aria-current="step">.*?Minh chứng/s',
            $response->getContent()
        );
        $response
            ->assertSeeInOrder([
                'Loại tài liệu',
                'Tên tài liệu / Mô tả ngắn',
                'Tệp minh chứng',
                'Tải lên',
            ])
            ->assertSee('name="document_name"', false)
            ->assertSee('placeholder="VD: Chứng chỉ IELTS 6.5, Bảng điểm HK1 năm 2025..."', false);
    }

    public function test_document_name_is_required_trimmed_limited_and_preserved_as_old_input(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $url = route('tutor-registration.documents.edit');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document_name');

        foreach ([TutorDocument::TYPE_CERTIFICATE, TutorDocument::TYPE_OTHER] as $type) {
            $this->actingAs($user)
                ->from($url)
                ->post(route('tutor-registration.documents.store'), [
                    'document_type' => $type,
                    'document_name' => '   ',
                    'document' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                ])
                ->assertRedirect($url)
                ->assertSessionHasErrors('document_name');
        }

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => str_repeat('a', 256),
                'document' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document_name');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Chứng chỉ IELTS 6.5',
                'document' => UploadedFile::fake()->create('proof.exe', 100, 'application/pdf'),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document')
            ->assertSessionHasInput('document_type', TutorDocument::TYPE_CERTIFICATE)
            ->assertSessionHasInput('document_name', 'Chứng chỉ IELTS 6.5');

        $this->assertSame(0, TutorDocument::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_user_can_upload_pdf_to_private_storage_without_changing_profile_workflow(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);

        $this->actingAs($user)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_DEGREE,
                'document_name' => '  Bằng tốt nghiệp Đại học  ',
                'document' => UploadedFile::fake()->create('ban-scan-goc.pdf', 200, 'application/pdf'),
                'verification_status' => TutorDocument::STATUS_APPROVED,
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionHas('success', 'Đã tải lên minh chứng.');

        $document = TutorDocument::query()->sole();

        $this->assertSame($profile->tutor_profile_id, $document->tutor_profile_id);
        $this->assertSame(TutorDocument::TYPE_DEGREE, $document->document_type);
        $this->assertSame(TutorDocument::STATUS_PENDING, $document->verification_status);
        $this->assertSame('Bằng tốt nghiệp Đại học', $document->document_name);
        $this->assertMatchesRegularExpression(
            '#^tutor-documents/'.$profile->tutor_profile_id.'/[A-Za-z0-9]{40}\.pdf$#',
            $document->file_url
        );
        $this->assertStringNotContainsString('Bằng tốt nghiệp', $document->file_url);
        $this->assertStringNotContainsString('ban-scan-goc', $document->file_url);
        Storage::disk('local')->assertExists($document->file_url);

        $profile->refresh();
        $this->assertSame('PENDING', $profile->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertSame(0, DB::table('tutor_profile_reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_jpg_jpeg_and_png_uploads_are_supported(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $files = [
            ['certificate.jpg', 'image/jpeg'],
            ['student-card.jpeg', 'image/jpeg'],
            ['transcript.png', 'image/png'],
        ];

        foreach ($files as [$name, $mimeType]) {
            $this->actingAs($user)
                ->post(route('tutor-registration.documents.store'), [
                    'document_type' => TutorDocument::TYPE_CERTIFICATE,
                    'document_name' => 'Chứng chỉ chuyên môn',
                    'document' => UploadedFile::fake()->create($name, 100, $mimeType),
                ])
                ->assertRedirect(route('tutor-registration.documents.edit'))
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(3, $profile->documents()->count());
        $profile->documents->each(
            fn (TutorDocument $document) => Storage::disk('local')->assertExists($document->file_url)
        );
    }

    public function test_invalid_document_type_is_rejected(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);

        $this->actingAs($user)
            ->from(route('tutor-registration.documents.edit'))
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => 'ID_CARD',
                'document_name' => 'Giấy tờ không hợp lệ',
                'document' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionHasErrors('document_type');

        $this->assertSame(0, TutorDocument::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_browser_cannot_override_document_ownership_status_or_profile_workflow(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);

        $this->actingAs($user)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_OTHER,
                'document_name' => 'Giấy xác nhận trợ giảng môn Toán',
                'document' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                'tutor_profile_id' => $otherProfile->tutor_profile_id,
                'user_id' => $otherUser->user_id,
                'profile_id' => $otherProfile->tutor_profile_id,
                'file_url' => '/uploads/documents/injected.pdf',
                'verification_status' => TutorDocument::STATUS_APPROVED,
                'approval_status' => 'REJECTED',
                'approved_at' => now()->addYear()->toDateTimeString(),
                'submitted_at' => now()->addYear()->toDateTimeString(),
                'uploaded_at' => now()->addYear()->toDateTimeString(),
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $document = TutorDocument::query()->sole();

        $this->assertSame($profile->tutor_profile_id, $document->tutor_profile_id);
        $this->assertSame(TutorDocument::STATUS_PENDING, $document->verification_status);
        $this->assertSame('Giấy xác nhận trợ giảng môn Toán', $document->document_name);
        $this->assertTrue($document->uploaded_at->lessThan(now()->addMinute()));
        $this->assertStringStartsWith(
            TutorDocument::STORAGE_PREFIX.'/'.$profile->tutor_profile_id.'/',
            $document->file_url
        );
        $this->assertSame(0, $otherProfile->documents()->count());
        $this->assertSame('PENDING', $profile->refresh()->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertNull($profile->submitted_at);
    }

    public function test_invalid_mime_extension_and_oversized_files_are_rejected(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $url = route('tutor-registration.documents.edit');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Tài liệu HTML giả mạo',
                'document' => UploadedFile::fake()->create('malicious.pdf', 10, 'text/html'),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Tài liệu sai phần mở rộng',
                'document' => UploadedFile::fake()->create('proof.exe', 10, 'application/pdf'),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Tài liệu quá lớn',
                'document' => UploadedFile::fake()->create(
                    'large.pdf',
                    TutorDocument::MAX_FILE_SIZE_KB + 1,
                    'application/pdf'
                ),
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('document');

        $this->assertSame(0, TutorDocument::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_eleventh_document_is_rejected_without_leaving_a_file(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);

        foreach (range(1, TutorDocument::MAX_DOCUMENTS_PER_PROFILE) as $index) {
            $this->createDocument(
                $profile,
                TutorDocument::STATUS_PENDING,
                TutorDocument::TYPE_CERTIFICATE,
                "/uploads/documents/existing-$index.pdf",
                "existing-$index.pdf"
            );
        }

        $this->actingAs($user)
            ->from(route('tutor-registration.documents.edit'))
            ->post(route('tutor-registration.documents.store'), [
                'document_type' => TutorDocument::TYPE_CERTIFICATE,
                'document_name' => 'Tài liệu thứ mười một',
                'document' => UploadedFile::fake()->create('eleventh.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('tutor-registration.documents.edit'))
            ->assertSessionHasErrors('document');

        $this->assertSame(TutorDocument::MAX_DOCUMENTS_PER_PROFILE, $profile->documents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_database_failure_cleans_up_the_uploaded_file(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        TutorDocument::creating(function (): void {
            throw new RuntimeException('Forced document insert failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->post(route('tutor-registration.documents.store'), [
                    'document_type' => TutorDocument::TYPE_CERTIFICATE,
                    'document_name' => 'Chứng chỉ gây lỗi database',
                    'document' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                ]);

            $this->fail('The forced database failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced document insert failure.', $exception->getMessage());
        } finally {
            TutorDocument::flushEventListeners();
        }

        $this->assertSame(0, TutorDocument::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_continue_requires_at_least_one_document_and_then_opens_step_six(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $url = route('tutor-registration.documents.edit');

        $this->actingAs($user)
            ->from($url)
            ->post(route('tutor-registration.documents.continue'))
            ->assertRedirect($url)
            ->assertSessionHasErrors('documents');

        $this->createDocument($profile);

        $this->actingAs($user)
            ->post(route('tutor-registration.documents.continue'))
            ->assertRedirect(route('tutor-registration.confirmation.edit'))
            ->assertSessionHas('success', 'Đã lưu minh chứng.');

        $this->assertSame('PENDING', $profile->refresh()->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertSame(0, DB::table('tutor_profile_reviews')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_owner_can_download_a_private_document(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $document = $this->createDocument(
            $profile,
            TutorDocument::STATUS_APPROVED,
            TutorDocument::TYPE_DEGREE,
            null,
            'proof.pdf',
            true
        );

        $this->actingAs($user)
            ->get(route('tutor-registration.documents.download', $document->document_id))
            ->assertOk()
            ->assertDownload('proof.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_download_appends_the_trusted_file_extension_to_a_description_without_one(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $document = $this->createDocument(
            $profile,
            TutorDocument::STATUS_PENDING,
            TutorDocument::TYPE_CERTIFICATE,
            TutorDocument::STORAGE_PREFIX.'/'.$profile->tutor_profile_id.'/'.Str::random(40).'.jpg',
            'Chứng chỉ IELTS 6.5',
            true
        );

        $this->actingAs($user)
            ->get(route('tutor-registration.documents.download', $document->document_id))
            ->assertOk()
            ->assertDownload('Chung chi IELTS 6.5.jpg');
    }

    public function test_download_prevents_idor_and_rejects_legacy_or_missing_paths(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        $otherDocument = $this->createDocument($otherProfile, storeFile: true);
        $legacyDocument = $this->createDocument(
            $profile,
            TutorDocument::STATUS_PENDING,
            TutorDocument::TYPE_CERTIFICATE,
            '/uploads/documents/legacy.pdf',
            'legacy.pdf'
        );
        Storage::disk('local')->put('uploads/documents/legacy.pdf', 'legacy');
        $missingDocument = $this->createDocument($profile);

        $this->actingAs($user)
            ->get(route('tutor-registration.documents.download', $otherDocument->document_id))
            ->assertNotFound();
        $this->actingAs($user)
            ->get(route('tutor-registration.documents.download', $legacyDocument->document_id))
            ->assertNotFound();
        $this->actingAs($user)
            ->get(route('tutor-registration.documents.download', $missingDocument->document_id))
            ->assertNotFound();

        Storage::disk('local')->assertExists('uploads/documents/legacy.pdf');
    }

    public function test_tutor_can_delete_pending_and_rejected_documents_with_their_private_files(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);

        foreach ([TutorDocument::STATUS_PENDING, TutorDocument::STATUS_REJECTED] as $status) {
            $document = $this->createDocument($profile, $status, storeFile: true);
            $path = $document->file_url;

            $this->actingAs($user)
                ->delete(route('tutor-registration.documents.destroy', $document->document_id))
                ->assertRedirect(route('tutor-registration.documents.edit'))
                ->assertSessionHas('success', 'Đã xóa tài liệu.');

            $this->assertDatabaseMissing('tutor_documents', [
                'document_id' => $document->document_id,
            ]);
            Storage::disk('local')->assertMissing($path);
        }

        $this->assertSame([], Storage::disk('local')->allFiles(TutorDocument::STORAGE_PREFIX.'/.trash'));
    }

    public function test_approved_and_unknown_status_documents_cannot_be_deleted(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $documents = [
            $this->createDocument($profile, TutorDocument::STATUS_APPROVED, storeFile: true),
            $this->createDocument($profile, 'MANUAL_REVIEW', storeFile: true),
        ];

        foreach ($documents as $document) {
            $this->actingAs($user)
                ->from(route('tutor-registration.documents.edit'))
                ->delete(route('tutor-registration.documents.destroy', $document->document_id))
                ->assertRedirect(route('tutor-registration.documents.edit'))
                ->assertSessionHasErrors('documents');

            $this->assertDatabaseHas('tutor_documents', [
                'document_id' => $document->document_id,
                'verification_status' => $document->verification_status,
            ]);
            Storage::disk('local')->assertExists($document->file_url);
        }
    }

    public function test_tutor_cannot_delete_another_profiles_document(): void
    {
        $user = $this->createUser();
        $this->createTutorProfile($user);
        $otherUser = $this->createUser();
        $otherProfile = $this->createTutorProfile($otherUser);
        $document = $this->createDocument($otherProfile, storeFile: true);

        $this->actingAs($user)
            ->delete(route('tutor-registration.documents.destroy', $document->document_id))
            ->assertNotFound();

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $document->document_id,
        ]);
        Storage::disk('local')->assertExists($document->file_url);
    }

    public function test_deleting_an_eligible_legacy_row_never_touches_its_legacy_path(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $document = $this->createDocument(
            $profile,
            TutorDocument::STATUS_REJECTED,
            TutorDocument::TYPE_TRANSCRIPT,
            '/uploads/documents/legacy.pdf',
            'legacy.pdf'
        );
        Storage::disk('local')->put('uploads/documents/legacy.pdf', 'must remain');

        $this->actingAs($user)
            ->delete(route('tutor-registration.documents.destroy', $document->document_id))
            ->assertRedirect(route('tutor-registration.documents.edit'));

        $this->assertDatabaseMissing('tutor_documents', [
            'document_id' => $document->document_id,
        ]);
        Storage::disk('local')->assertExists('uploads/documents/legacy.pdf');
    }

    public function test_delete_database_failure_restores_the_private_file(): void
    {
        $user = $this->createUser();
        $profile = $this->createTutorProfile($user);
        $document = $this->createDocument($profile, storeFile: true);
        $path = $document->file_url;
        TutorDocument::deleting(function (): void {
            throw new RuntimeException('Forced document delete failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->delete(route('tutor-registration.documents.destroy', $document->document_id));

            $this->fail('The forced database failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced document delete failure.', $exception->getMessage());
        } finally {
            TutorDocument::flushEventListeners();
        }

        $this->assertDatabaseHas('tutor_documents', [
            'document_id' => $document->document_id,
        ]);
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('local')->allFiles(TutorDocument::STORAGE_PREFIX.'/.trash'));
    }

    private function createUser(): User
    {
        $this->userSequence++;

        return User::query()->create([
            'google_id' => "google-document-{$this->userSequence}",
            'email' => "document-{$this->userSequence}@example.com",
            'full_name' => "Gia sư {$this->userSequence}",
            'avatar_url' => null,
            'phone' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTutorProfile(User $user, array $attributes = []): TutorProfile
    {
        $profile = TutorProfile::query()->create([
            'user_id' => $user->user_id,
            'headline' => 'Gia sư tận tâm',
            'bio' => 'Giới thiệu Step 1',
            'education_summary' => 'Học vấn Step 1',
            'teaching_experience' => 'Kinh nghiệm Step 1',
            'hourly_rate' => 200000,
            'supports_online' => true,
            'supports_offline' => false,
        ]);
        $profile->approval_status = $attributes['approval_status'] ?? 'PENDING';
        $profile->approved_at = $attributes['approved_at'] ?? null;
        $profile->save();

        return $profile;
    }

    private function createDocument(
        TutorProfile $profile,
        string $status = TutorDocument::STATUS_PENDING,
        string $type = TutorDocument::TYPE_CERTIFICATE,
        ?string $path = null,
        string $name = 'proof.pdf',
        bool $storeFile = false
    ): TutorDocument {
        $path ??= TutorDocument::STORAGE_PREFIX.'/'.$profile->tutor_profile_id.'/'.Str::random(40).'.pdf';
        $document = new TutorDocument([
            'document_type' => $type,
            'document_name' => $name,
            'file_url' => $path,
        ]);
        $document->verification_status = $status;
        $document->uploaded_at = now();
        $profile->documents()->save($document);

        if ($storeFile) {
            Storage::disk('local')->put($path, 'private document content');
        }

        return $document;
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
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('tutor_documents', function (Blueprint $table): void {
            $table->bigIncrements('document_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('document_type', 50);
            $table->string('document_name')->nullable();
            $table->string('file_url', 500);
            $table->string('verification_status', 30)->default(TutorDocument::STATUS_PENDING);
            $table->dateTime('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tutor_profile_id')->references('tutor_profile_id')->on('tutor_profiles')->cascadeOnDelete();
        });

        Schema::create('tutor_profile_reviews', function (Blueprint $table): void {
            $table->bigIncrements('review_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('review_source', 20);
            $table->string('review_result', 30);
            $table->text('notes')->nullable();
            $table->dateTime('reviewed_at')->useCurrent();
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

        Schema::enableForeignKeyConstraints();
    }
}
