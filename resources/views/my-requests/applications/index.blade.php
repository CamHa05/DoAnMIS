<x-app-layout>
    @php
        $subjectLevel = $tutoringRequest->subjectLevel;
        $subjectName = trim((string) $subjectLevel?->subject?->subject_name);
        $levelName = trim((string) $subjectLevel?->level_name);
        $schedules = $tutoringRequest->schedules;
        $scheduleSummary = $schedules
            ->map(fn ($schedule) => $schedule->dayLabel())
            ->unique()
            ->implode(', ');
        $expectedFeeLabel = $tutoringRequest->expected_fee !== null
            ? number_format((float) $tutoringRequest->expected_fee, 0, ',', '.').' VNĐ'.$feeUnit
            : 'Chưa cập nhật';
        $sortOptions = [
            'newest' => 'Mới nhất',
            'oldest' => 'Cũ nhất',
            'fee_low' => 'Học phí thấp nhất',
            'fee_high' => 'Học phí cao nhất',
        ];
    @endphp

    <x-slot name="title">Gia sư ứng tuyển | GiaSu</x-slot>

    <section class="application-directory-page" aria-labelledby="application-directory-title">
        <div class="container application-directory-container">
            @if (session('success'))
                <div class="request-detail-flash request-detail-flash--success" role="status">
                    <x-directory-icon name="circle-check" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="request-detail-flash request-detail-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="application-directory-layout">
                <main class="application-directory-main">
                    <nav class="application-directory-breadcrumb" aria-label="Đường dẫn điều hướng">
                        <a href="{{ route('my-requests.index') }}">Yêu cầu của tôi</a>
                        <x-directory-icon name="chevron-right" />
                        <span aria-current="page">Gia sư ứng tuyển</span>
                    </nav>

                    <a class="application-directory-back" href="{{ route('my-requests.show', $tutoringRequest) }}">
                        <x-directory-icon name="arrow-left" />
                        Quay lại chi tiết yêu cầu
                    </a>

                    <header class="application-directory-header">
                        <div class="application-directory-title-row">
                            <h1 id="application-directory-title">Gia sư ứng tuyển</h1>
                            <span class="application-directory-request-status" data-tone="{{ $tutoringRequest->statusTone() }}">
                                {{ $requestStatusLabel }}
                            </span>
                        </div>
                        <p>Xem và lựa chọn gia sư phù hợp với yêu cầu học của bạn.</p>
                    </header>

                    @if (
                        strtoupper((string) $tutoringRequest->status) === \App\Models\TutoringRequest::STATUS_MATCHED
                        && $tutoringRequest->contract
                    )
                        <a
                            class="application-contract-cta"
                            href="{{ route('contracts.show', $tutoringRequest->contract) }}"
                        >
                            <x-directory-icon name="document" />
                            <span>Xem hợp đồng</span>
                            <x-directory-icon name="chevron-right" />
                        </a>
                    @endif

                    <section class="application-request-summary" aria-labelledby="application-request-summary-title">
                        <div class="application-request-summary-heading">
                            <span><x-directory-icon name="request" /></span>
                            <h2 id="application-request-summary-title">{{ $requestTitle }}</h2>
                        </div>
                        <dl>
                            <div>
                                <dt><span class="application-request-fact-icon"><x-directory-icon name="book-open" /></span><span>Môn học</span></dt>
                                <dd>{{ $subjectName ?: 'Chưa cập nhật' }}</dd>
                            </div>
                            <div>
                                <dt><span class="application-request-fact-icon"><x-directory-icon name="education" /></span><span>Trình độ</span></dt>
                                <dd>{{ $levelName ?: 'Chưa cập nhật' }}</dd>
                            </div>
                            <div>
                                <dt><span class="application-request-fact-icon"><x-directory-icon name="mode" /></span><span>Hình thức học</span></dt>
                                <dd>{{ $tutoringRequest->learningModeLabel() }}</dd>
                            </div>
                            <div>
                                <dt><span class="application-request-fact-icon"><x-directory-icon name="wallet" /></span><span>Học phí dự kiến</span></dt>
                                <dd>{{ $expectedFeeLabel }}</dd>
                            </div>
                            <div>
                                <dt><span class="application-request-fact-icon"><x-directory-icon name="calendar" /></span><span>Lịch học mong muốn</span></dt>
                                <dd>{{ $scheduleSummary ?: 'Chưa cập nhật' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="application-candidates-section" aria-labelledby="application-candidates-title">
                        <header class="application-candidates-heading">
                            <div>
                                <h2 id="application-candidates-title">Danh sách ứng viên</h2>
                                <p>{{ $tutoringRequest->applications_count }} gia sư</p>
                            </div>
                            <form method="GET" action="{{ route('my-requests.applications.index', $tutoringRequest) }}">
                                <label for="application-sort">Sắp xếp</label>
                                <select id="application-sort" name="sort" onchange="this.form.submit()">
                                    @foreach ($sortOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </header>

                        @if ($applications->isNotEmpty())
                            <div class="application-candidate-list">
                                @foreach ($applications as $application)
                                    @php
                                        $profile = $application->tutorProfile;
                                        $user = $profile?->user;
                                        $subjects = $profile?->tutorSubjects
                                            ?->map(fn ($tutorSubject) => trim((string) $tutorSubject->subject?->subject_name))
                                            ->filter()
                                            ->unique()
                                            ->implode(', ');
                                        $agreesWithExpectedFee = $application->proposed_fee === null;
                                        $feeLabel = $agreesWithExpectedFee
                                            ? $expectedFeeLabel
                                            : number_format((float) $application->proposed_fee, 0, ',', '.').' VNĐ'.$feeUnit;
                                        $teachingModes = collect([
                                            $profile?->supports_online ? 'Trực tuyến' : null,
                                            $profile?->supports_offline ? 'Tại nhà' : null,
                                        ])->filter()->implode(' · ');
                                        $canSelectByWorkflow = strtoupper((string) $tutoringRequest->status) === \App\Models\TutoringRequest::STATUS_OPEN
                                            && strtoupper((string) $application->status) === \App\Models\TutorApplication::STATUS_PENDING;
                                        $canSelect = $canSelectByWorkflow && $user?->isActive() === true;
                                    @endphp
                                    <article class="application-candidate-card">
                                        <div class="application-candidate-profile">
                                            <div id="application-avatar-{{ $application->getKey() }}" class="application-select-avatar-source">
                                                <x-tutor-avatar :user="$user" />
                                            </div>
                                            <div>
                                                <h3>{{ $user?->full_name ?: 'Gia sư không còn khả dụng' }}</h3>
                                                <p>{{ filled($profile?->headline) ? $profile->headline : 'Gia sư trên GiaSu' }}</p>
                                                @if ($profile?->approval_status === \App\Models\TutorProfile::STATUS_APPROVED)
                                                    <span class="application-profile-approved"><x-directory-icon name="verified" />Hồ sơ đã duyệt</span>
                                                @endif
                                                @if ($subjects)
                                                    <small><x-directory-icon name="book-open" />Chuyên môn: {{ $subjects }}</small>
                                                @endif
                                                @if (filled($profile?->teaching_experience))
                                                    <small><x-directory-icon name="education" />Kinh nghiệm: {{ \Illuminate\Support\Str::limit(trim($profile->teaching_experience), 76) }}</small>
                                                @endif
                                                @if ($teachingModes !== '')
                                                    <small><x-directory-icon name="mode" />Hình thức dạy: {{ $teachingModes }}</small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="application-candidate-proposal">
                                            <div>
                                                <span><x-directory-icon name="wallet" /></span>
                                                <p>{{ $agreesWithExpectedFee ? 'Đồng ý mức học phí dự kiến' : 'Học phí đề xuất' }}</p>
                                                <strong>{{ $feeLabel }}</strong>
                                            </div>
                                            <div>
                                                <span><x-directory-icon name="support" /></span>
                                                <p>Lời nhắn</p>
                                                <blockquote>{{ filled($application->message) ? $application->message : 'Gia sư không để lại lời nhắn.' }}</blockquote>
                                            </div>
                                            <time datetime="{{ $application->applied_at?->toDateTimeString() }}">
                                                <x-directory-icon name="clock" />
                                                {{ $application->applied_at?->locale('vi')->diffForHumans() ?: 'Chưa cập nhật' }}
                                            </time>
                                        </div>

                                        <div class="application-candidate-actions">
                                            <span class="application-owner-status" data-tone="{{ $application->ownerStatusTone() }}">
                                                {{ $application->ownerStatusLabel() }}
                                            </span>
                                            <a href="{{ route('my-requests.applications.show', [$tutoringRequest, $application]) }}">
                                                Xem hồ sơ
                                            </a>
                                            @if ($canSelect)
                                                <button
                                                    type="button"
                                                    data-select-tutor-open
                                                    data-select-tutor-action="{{ route('my-requests.applications.select', [$tutoringRequest, $application]) }}"
                                                    data-tutor-avatar-source="application-avatar-{{ $application->getKey() }}"
                                                    data-tutor-name="{{ $user?->full_name ?: 'Gia sư không còn khả dụng' }}"
                                                    data-tutor-headline="{{ filled($profile?->headline) ? $profile->headline : 'Gia sư trên GiaSu' }}"
                                                    data-tutor-approved="{{ $profile?->approval_status === \App\Models\TutorProfile::STATUS_APPROVED ? 'true' : 'false' }}"
                                                    data-tutor-subjects="{{ $subjects ?: 'Chưa cập nhật' }}"
                                                    data-tutor-modes="{{ $teachingModes ?: 'Chưa cập nhật' }}"
                                                    data-expected-fee="{{ $expectedFeeLabel }}"
                                                    data-has-proposed-fee="{{ $application->proposed_fee !== null ? 'true' : 'false' }}"
                                                    data-proposed-fee="{{ $application->proposed_fee !== null ? $feeLabel : '' }}"
                                                    data-application-message="{{ filled($application->message) ? $application->message : 'Gia sư không để lại lời nhắn.' }}"
                                                    data-application-applied="Ứng tuyển {{ $application->applied_at?->locale('vi')->diffForHumans() ?: 'chưa cập nhật' }}"
                                                >Chọn gia sư</button>
                                            @elseif ($canSelectByWorkflow && $user?->isDisabled())
                                                <button
                                                    class="application-selection-unavailable"
                                                    type="button"
                                                    disabled
                                                    data-select-tutor-disabled
                                                    title="Tài khoản gia sư đã bị vô hiệu hóa"
                                                >Không thể chọn</button>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if ($applications->hasPages())
                                <nav class="application-directory-pagination request-pagination" aria-label="Phân trang ứng viên">
                                    {{ $applications->links() }}
                                </nav>
                            @endif
                        @else
                            <div class="application-directory-empty-state">
                                <span><x-directory-icon name="users" /></span>
                                <h3>Chưa có gia sư ứng tuyển</h3>
                                <p>Yêu cầu của bạn chưa nhận được ứng tuyển nào. Hãy chờ thêm một chút.</p>
                            </div>
                        @endif
                    </section>
                </main>

                <aside class="application-directory-sidebar" aria-label="Tóm tắt ứng tuyển">
                    <section class="application-sidebar-card application-sidebar-counts" aria-labelledby="application-counts-title">
                        <header><x-directory-icon name="users" /><h2 id="application-counts-title">Tình trạng ứng tuyển</h2></header>
                        <dl>
                            <div><span><x-directory-icon name="users" /></span><dt>Tổng số gia sư ứng tuyển</dt><dd>{{ $tutoringRequest->applications_count }}</dd></div>
                            <div><span><x-directory-icon name="clock" /></span><dt>Đang chờ xem xét</dt><dd>{{ $tutoringRequest->pending_applications_count }}</dd></div>
                            <div><span><x-directory-icon name="circle-check" /></span><dt>Đã chọn</dt><dd>{{ $tutoringRequest->accepted_applications_count }}</dd></div>
                        </dl>
                    </section>

                    <section class="application-sidebar-card application-sidebar-deadline" aria-labelledby="application-deadline-title">
                        <x-directory-icon name="clock" />
                        <div>
                            <h2 id="application-deadline-title">Hạn ứng tuyển</h2>
                            <strong>{{ $tutoringRequest->expires_at?->format('d/m/Y') ?: 'Không có thời hạn' }}</strong>
                            @if ($tutoringRequest->expires_at)
                                <span>{{ $tutoringRequest->expires_at->isFuture() ? 'Còn '.(int) ceil(now()->diffInSeconds($tutoringRequest->expires_at) / 86400).' ngày' : 'Đã hết hạn' }}</span>
                            @endif
                        </div>
                    </section>

                    <section class="application-sidebar-card application-sidebar-tip" aria-labelledby="application-tip-title">
                        <x-directory-icon name="support" />
                        <h2 id="application-tip-title">Mẹo chọn gia sư</h2>
                        <ol>
                            <li>Xem kỹ hồ sơ và kinh nghiệm.</li>
                            <li>So sánh học phí đề xuất.</li>
                            <li>Đọc lời nhắn của gia sư.</li>
                            <li>Chọn người phù hợp với nhu cầu học.</li>
                        </ol>
                    </section>
                </aside>
            </div>
        </div>
    </section>

    @include('my-requests.applications.partials.select-tutor-modal', [
        'subjectName' => $subjectName,
        'levelName' => $levelName,
    ])
</x-app-layout>
