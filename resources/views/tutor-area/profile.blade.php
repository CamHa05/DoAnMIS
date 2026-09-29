<x-tutor-account-layout title="Hồ sơ gia sư | GiaSu">
    @php
        $tutorName = trim((string) ($tutorProfile->user?->full_name ?: 'Gia sư'));
        $statusIcon = match ($profileStatus['tone']) {
            'approved' => 'circle-check',
            'pending' => 'clock',
            'rejected' => 'x-circle',
            default => 'info',
        };
        $educationChange = $activeChanges->get(\App\Models\TutorProfileChangeRequest::TYPE_EDUCATION);
        $experienceChange = $activeChanges->get(\App\Models\TutorProfileChangeRequest::TYPE_EXPERIENCE);
    @endphp

    <section class="tutor-owner-page" aria-labelledby="tutor-owner-title">
        <header class="tutor-owner-page-header">
            <div>
                <h1 id="tutor-owner-title">Hồ sơ gia sư</h1>
                <p>Quản lý thông tin hồ sơ được hiển thị với người học trên GiaSu.</p>
            </div>

            <div class="tutor-owner-page-actions" aria-label="Thao tác hồ sơ">
                @if ($canViewPublic)
                    <a class="tutor-owner-button tutor-owner-button--secondary" href="{{ route('tutors.show', $tutorProfile) }}">
                        <x-directory-icon name="eye" />
                        Xem hồ sơ công khai
                    </a>
                @else
                    <span
                        class="tutor-owner-button tutor-owner-button--secondary is-disabled"
                        aria-disabled="true"
                        title="Hồ sơ chỉ công khai sau khi được duyệt và tài khoản đang hoạt động."
                    >
                        <x-directory-icon name="eye" />
                        Chưa thể xem công khai
                    </span>
                @endif

                @if ($canManageApproved)
                    <a class="tutor-owner-button tutor-owner-button--primary" href="{{ route('tutor-area.profile.edit') }}">
                        <x-directory-icon name="edit" />
                        Chỉnh sửa hồ sơ
                    </a>
                @elseif ($canEdit)
                    <a class="tutor-owner-button tutor-owner-button--primary" href="{{ route('tutor-registration.basic.edit') }}">
                        <x-directory-icon name="edit" />
                        Chỉnh sửa hồ sơ
                    </a>
                @else
                    <span
                        class="tutor-owner-button tutor-owner-button--primary is-disabled"
                        aria-disabled="true"
                        title="Hồ sơ đang được xét duyệt hoặc đã được duyệt nên tạm thời không thể chỉnh sửa."
                    >
                        <x-directory-icon name="lock" />
                        Hồ sơ đang khóa
                    </span>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="tutor-owner-alert tutor-owner-alert--success" role="status">
                <x-directory-icon name="circle-check" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="tutor-owner-alert tutor-owner-alert--error" role="alert">
                <x-directory-icon name="x-circle" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if ($pendingChangeCount > 0)
            <div class="tutor-owner-alert tutor-owner-alert--pending" role="status">
                <x-directory-icon name="clock" />
                <span>
                    Bạn có {{ $pendingChangeCount }} thay đổi đang chờ Admin xét duyệt.
                    Dữ liệu đã duyệt hiện tại vẫn tiếp tục hiển thị công khai.
                </span>
                <a href="{{ route('tutor-area.profile.edit') }}">Xem thay đổi</a>
            </div>
        @endif

        <div class="tutor-owner-grid">
            <article class="tutor-owner-panel tutor-owner-hero">
                <div class="tutor-owner-identity">
                    <x-tutor-avatar :user="$tutorProfile->user" size="large" />

                    <div class="tutor-owner-identity-copy">
                        <h2>{{ $tutorName }}</h2>
                        <p class="tutor-owner-headline">
                            {{ filled($tutorProfile->headline) ? $tutorProfile->headline : 'Chưa cập nhật tiêu đề hồ sơ.' }}
                        </p>

                        <div class="tutor-owner-chip-list" aria-label="Thông tin nhanh">
                            @foreach ($teachingModes as $mode)
                                <span><x-directory-icon name="mode" /> {{ $mode }}</span>
                            @endforeach

                            @if ($hourlyRateLabel)
                                <span><x-directory-icon name="wallet" /> {{ $hourlyRateLabel }}</span>
                            @endif

                            @if ($subjectGroups->isNotEmpty())
                                <span><x-directory-icon name="book-open" /> {{ $subjectGroups->pluck('name')->join(', ') }}</span>
                            @endif

                            @if ($areaSummary)
                                <span><x-directory-icon name="map-pin" /> {{ $areaSummary }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="tutor-owner-profile-state">
                    <span class="tutor-owner-overline">Trạng thái hồ sơ</span>
                    <span class="tutor-owner-status tutor-owner-status--{{ $profileStatus['tone'] }}">
                        <x-directory-icon name="{{ $statusIcon }}" />
                        {{ $profileStatus['label'] }}
                    </span>
                    <p>{{ $profileStatus['message'] }}</p>

                    @if ($profileStatus['tone'] === 'rejected' && filled($tutorProfile->rejection_reason))
                        <div class="tutor-owner-rejection-note">
                            <strong>Lý do từ chối</strong>
                            <p>{{ $tutorProfile->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </article>

            <aside class="tutor-owner-panel tutor-owner-summary" aria-labelledby="tutor-owner-summary-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="document" /></span>
                    <div>
                        <h2 id="tutor-owner-summary-title">Tóm tắt hồ sơ</h2>
                        <p>Các thông tin chính đang được lưu.</p>
                    </div>
                </div>

                <dl class="tutor-owner-summary-list">
                    <div>
                        <dt>Trạng thái</dt>
                        <dd>{{ $profileStatus['label'] }}</dd>
                    </div>
                    <div>
                        <dt>Chuyên môn</dt>
                        <dd>{{ $specializationLabel ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Hình thức dạy</dt>
                        <dd>{{ $teachingModeLabel ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Học phí mong muốn</dt>
                        <dd>{{ $hourlyRateLabel ?: 'Chưa cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Khu vực</dt>
                        <dd>
                            @if ($tutorProfile->supports_offline)
                                {{ $areaSummary ?: 'Chưa cập nhật' }}
                            @else
                                Không áp dụng khi chỉ dạy trực tuyến
                            @endif
                        </dd>
                    </div>
                </dl>
            </aside>

            <article class="tutor-owner-panel tutor-owner-intro" aria-labelledby="tutor-owner-intro-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="document" /></span>
                    <div>
                        <h2 id="tutor-owner-intro-title">Giới thiệu</h2>
                        <p>Thông tin được hiển thị trên hồ sơ công khai.</p>
                    </div>
                </div>

                <p class="tutor-owner-copy">
                    {{ filled($tutorProfile->bio) ? $tutorProfile->bio : 'Bạn chưa cập nhật phần giới thiệu bản thân.' }}
                </p>
            </article>

            <aside class="tutor-owner-panel tutor-owner-public-card" aria-labelledby="tutor-owner-public-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="eye" /></span>
                    <div>
                        <h2 id="tutor-owner-public-title">Hồ sơ công khai</h2>
                        <p>Đây là nội dung người học nhìn thấy khi xem hồ sơ của bạn.</p>
                    </div>
                </div>

                @if ($canViewPublic)
                    <a class="tutor-owner-button tutor-owner-button--primary tutor-owner-button--wide" href="{{ route('tutors.show', $tutorProfile) }}">
                        Xem hồ sơ công khai
                        <x-directory-icon name="arrow" />
                    </a>
                @else
                    <p class="tutor-owner-public-note">Liên kết sẽ khả dụng khi hồ sơ được duyệt và tài khoản đang hoạt động.</p>
                @endif
            </aside>

            <article class="tutor-owner-panel tutor-owner-education" aria-labelledby="tutor-owner-education-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="education" /></span>
                    <div>
                        <h2 id="tutor-owner-education-title">Học vấn &amp; kinh nghiệm</h2>
                        <p>Nền tảng chuyên môn và trải nghiệm giảng dạy.</p>
                    </div>
                </div>

                <div class="tutor-owner-narrative-grid">
                    <section aria-labelledby="tutor-owner-study-title">
                        <h3 id="tutor-owner-study-title"><x-directory-icon name="book-open" /> Học vấn</h3>
                        <p>{{ filled($tutorProfile->education_summary) ? $tutorProfile->education_summary : 'Chưa cập nhật thông tin học vấn.' }}</p>
                        @if ($educationChange)
                            <div class="tutor-owner-change-note tutor-owner-change-note--{{ $educationChange->statusTone() }}">
                                <strong>{{ $educationChange->statusLabel() }} · Cập nhật đề xuất</strong>
                                <p>{{ data_get($educationChange->payload, 'value') }}</p>
                                @if ($educationChange->rejection_reason)
                                    <small>Lý do: {{ $educationChange->rejection_reason }}</small>
                                @endif
                            </div>
                        @endif
                    </section>
                    <section aria-labelledby="tutor-owner-experience-title">
                        <h3 id="tutor-owner-experience-title"><x-directory-icon name="briefcase" /> Kinh nghiệm</h3>
                        <p>{{ filled($tutorProfile->teaching_experience) ? $tutorProfile->teaching_experience : 'Chưa cập nhật kinh nghiệm giảng dạy.' }}</p>
                        @if ($experienceChange)
                            <div class="tutor-owner-change-note tutor-owner-change-note--{{ $experienceChange->statusTone() }}">
                                <strong>{{ $experienceChange->statusLabel() }} · Cập nhật đề xuất</strong>
                                <p>{{ data_get($experienceChange->payload, 'value') }}</p>
                                @if ($experienceChange->rejection_reason)
                                    <small>Lý do: {{ $experienceChange->rejection_reason }}</small>
                                @endif
                            </div>
                        @endif
                    </section>
                </div>
            </article>

            <article class="tutor-owner-panel tutor-owner-specialties" aria-labelledby="tutor-owner-specialties-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="book-open" /></span>
                    <div>
                        <h2 id="tutor-owner-specialties-title">Chuyên môn</h2>
                        <p>Môn học và cấp độ bạn có thể giảng dạy.</p>
                    </div>
                </div>

                <div class="tutor-owner-subject-list">
                    @forelse ($subjectGroups as $subject)
                        <section>
                            <h3>{{ $subject['name'] }}</h3>
                            @if ($subject['levels']->isNotEmpty())
                                <div class="tutor-owner-chip-list tutor-owner-chip-list--compact">
                                    @foreach ($subject['levels'] as $level)
                                        <span>{{ $level }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p>Chưa cập nhật cấp độ giảng dạy.</p>
                            @endif
                        </section>
                    @empty
                        <div class="tutor-owner-empty-state">
                            <x-directory-icon name="book-open" />
                            <p>Chưa có môn học và cấp độ giảng dạy.</p>
                        </div>
                    @endforelse
                </div>

            </article>

            <article class="tutor-owner-panel tutor-owner-teaching" aria-labelledby="tutor-owner-teaching-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="map-pin" /></span>
                    <div>
                        <h2 id="tutor-owner-teaching-title">Hình thức &amp; khu vực giảng dạy</h2>
                        <p>Hình thức và địa bàn bạn có thể nhận lớp.</p>
                    </div>
                </div>

                <dl class="tutor-owner-teaching-list">
                    <div>
                        <dt><x-directory-icon name="mode" /> Hình thức dạy</dt>
                        <dd>
                            @if ($teachingModes->isNotEmpty())
                                <div class="tutor-owner-chip-list tutor-owner-chip-list--compact">
                                    @foreach ($teachingModes as $mode)
                                        <span>{{ $mode }}</span>
                                    @endforeach
                                </div>
                            @else
                                Chưa cập nhật
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt><x-directory-icon name="location" /> Khu vực</dt>
                        <dd>
                            @if (! $tutorProfile->supports_offline)
                                Không áp dụng khi chỉ dạy trực tuyến
                            @elseif ($areaLabels->isNotEmpty())
                                <div class="tutor-owner-area-list">
                                    @foreach ($areaLabels as $area)
                                        <span>{{ $area }}</span>
                                    @endforeach
                                </div>
                            @else
                                Chưa cập nhật khu vực nhận dạy
                            @endif
                        </dd>
                    </div>
                </dl>
            </article>

            <article class="tutor-owner-panel tutor-owner-availability" aria-labelledby="tutor-owner-availability-title">
                <div class="tutor-owner-section-heading">
                    <span class="tutor-owner-section-icon"><x-directory-icon name="calendar-days" /></span>
                    <div>
                        <h2 id="tutor-owner-availability-title">Lịch rảnh</h2>
                        <p>Thời gian bạn có thể nhận lớp mới.</p>
                    </div>
                </div>

                @if ($availabilityGroups->isNotEmpty())
                    <div class="tutor-owner-availability-list">
                        @foreach ($availabilityGroups as $group)
                            <section>
                                <h3>{{ $group['label'] }}</h3>
                                <div>
                                    @foreach ($group['slots'] as $slot)
                                        <span>{{ $slot }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <div class="tutor-owner-empty-state">
                        <x-directory-icon name="calendar-days" />
                        <p>Chưa có lịch rảnh để hiển thị.</p>
                    </div>
                @endif
            </article>

            <article class="tutor-owner-panel tutor-owner-documents" aria-labelledby="tutor-owner-documents-title">
                <div class="tutor-owner-section-heading tutor-owner-section-heading--split">
                    <div class="tutor-owner-section-heading-main">
                        <span class="tutor-owner-section-icon"><x-directory-icon name="file-check" /></span>
                        <div>
                            <h2 id="tutor-owner-documents-title">Minh chứng</h2>
                            <p>Bằng cấp, chứng chỉ và tài liệu bạn đã gửi để xét duyệt.</p>
                        </div>
                    </div>
                    <span class="tutor-owner-document-count">{{ $documents->count() }} tài liệu</span>
                </div>

                @if ($documents->isNotEmpty())
                    <div class="tutor-owner-document-list">
                        @foreach ($documents as $document)
                            <article class="tutor-owner-document-row">
                                <div class="tutor-owner-document-preview">
                                    @if ($document['preview_type'] === 'image')
                                        <img
                                            src="{{ route('tutor-registration.documents.download', ['document' => $document['id'], 'disposition' => 'inline']) }}"
                                            alt="Bản xem trước {{ $document['name'] }}"
                                            width="96"
                                            height="68"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    @else
                                        <x-directory-icon name="{{ $document['preview_type'] === 'pdf' ? 'document' : 'file-check' }}" />
                                    @endif
                                </div>

                                <div class="tutor-owner-document-copy">
                                    <h3>{{ $document['name'] }}</h3>
                                    <p>{{ $document['type_label'] }}</p>
                                    @if ($document['change_status_label'])
                                        <div class="tutor-owner-document-change tutor-owner-document-change--{{ $document['change_status_tone'] }}">
                                            <strong>{{ $document['change_status_label'] }} · {{ $document['change_action'] }}</strong>
                                            @if ($document['proposed_name'] && $document['proposed_name'] !== $document['name'])
                                                <span>Tên đề xuất: {{ $document['proposed_name'] }}</span>
                                            @endif
                                            @if ($document['change_rejection_reason'])
                                                <small>Lý do: {{ $document['change_rejection_reason'] }}</small>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <span class="tutor-owner-document-status tutor-owner-document-status--{{ $document['status_tone'] }}">
                                    <x-directory-icon name="{{ $document['status_tone'] === 'approved' ? 'circle-check' : ($document['status_tone'] === 'rejected' ? 'x-circle' : 'clock') }}" />
                                    {{ $document['status_label'] }}
                                </span>

                                @if ($document['available'])
                                    <a
                                        class="tutor-owner-document-link"
                                        href="{{ route('tutor-registration.documents.download', ['document' => $document['id'], 'disposition' => 'inline']) }}"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Xem
                                        <x-directory-icon name="arrow" />
                                    </a>
                                @else
                                    <span class="tutor-owner-document-link is-disabled" aria-disabled="true" title="Tệp không còn khả dụng trong kho lưu trữ.">
                                        Không khả dụng
                                    </span>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="tutor-owner-empty-state">
                        <x-directory-icon name="file-check" />
                        <p>Bạn chưa tải lên tài liệu minh chứng.</p>
                        @if ($canManageApproved)
                            <a href="{{ route('tutor-area.profile.edit') }}#tutor-profile-documents">Thêm minh chứng</a>
                        @elseif ($canEdit)
                            <a href="{{ route('tutor-registration.documents.edit') }}">Thêm minh chứng</a>
                        @endif
                    </div>
                @endif
            </article>
        </div>
    </section>
</x-tutor-account-layout>
