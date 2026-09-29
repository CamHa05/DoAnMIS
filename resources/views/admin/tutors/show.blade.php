@php
    $statusMeta = [
        \App\Models\TutorProfile::STATUS_PENDING => [
            'label' => 'Chờ xét duyệt',
            'class' => 'pending',
            'icon' => 'clock',
        ],
        \App\Models\TutorProfile::STATUS_APPROVED => [
            'label' => 'Đã duyệt',
            'class' => 'approved',
            'icon' => 'circle-check',
        ],
        \App\Models\TutorProfile::STATUS_REJECTED => [
            'label' => 'Từ chối',
            'class' => 'rejected',
            'icon' => 'x-circle',
        ],
    ];
    $profileStatus = $statusMeta[$tutorProfile->approval_status] ?? [
        'label' => $tutorProfile->approval_status,
        'class' => 'neutral',
        'icon' => 'info',
    ];
    $teachingModes = collect([
        $tutorProfile->supports_online ? 'Trực tuyến' : null,
        $tutorProfile->supports_offline ? 'Trực tiếp' : null,
    ])->filter()->values();
    $dayLabels = [
        1 => 'Thứ 2',
        2 => 'Thứ 3',
        3 => 'Thứ 4',
        4 => 'Thứ 5',
        5 => 'Thứ 6',
        6 => 'Thứ 7',
        7 => 'Chủ nhật',
    ];
@endphp

<x-admin-layout title="Chi tiết hồ sơ" active-section="tutors">
    <article class="admin-tutor-profile" data-admin-review>
        @if (session('success'))
            <div class="admin-tutor-profile__flash admin-tutor-profile__flash--success" role="status">
                <x-directory-icon name="circle-check" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="admin-tutor-profile__flash admin-tutor-profile__flash--error" role="alert">
                <x-directory-icon name="info" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-tutor-profile__flash admin-tutor-profile__flash--error" role="alert">
                <x-directory-icon name="info" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <nav class="admin-breadcrumb admin-tutor-profile__breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <a href="{{ route('admin.tutors.index') }}">Gia sư</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Chi tiết hồ sơ</span>
        </nav>

        <a class="admin-tutor-profile__back" href="{{ route('admin.tutors.index') }}">
            <x-directory-icon name="arrow-left" />
            <span>Quay lại danh sách</span>
        </a>

        <header class="admin-tutor-profile__hero">
            <div class="admin-tutor-profile__identity">
                <x-tutor-avatar :user="$tutorProfile->user" />
                <div>
                    <div class="admin-tutor-profile__name-row">
                        <h1>{{ $tutorProfile->user?->full_name ?: 'Chưa cập nhật' }}</h1>
                        <span class="admin-status-badge admin-status-badge--{{ $profileStatus['class'] }}">
                            <x-directory-icon
                                :name="$profileStatus['icon']"
                                data-status-icon="{{ $profileStatus['icon'] }}"
                            />
                            <span>{{ $profileStatus['label'] }}</span>
                        </span>
                    </div>
                    <p class="admin-tutor-profile__email">
                        <x-directory-icon name="mail" />
                        <span>{{ $tutorProfile->user?->email ?: 'Chưa cập nhật' }}</span>
                    </p>
                    <p class="admin-tutor-profile__headline">
                        {{ filled($tutorProfile->headline) ? $tutorProfile->headline : 'Chưa cập nhật' }}
                    </p>
                </div>
            </div>

            <dl class="admin-tutor-profile__hero-facts">
                <div>
                    <dt><x-directory-icon name="calendar" /> Gửi xét duyệt</dt>
                    <dd>
                        <time datetime="{{ $tutorProfile->submitted_at->toIso8601String() }}">
                            {{ $tutorProfile->submitted_at->format('d/m/Y H:i') }}
                        </time>
                    </dd>
                </div>
                <div>
                    <dt><x-directory-icon name="mode" /> Hình thức dạy</dt>
                    <dd>
                        @forelse ($teachingModes as $mode)
                            <span class="admin-tutor-profile__tag">{{ $mode }}</span>
                        @empty
                            <span>Chưa cập nhật</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </header>

        <div class="admin-tutor-profile__layout">
            <div class="admin-tutor-profile__main">
                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-bio">
                    <h2 id="admin-tutor-bio">
                        <x-directory-icon name="book-open" />
                        <span>Giới thiệu</span>
                    </h2>
                    <p>{{ filled($tutorProfile->bio) ? $tutorProfile->bio : 'Chưa cập nhật' }}</p>
                </section>

                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-background">
                    <h2 id="admin-tutor-background">
                        <x-directory-icon name="education" />
                        <span>Học vấn &amp; kinh nghiệm</span>
                    </h2>
                    <dl class="admin-tutor-profile__split-facts">
                        <div>
                            <dt>Trình độ học vấn</dt>
                            <dd>{{ filled($tutorProfile->education_summary) ? $tutorProfile->education_summary : 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt>Kinh nghiệm giảng dạy</dt>
                            <dd>{{ filled($tutorProfile->teaching_experience) ? $tutorProfile->teaching_experience : 'Chưa cập nhật' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-subjects">
                    <h2 id="admin-tutor-subjects">
                        <x-directory-icon name="book-open" />
                        <span>Chuyên môn</span>
                    </h2>
                    @if ($tutorProfile->tutorSubjects->isNotEmpty())
                        <div class="admin-tutor-profile__table-wrap">
                            <table class="admin-tutor-profile__table">
                                <thead>
                                    <tr>
                                        <th scope="col">Môn học</th>
                                        <th scope="col">Cấp/Lớp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tutorProfile->tutorSubjects as $tutorSubject)
                                        <tr>
                                            <th scope="row">{{ $tutorSubject->subject?->subject_name ?: 'Chưa cập nhật' }}</th>
                                            <td>
                                                @php
                                                    $levels = $tutorSubject->tutorSubjectLevels
                                                        ->pluck('subjectLevel')
                                                        ->filter()
                                                        ->sortBy(fn ($level) => [$level->sort_order ?? PHP_INT_MAX, $level->level_name])
                                                        ->pluck('level_name')
                                                        ->filter()
                                                        ->unique()
                                                        ->values();
                                                @endphp
                                                @forelse ($levels as $level)
                                                    <span class="admin-tutor-profile__tag">{{ $level }}</span>
                                                @empty
                                                    <span class="admin-tutor-profile__muted">Chưa cập nhật</span>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="admin-tutor-profile__empty">Chưa cập nhật chuyên môn</p>
                    @endif
                </section>

                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-teaching">
                    <h2 id="admin-tutor-teaching">
                        <x-directory-icon name="education" />
                        <span>Thông tin giảng dạy</span>
                    </h2>
                    <dl class="admin-tutor-profile__split-facts">
                        <div>
                            <dt>Mức học phí</dt>
                            <dd>
                                @if ($tutorProfile->hourly_rate !== null)
                                    {{ number_format((float) $tutorProfile->hourly_rate, 0, ',', '.') }} VNĐ/giờ
                                @else
                                    Chưa cập nhật
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Hình thức dạy</dt>
                            <dd>
                                @forelse ($teachingModes as $mode)
                                    <span class="admin-tutor-profile__tag">{{ $mode }}</span>
                                @empty
                                    Chưa cập nhật
                                @endforelse
                            </dd>
                        </div>
                    </dl>
                </section>

                @if ($tutorProfile->teachingAreas->isNotEmpty())
                    <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-areas">
                        <h2 id="admin-tutor-areas">
                            <x-directory-icon name="location" />
                            <span>Khu vực giảng dạy</span>
                        </h2>
                        <div class="admin-tutor-profile__tags">
                            @foreach ($tutorProfile->teachingAreas as $area)
                                @if ($area->ward)
                                    <span class="admin-tutor-profile__tag">
                                        {{ $area->ward->ward_name }}@if ($area->ward->province), {{ $area->ward->province->province_name }}@endif
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-availability">
                    <h2 id="admin-tutor-availability">
                        <x-directory-icon name="calendar-days" />
                        <span>Lịch rảnh</span>
                    </h2>
                    @if ($tutorProfile->availabilities->isNotEmpty())
                        <div class="admin-tutor-profile__table-wrap">
                            <table class="admin-tutor-profile__table admin-tutor-profile__table--compact">
                                <thead>
                                    <tr>
                                        <th scope="col">Thứ</th>
                                        <th scope="col">Khung giờ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tutorProfile->availabilities as $availability)
                                        <tr>
                                            <th scope="row">{{ $dayLabels[$availability->day_of_week] ?? 'Không xác định' }}</th>
                                            <td>
                                                @if ($availability->timeSlot)
                                                    {{ substr((string) $availability->timeSlot->start_time, 0, 5) }}–{{ substr((string) $availability->timeSlot->end_time, 0, 5) }}
                                                @else
                                                    <span class="admin-tutor-profile__muted">Chưa cập nhật</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="admin-tutor-profile__empty">Chưa cập nhật lịch rảnh</p>
                    @endif
                </section>

                <section class="admin-tutor-profile__section" aria-labelledby="admin-tutor-documents">
                    <h2 id="admin-tutor-documents">
                        <x-directory-icon name="document" />
                        <span>Minh chứng</span>
                    </h2>
                    @if ($tutorProfile->documents->isNotEmpty())
                        <div class="admin-tutor-profile__table-wrap">
                            <table class="admin-tutor-profile__table admin-tutor-profile__documents">
                                <thead>
                                    <tr>
                                        <th scope="col">Tên tài liệu</th>
                                        <th scope="col">Loại tài liệu</th>
                                        <th scope="col">Trạng thái</th>
                                        <th scope="col">Ngày gửi</th>
                                        <th scope="col">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tutorProfile->documents as $document)
                                        @php
                                            $documentStatusTone = $document->verificationStatusTone();
                                            $documentStatusTone = $documentStatusTone === 'unknown'
                                                ? 'neutral'
                                                : $documentStatusTone;
                                            $documentStatusIcon = match ($document->verification_status) {
                                                \App\Models\TutorDocument::STATUS_APPROVED => 'circle-check',
                                                \App\Models\TutorDocument::STATUS_REJECTED => 'x-circle',
                                                \App\Models\TutorDocument::STATUS_PENDING => 'clock',
                                                default => 'info',
                                            };
                                            $documentAccessItem = $documentAccess[(int) $document->document_id] ?? [
                                                'available' => false,
                                                'preview_type' => null,
                                            ];
                                            $documentUrlParameters = [
                                                'tutorProfile' => $tutorProfile,
                                                'document' => $document,
                                            ];
                                            $documentDownloadUrl = route(
                                                'admin.tutors.documents.download',
                                                $documentUrlParameters
                                            );
                                            $documentPreviewUrl = route(
                                                'admin.tutors.documents.download',
                                                [...$documentUrlParameters, 'disposition' => 'inline']
                                            );
                                        @endphp
                                        <tr>
                                            <th scope="row">{{ filled($document->document_name) ? $document->document_name : 'Chưa cập nhật' }}</th>
                                            <td>{{ $document->typeLabel() }}</td>
                                            <td>
                                                <span class="admin-status-badge admin-status-badge--{{ $documentStatusTone }}">
                                                    <x-directory-icon :name="$documentStatusIcon" />
                                                    <span>{{ $document->verificationStatusLabel() }}</span>
                                                </span>
                                            </td>
                                            <td>
                                                @if ($document->uploaded_at)
                                                    <time datetime="{{ $document->uploaded_at->toDateString() }}">
                                                        {{ $document->uploaded_at->format('d/m/Y') }}
                                                    </time>
                                                @else
                                                    <span class="admin-tutor-profile__muted">Chưa cập nhật</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($documentAccessItem['available'])
                                                    <div class="admin-tutor-profile__document-actions">
                                                        @if ($documentAccessItem['preview_type'] === 'pdf')
                                                            <a
                                                                class="admin-tutor-profile__document-link is-primary"
                                                                href="{{ $documentPreviewUrl }}"
                                                                target="_blank"
                                                                rel="noopener"
                                                            >
                                                                <x-directory-icon name="search" />
                                                                <span>Xem</span>
                                                            </a>
                                                        @elseif ($documentAccessItem['preview_type'] === 'image')
                                                            <button
                                                                class="admin-tutor-profile__document-link is-primary"
                                                                type="button"
                                                                data-document-preview-open
                                                                data-preview-src="{{ $documentPreviewUrl }}"
                                                                data-download-src="{{ $documentDownloadUrl }}"
                                                                data-preview-name="{{ filled($document->document_name) ? $document->document_name : 'Tài liệu minh chứng' }}"
                                                            >
                                                                <x-directory-icon name="search" />
                                                                <span>Xem</span>
                                                            </button>
                                                        @else
                                                            <span class="admin-tutor-profile__preview-unavailable">Không hỗ trợ xem trước</span>
                                                        @endif

                                                        <a
                                                            class="admin-tutor-profile__document-link is-secondary"
                                                            href="{{ $documentDownloadUrl }}"
                                                        >
                                                            <x-directory-icon name="document" />
                                                            <span>Tải xuống</span>
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="admin-tutor-profile__muted">Tệp không khả dụng</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="admin-tutor-profile__empty">Chưa có tài liệu minh chứng</p>
                    @endif
                </section>

                @if ($changeSummaries->isNotEmpty())
                    <section class="admin-tutor-profile__section admin-profile-changes" aria-labelledby="admin-tutor-pending-changes">
                        <div class="admin-profile-changes__heading">
                            <div>
                                <h2 id="admin-tutor-pending-changes">
                                    <x-directory-icon name="clock" />
                                    <span>Thay đổi chờ xét duyệt</span>
                                </h2>
                                <p>Duyệt từng thay đổi độc lập. Dữ liệu đã duyệt hiện tại vẫn giữ nguyên nếu từ chối.</p>
                            </div>
                            <span>{{ $changeSummaries->count() }} thay đổi</span>
                        </div>

                        <div class="admin-profile-changes__list">
                            @foreach ($changeSummaries as $summary)
                                @php
                                    $change = $summary['change'];
                                    $proposedAccess = $changeDocumentAccess->get((int) $change->getKey(), [
                                        'available' => false,
                                        'preview_type' => null,
                                    ]);
                                @endphp
                                <article class="admin-profile-change">
                                    <header>
                                        <div>
                                            <span class="admin-profile-change__icon"><x-directory-icon name="edit" /></span>
                                            <div>
                                                <h3>{{ $change->typeLabel() }}</h3>
                                                <p>{{ $change->actionLabel() }} · Gửi {{ $change->submitted_at?->format('d/m/Y H:i') }}</p>
                                            </div>
                                        </div>
                                        <span class="admin-status-badge admin-status-badge--pending">
                                            <x-directory-icon name="clock" /> Chờ duyệt
                                        </span>
                                    </header>

                                    <div class="admin-profile-change__comparison">
                                        <section>
                                            <h4>Đang hoạt động</h4>
                                            <p>{{ $summary['current'] }}</p>
                                        </section>
                                        <section>
                                            <h4>Thay đổi đề xuất</h4>
                                            <p>{{ $summary['proposed'] }}</p>
                                            @if ($change->change_type === \App\Models\TutorProfileChangeRequest::TYPE_DOCUMENT && $proposedAccess['available'])
                                                <a
                                                    href="{{ route('admin.tutors.changes.document', ['tutorProfile' => $tutorProfile, 'changeRequest' => $change, 'disposition' => 'inline']) }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <x-directory-icon name="eye" /> Xem tệp đề xuất
                                                </a>
                                            @endif
                                        </section>
                                    </div>

                                    <div class="admin-profile-change__actions">
                                        <form method="POST" action="{{ route('admin.tutors.changes.approve', ['tutorProfile' => $tutorProfile, 'changeRequest' => $change]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="is-primary" type="submit">
                                                <x-directory-icon name="circle-check" /> Duyệt thay đổi
                                            </button>
                                        </form>
                                        <details>
                                            <summary><x-directory-icon name="x-circle" /> Từ chối</summary>
                                            <form method="POST" action="{{ route('admin.tutors.changes.reject', ['tutorProfile' => $tutorProfile, 'changeRequest' => $change]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <label>
                                                    <span>Lý do từ chối</span>
                                                    <textarea name="change_rejection_reason" rows="3" minlength="10" maxlength="1000" required></textarea>
                                                </label>
                                                <button type="submit">Xác nhận từ chối</button>
                                            </form>
                                        </details>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="admin-tutor-profile__aside" aria-label="Xét duyệt hồ sơ">
                <section class="admin-tutor-profile__review-card">
                    <h2>
                        <x-directory-icon name="shield" />
                        <span>Xét duyệt hồ sơ</span>
                    </h2>
                    <div class="admin-tutor-profile__current-status">
                        <span>Trạng thái hiện tại</span>
                        <span class="admin-status-badge admin-status-badge--{{ $profileStatus['class'] }}">
                            <x-directory-icon :name="$profileStatus['icon']" />
                            <span>{{ $profileStatus['label'] }}</span>
                        </span>
                    </div>
                    <dl class="admin-tutor-profile__review-stats">
                        <div>
                            <dt><x-directory-icon name="book-open" /> Số môn đăng ký</dt>
                            <dd>{{ number_format($tutorProfile->tutorSubjects->count(), 0, ',', '.') }} môn</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="mode" /> Hình thức dạy</dt>
                            <dd>{{ $teachingModes->isNotEmpty() ? $teachingModes->join(', ') : 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="document" /> Tài liệu minh chứng</dt>
                            <dd>{{ number_format($tutorProfile->documents->count(), 0, ',', '.') }} tài liệu</dd>
                        </div>
                    </dl>

                    @if ($tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_PENDING)
                        <div class="admin-tutor-profile__review-actions" aria-label="Thao tác xét duyệt">
                            <button type="button" data-admin-review-open="reject">
                                <x-directory-icon name="x-circle" />
                                <span>Từ chối</span>
                            </button>
                            <button class="is-primary" type="button" data-admin-review-open="approve">
                                <x-directory-icon name="circle-check" />
                                <span>Duyệt hồ sơ</span>
                            </button>
                        </div>
                    @elseif ($tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_APPROVED)
                        <div class="admin-tutor-profile__review-result admin-tutor-profile__review-result--approved">
                            <x-directory-icon name="circle-check" />
                            <span>
                                <strong>Hồ sơ đã được duyệt</strong>
                                @if ($tutorProfile->approved_at)
                                    <small>Duyệt lúc {{ $tutorProfile->approved_at->format('d/m/Y H:i') }}</small>
                                @endif
                            </span>
                        </div>
                    @elseif ($tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_REJECTED)
                        <div class="admin-tutor-profile__review-result admin-tutor-profile__review-result--rejected">
                            <x-directory-icon name="x-circle" />
                            <span>
                                <strong>Hồ sơ đã bị từ chối</strong>
                                @if ($tutorProfile->rejected_at)
                                    <small>Từ chối lúc {{ $tutorProfile->rejected_at->format('d/m/Y H:i') }}</small>
                                @endif
                                @if (filled($tutorProfile->rejection_reason))
                                    <small class="admin-tutor-profile__rejection-reason">Lý do: {{ $tutorProfile->rejection_reason }}</small>
                                @endif
                            </span>
                        </div>
                    @endif
                </section>

                <section class="admin-tutor-profile__notice">
                    <h2>
                        <x-directory-icon name="info" />
                        <span>Lưu ý</span>
                    </h2>
                    <ul>
                        <li>Kiểm tra kỹ thông tin và minh chứng trước khi duyệt.</li>
                        <li>Chỉ duyệt khi hồ sơ đầy đủ và phù hợp.</li>
                        <li>Sau khi xử lý, trạng thái hồ sơ sẽ được cập nhật.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </article>

    @if ($hasImagePreviews)
        <dialog
            id="admin-document-preview-dialog"
            class="admin-review-dialog admin-document-preview"
            aria-labelledby="admin-document-preview-title"
            data-document-preview-dialog
        >
            <div class="admin-review-dialog__surface">
                <button class="admin-review-dialog__close" type="button" aria-label="Đóng bản xem trước" data-document-preview-close>
                    <x-directory-icon name="x-circle" />
                </button>
                <h2 id="admin-document-preview-title" data-document-preview-title>Xem minh chứng</h2>
                <div class="admin-document-preview__frame">
                    <img alt="" data-document-preview-image>
                    <p id="admin-document-preview-message" data-document-preview-message hidden>Không thể hiển thị bản xem trước. Vui lòng tải tài liệu xuống.</p>
                </div>
                <div class="admin-document-preview__actions">
                    <button class="admin-review-dialog__button" type="button" data-document-preview-close>Đóng</button>
                    <a class="admin-review-dialog__button admin-review-dialog__button--approve" href="#" data-document-preview-download data-document-preview-initial>
                        <x-directory-icon name="document" />
                        <span>Tải xuống</span>
                    </a>
                </div>
            </div>
        </dialog>
    @endif

    @if ($tutorProfile->approval_status === \App\Models\TutorProfile::STATUS_PENDING)
        <dialog
            id="admin-approve-dialog"
            class="admin-review-dialog admin-review-dialog--approve"
            aria-labelledby="admin-approve-title"
            aria-describedby="admin-approve-description"
            data-admin-review-dialog="approve"
        >
            <div class="admin-review-dialog__surface">
                <div class="admin-review-dialog__icon" aria-hidden="true">
                    <x-directory-icon name="circle-check" />
                </div>
                <h2 id="admin-approve-title">Duyệt hồ sơ gia sư?</h2>
                <div id="admin-approve-description" class="admin-review-dialog__copy">
                    <p>Hồ sơ của <strong>{{ $tutorProfile->user?->full_name ?: 'gia sư này' }}</strong> sẽ được chuyển sang trạng thái Đã duyệt.</p>
                    <p>Sau khi duyệt, gia sư có thể xuất hiện trên hệ thống theo quy tắc hiện tại.</p>
                </div>
                <form method="POST" action="{{ route('admin.tutors.approve', $tutorProfile) }}" data-admin-review-form>
                    @csrf
                    @method('PATCH')
                    <div class="admin-review-dialog__actions">
                        <button class="admin-review-dialog__button" type="button" data-admin-review-close>Hủy</button>
                        <button class="admin-review-dialog__button admin-review-dialog__button--approve" type="submit" data-admin-review-submit data-initial-focus>
                            <x-directory-icon name="circle-check" />
                            <span data-admin-review-submit-label>Xác nhận duyệt</span>
                        </button>
                    </div>
                </form>
            </div>
        </dialog>

        <dialog
            id="admin-reject-dialog"
            class="admin-review-dialog admin-review-dialog--reject"
            aria-labelledby="admin-reject-title"
            aria-describedby="admin-reject-description"
            data-admin-review-dialog="reject"
            @if ($errors->has('rejection_reason')) data-auto-open="true" @endif
        >
            <div class="admin-review-dialog__surface">
                <button class="admin-review-dialog__close" type="button" aria-label="Đóng hộp thoại từ chối" data-admin-review-close>
                    <x-directory-icon name="x-circle" />
                </button>
                <div class="admin-review-dialog__heading">
                    <div class="admin-review-dialog__icon" aria-hidden="true">
                        <x-directory-icon name="x-circle" />
                    </div>
                    <div>
                        <h2 id="admin-reject-title">Từ chối hồ sơ gia sư</h2>
                        <p id="admin-reject-description">Vui lòng nhập lý do để gia sư biết hồ sơ cần điều chỉnh gì.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.tutors.reject', $tutorProfile) }}" data-admin-review-form data-reject-form novalidate>
                    @csrf
                    @method('PATCH')
                    <div class="admin-review-dialog__field @error('rejection_reason') has-error @enderror">
                        <label for="rejection_reason">Lý do từ chối <span aria-hidden="true">*</span></label>
                        <textarea
                            id="rejection_reason"
                            name="rejection_reason"
                            rows="5"
                            minlength="10"
                            maxlength="1000"
                            required
                            aria-required="true"
                            aria-invalid="{{ $errors->has('rejection_reason') ? 'true' : 'false' }}"
                            aria-describedby="rejection-reason-error rejection-reason-help"
                            placeholder="Ví dụ: Minh chứng học vấn chưa đầy đủ hoặc chưa rõ ràng..."
                            data-rejection-reason
                            data-initial-focus
                        >{{ old('rejection_reason') }}</textarea>
                        <div class="admin-review-dialog__field-meta">
                            <p id="rejection-reason-error" class="admin-review-dialog__error" data-rejection-error @if (! $errors->has('rejection_reason')) hidden @endif>{{ $errors->first('rejection_reason') }}</p>
                            <span aria-live="polite"><span data-rejection-count>{{ mb_strlen(old('rejection_reason', '')) }}</span>/1000</span>
                        </div>
                        <p id="rejection-reason-help" class="admin-review-dialog__help">Lý do này sẽ được lưu lại cùng kết quả xét duyệt.</p>
                    </div>
                    <div class="admin-review-dialog__actions">
                        <button class="admin-review-dialog__button" type="button" data-admin-review-close>Hủy</button>
                        <button class="admin-review-dialog__button admin-review-dialog__button--reject" type="submit" data-admin-review-submit>
                            <x-directory-icon name="x-circle" />
                            <span data-admin-review-submit-label>Xác nhận từ chối</span>
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif
</x-admin-layout>
