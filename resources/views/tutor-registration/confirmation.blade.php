@php
    $isSubmitted = $tutorProfile->hasSubmittedTutorRegistration();
    $isApproved = $tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_APPROVED;
    $isRejected = $tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_REJECTED;
    $hasKnownStatus = in_array($tutorProfile->approval_status, \App\Models\TutorProfile::APPROVAL_STATUSES, true);
    $availabilityByDay = $tutorProfile->availabilities->groupBy('day_of_week');
@endphp

<x-app-layout title="Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
    <section
        class="tutor-registration-page"
        aria-labelledby="tutor-registration-title"
        style="--tutor-registration-background: url('{{ asset('images/nen.webp') }}')"
    >
        <div class="container tutor-registration-container">
            <header class="tutor-registration-heading">
                <h1 id="tutor-registration-title">Đăng ký trở thành <span>gia sư</span></h1>
                <p>Chia sẻ kiến thức – Truyền cảm hứng – Cùng nhau phát triển</p>
            </header>

            <nav class="tutor-registration-progress" aria-label="Tiến trình đăng ký gia sư">
                <ol>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Thông tin cơ bản</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Chuyên môn</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Hình thức &amp; khu vực</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Lịch rảnh</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Minh chứng</strong>
                    </li>
                    <li class="is-active" aria-current="step">
                        <span><x-directory-icon name="circle-check" /></span>
                        <strong>Xác nhận</strong>
                    </li>
                </ol>
            </nav>

            <section class="tutor-basic-card tutor-confirmation-card" aria-labelledby="tutor-confirmation-title">
                <header class="tutor-basic-card-heading tutor-specialization-heading">
                    <span class="tutor-specialization-heading-icon" aria-hidden="true">
                        <x-directory-icon name="circle-check" />
                    </span>
                    <span>
                        <h2 id="tutor-confirmation-title">Xác nhận</h2>
                        <p>Kiểm tra lại thông tin trước khi gửi hồ sơ.</p>
                    </span>
                </header>

                @if (session('success'))
                    <div class="tutor-basic-alert" role="status">
                        <x-directory-icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('info'))
                    <div class="tutor-basic-alert" role="status">
                        <x-directory-icon name="shield" />
                        <span>{{ session('info') }}</span>
                    </div>
                @endif

                @if ($errors->has('confirmation'))
                    <div class="tutor-basic-alert tutor-specialization-alert-error" role="alert">
                        <x-directory-icon name="flag" />
                        <span>{{ $errors->first('confirmation') }}</span>
                    </div>
                @endif

                <div class="tutor-confirmation-content">
                    @if ($completionErrors !== [])
                        <aside class="tutor-confirmation-completion-alert" role="alert">
                            <x-directory-icon name="flag" />
                            <div>
                                <strong>Hồ sơ chưa hoàn tất</strong>
                                <ul>
                                    @foreach ($completionErrors as $step => $message)
                                        <li>
                                            <span>{{ $message }}</span>
                                            @if ($canEdit)
                                                <a href="{{ match ($step) {
                                                    \App\Services\TutorRegistrationCompletionChecker::STEP_BASIC => route('tutor-registration.basic.edit'),
                                                    \App\Services\TutorRegistrationCompletionChecker::STEP_SPECIALIZATION => route('tutor-registration.specialization.edit'),
                                                    \App\Services\TutorRegistrationCompletionChecker::STEP_TEACHING_PREFERENCES => route('tutor-registration.teaching-preferences.edit'),
                                                    default => route('tutor-registration.documents.edit'),
                                                } }}">Hoàn thiện</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </aside>
                    @endif

                    @if ($isSubmitted)
                        <div class="tutor-confirmation-state" role="status">
                            <x-directory-icon name="clock" />
                            <div>
                                <strong>Hồ sơ đã được gửi xét duyệt.</strong>
                                @if ($tutorProfile->submitted_at)
                                    <span>Thời gian gửi: {{ $tutorProfile->submitted_at->format('H:i d/m/Y') }}</span>
                                @endif
                            </div>
                        </div>
                    @elseif ($isApproved)
                        <div class="tutor-confirmation-state is-approved" role="status">
                            <x-directory-icon name="circle-check" />
                            <div>
                                <strong>Hồ sơ đã được duyệt.</strong>
                                <span>Hồ sơ không thể chỉnh sửa trong trình đăng ký ở phiên bản hiện tại.</span>
                            </div>
                        </div>
                    @elseif ($isRejected)
                        <div class="tutor-confirmation-state is-rejected" role="status">
                            <x-directory-icon name="flag" />
                            <div>
                                <strong>Hồ sơ đã bị từ chối.</strong>
                                <span>Bạn có thể chỉnh sửa thông tin và gửi lại hồ sơ xét duyệt.</span>
                            </div>
                        </div>
                    @elseif (! $hasKnownStatus)
                        <div class="tutor-confirmation-state is-rejected" role="alert">
                            <x-directory-icon name="flag" />
                            <div>
                                <strong>Hồ sơ có trạng thái không hợp lệ.</strong>
                                <span>Hồ sơ không thể chỉnh sửa hoặc gửi xét duyệt.</span>
                            </div>
                        </div>
                    @endif

                    <div class="tutor-confirmation-sections">
                        <section class="tutor-confirmation-section" aria-labelledby="confirmation-basic-title">
                            <header>
                                <span class="tutor-confirmation-section-icon" aria-hidden="true">
                                    <x-directory-icon name="user-round" />
                                </span>
                                <h3 id="confirmation-basic-title">Thông tin cơ bản</h3>
                                @if ($canEdit)
                                    <a href="{{ route('tutor-registration.basic.edit') }}">Chỉnh sửa</a>
                                @endif
                            </header>
                            <dl class="tutor-confirmation-basic-grid">
                                <div>
                                    <dt>Tiêu đề hồ sơ</dt>
                                    <dd>{{ $tutorProfile->headline ?: 'Chưa cung cấp' }}</dd>
                                </div>
                                <div>
                                    <dt>Giới thiệu</dt>
                                    <dd>{{ $tutorProfile->bio ?: 'Chưa cung cấp' }}</dd>
                                </div>
                                <div>
                                    <dt>Trình độ học vấn</dt>
                                    <dd>{{ $tutorProfile->education_summary ?: 'Chưa cung cấp' }}</dd>
                                </div>
                                <div>
                                    <dt>Kinh nghiệm giảng dạy</dt>
                                    <dd>{{ $tutorProfile->teaching_experience ?: 'Chưa cung cấp' }}</dd>
                                </div>
                                <div>
                                    <dt>Mức học phí</dt>
                                    <dd>
                                        {{ $tutorProfile->hourly_rate !== null
                                            ? number_format((float) $tutorProfile->hourly_rate, 0, ',', '.').'đ/giờ'
                                            : 'Chưa cung cấp' }}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section class="tutor-confirmation-section" aria-labelledby="confirmation-specialization-title">
                            <header>
                                <span class="tutor-confirmation-section-icon" aria-hidden="true">
                                    <x-directory-icon name="book-open" />
                                </span>
                                <h3 id="confirmation-specialization-title">Chuyên môn</h3>
                                @if ($canEdit)
                                    <a href="{{ route('tutor-registration.specialization.edit') }}">Chỉnh sửa</a>
                                @endif
                            </header>
                            @forelse ($tutorProfile->tutorSubjects as $tutorSubject)
                                <div class="tutor-confirmation-subject">
                                    <strong>{{ $tutorSubject->subject?->subject_name ?? 'Môn học không khả dụng' }}</strong>
                                    <div class="tutor-confirmation-chips">
                                        @forelse ($tutorSubject->tutorSubjectLevels as $tutorSubjectLevel)
                                            <span>{{ $tutorSubjectLevel->subjectLevel?->level_name ?? 'Trình độ không khả dụng' }}</span>
                                        @empty
                                            <span>Chưa chọn trình độ</span>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <p class="tutor-confirmation-empty">Chưa thiết lập chuyên môn.</p>
                            @endforelse
                        </section>

                        <section class="tutor-confirmation-section" aria-labelledby="confirmation-preferences-title">
                            <header>
                                <span class="tutor-confirmation-section-icon" aria-hidden="true">
                                    <x-directory-icon name="map-pin" />
                                </span>
                                <h3 id="confirmation-preferences-title">Hình thức &amp; khu vực</h3>
                                @if ($canEdit)
                                    <a href="{{ route('tutor-registration.teaching-preferences.edit') }}">Chỉnh sửa</a>
                                @endif
                            </header>
                            <div class="tutor-confirmation-detail-row">
                                <strong>Hình thức dạy</strong>
                                <div class="tutor-confirmation-chips">
                                    @if ($tutorProfile->supports_online)
                                        <span>Trực tuyến</span>
                                    @endif
                                    @if ($tutorProfile->supports_offline)
                                        <span>Trực tiếp</span>
                                    @endif
                                    @if (! $tutorProfile->supports_online && ! $tutorProfile->supports_offline)
                                        <span>Chưa thiết lập</span>
                                    @endif
                                </div>
                            </div>
                            @if ($tutorProfile->supports_offline)
                                <div class="tutor-confirmation-detail-row">
                                    <strong>Khu vực dạy</strong>
                                    <div class="tutor-confirmation-chips">
                                        @forelse ($tutorProfile->teachingAreas as $teachingArea)
                                            <span>
                                                {{ $teachingArea->ward?->ward_name ?? 'Phường/xã không khả dụng' }}{{ $teachingArea->ward?->province ? ', '.$teachingArea->ward->province->province_name : '' }}
                                            </span>
                                        @empty
                                            <span>Chưa thiết lập khu vực</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </section>

                        <section class="tutor-confirmation-section" aria-labelledby="confirmation-availability-title">
                            <header>
                                <span class="tutor-confirmation-section-icon" aria-hidden="true">
                                    <x-directory-icon name="calendar-days" />
                                </span>
                                <h3 id="confirmation-availability-title">Lịch rảnh</h3>
                                @if ($canEdit)
                                    <a href="{{ route('tutor-registration.availability.edit') }}">Chỉnh sửa</a>
                                @endif
                            </header>
                            @if ($availabilityByDay->isEmpty())
                                <p class="tutor-confirmation-empty">Chưa thiết lập lịch rảnh.</p>
                            @else
                                <div class="tutor-confirmation-availability">
                                    @foreach ($availabilityByDay as $dayOfWeek => $availabilities)
                                        <div>
                                            <strong>{{ $dayLabels[(int) $dayOfWeek] ?? 'Ngày không xác định' }}</strong>
                                            <div class="tutor-confirmation-chips">
                                                @foreach ($availabilities as $availability)
                                                    @php
                                                        $timeSlot = $availability->timeSlot;
                                                        $timeRange = $timeSlot
                                                            ? substr((string) $timeSlot->start_time, 0, 5).' - '.substr((string) $timeSlot->end_time, 0, 5)
                                                            : 'Khung giờ không khả dụng';
                                                        $slotName = trim((string) $timeSlot?->slot_name);
                                                        $normalizedSlotName = str_replace(
                                                            [' ', '–', '—'],
                                                            ['', '-', '-'],
                                                            $slotName
                                                        );
                                                        $normalizedTimeRange = str_replace(' ', '', $timeRange);
                                                        $slotNameContainsTimeRange = $timeSlot
                                                            && $slotName !== ''
                                                            && str_contains($normalizedSlotName, $normalizedTimeRange);
                                                        $timeLabel = match (true) {
                                                            $timeSlot === null => $timeRange,
                                                            $slotName === '' => $timeRange,
                                                            $slotNameContainsTimeRange => $slotName,
                                                            default => $slotName.' · '.$timeRange,
                                                        };
                                                    @endphp
                                                    <span>{{ $timeLabel }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        <section class="tutor-confirmation-section" aria-labelledby="confirmation-documents-title">
                            <header>
                                <span class="tutor-confirmation-section-icon" aria-hidden="true">
                                    <x-directory-icon name="file-check" />
                                </span>
                                <h3 id="confirmation-documents-title">Minh chứng</h3>
                                @if ($canEdit)
                                    <a href="{{ route('tutor-registration.documents.edit') }}">Chỉnh sửa</a>
                                @endif
                            </header>
                            @forelse ($tutorProfile->documents as $document)
                                @php
                                    $documentId = (int) $document->document_id;
                                    $isDownloadable = $downloadableDocumentIds[$documentId] ?? false;
                                @endphp
                                <div class="tutor-confirmation-document">
                                    <x-directory-icon name="document" />
                                    <div>
                                        <strong>{{ $document->document_name ?: 'Tài liệu minh chứng' }}</strong>
                                        <span>
                                            {{ $document->typeLabel() }} · {{ $document->verificationStatusLabel() }} ·
                                            {{ $document->uploaded_at?->format('d/m/Y') ?? 'Chưa xác định' }}
                                        </span>
                                    </div>
                                    @if ($isDownloadable)
                                        <a href="{{ route('tutor-registration.documents.download', $documentId) }}">Tải xuống</a>
                                    @else
                                        <span class="tutor-documents-unavailable">Tệp không khả dụng</span>
                                    @endif
                                </div>
                            @empty
                                <p class="tutor-confirmation-empty">Chưa có tài liệu minh chứng.</p>
                            @endforelse
                        </section>
                    </div>

                    @if ($tutorProfile->canSubmitTutorRegistration())
                        <form method="POST" action="{{ route('tutor-registration.confirmation.submit') }}" class="tutor-confirmation-submit-form">
                            @csrf
                            <label class="tutor-confirmation-checkbox">
                                <input
                                    type="checkbox"
                                    name="confirmation"
                                    value="1"
                                    @checked(old('confirmation'))
                                    @disabled($completionErrors !== [])
                                >
                                <span aria-hidden="true"><x-directory-icon name="check" /></span>
                                <strong>Tôi xác nhận các thông tin trên là chính xác.</strong>
                            </label>
                            @error('confirmation')
                                <p class="tutor-basic-error" role="alert">{{ $message }}</p>
                            @enderror

                            <footer class="tutor-basic-actions tutor-confirmation-actions">
                                <a class="tutor-basic-back" href="{{ route('tutor-registration.documents.edit') }}">
                                    <x-directory-icon name="arrow-left" />
                                    Quay lại
                                </a>
                                <button class="button tutor-basic-submit" type="submit" @disabled(! $canSubmit)>
                                    <x-directory-icon name="send" />
                                    Gửi hồ sơ xét duyệt
                                </button>
                            </footer>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </section>
</x-app-layout>
