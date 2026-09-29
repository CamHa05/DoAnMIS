<x-app-layout>
    @php
        $subjectLevel = $tutoringRequest->subjectLevel;
        $subject = trim((string) $subjectLevel?->subject?->subject_name);
        $level = trim((string) $subjectLevel?->level_name);
        $displayLevel = preg_replace('/^Lớp(?=\s|$)/iu', 'lớp', $level) ?? $level;
        $subjectAndLevel = collect([$subject, $displayLevel])
            ->filter(fn ($value) => filled($value))
            ->implode(' ');
        $learningMode = strtoupper(trim((string) $tutoringRequest->learning_mode));
        $isOffline = $learningMode === 'OFFLINE';
        $province = $isOffline
            ? trim((string) $tutoringRequest->ward?->province?->province_name)
            : '';
        $location = $isOffline
            ? collect([
                trim((string) $tutoringRequest->ward?->ward_name),
                $province,
            ])->filter(fn ($value) => filled($value))->unique()->implode(', ')
            : '';
        $title = match (true) {
            $learningMode === 'ONLINE' && $subjectAndLevel !== '' => 'Cần gia sư '.$subjectAndLevel.' học Online',
            $isOffline && $subjectAndLevel !== '' && $province !== '' => 'Cần gia sư '.$subjectAndLevel.' tại '.$province,
            $subjectAndLevel !== '' => 'Cần gia sư '.$subjectAndLevel,
            default => 'Yêu cầu tìm gia sư',
        };
        $status = strtoupper(trim((string) $tutoringRequest->status));
        $statusLabel = match ($status) {
            'OPEN' => 'Đang mở',
            'MATCHED' => 'Đã ghép gia sư',
            'EXPIRED' => 'Đã hết hạn',
            'PENDING' => 'Chờ xử lý',
            default => $status !== '' ? $status : 'Chưa cập nhật',
        };
        $genderLabel = match (strtoupper(trim((string) $tutoringRequest->preferred_tutor_gender))) {
            'FEMALE' => 'Nữ',
            'MALE' => 'Nam',
            default => 'Không yêu cầu',
        };
        $feeUnit = match (strtoupper(trim((string) $tutoringRequest->fee_type))) {
            'HOURLY' => '/giờ',
            'MONTHLY' => '/tháng',
            default => '',
        };
        $feeLabel = $tutoringRequest->expected_fee !== null
            ? number_format((float) $tutoringRequest->expected_fee, 0, ',', '.').' VNĐ'.$feeUnit
            : 'Chưa cập nhật';
        $createdLabel = $tutoringRequest->created_at?->locale('vi')->diffForHumans();
        $remainingSeconds = $tutoringRequest->expires_at
            ? now()->diffInSeconds($tutoringRequest->expires_at, false)
            : null;
        $isAcceptingApplications = $status === 'OPEN'
            && ($remainingSeconds === null || $remainingSeconds > 0);
        $deadlineDate = $tutoringRequest->expires_at?->format('d/m/Y');
        $remainingLabel = match (true) {
            $remainingSeconds === null => 'Không giới hạn',
            $remainingSeconds <= 0 => 'Đã hết hạn',
            $remainingSeconds < 86400 => 'Còn dưới 1 ngày',
            default => 'Còn '.(int) ceil($remainingSeconds / 86400).' ngày',
        };
        $orderedSchedules = $tutoringRequest->schedules
            ->sortBy(function ($schedule) {
                $timeSlot = $schedule->timeSlot;

                return sprintf(
                    '%d-%s-%s-%d',
                    (int) $schedule->day_of_week,
                    substr((string) $timeSlot?->start_time, 0, 5),
                    substr((string) $timeSlot?->end_time, 0, 5),
                    (int) $schedule->request_schedule_id,
                );
            })
            ->values();
        $learner = $tutoringRequest->user;
        $learnerName = trim((string) $learner?->full_name) ?: 'Người học';
        $scheduleDaySummary = $orderedSchedules
            ->map(fn ($schedule) => $schedule->dayLabel())
            ->unique()
            ->implode(', ');
        $applicationStatusLabel = $currentApplication
            ? match (strtoupper((string) $currentApplication->status)) {
                'PENDING' => 'Đang chờ phản hồi',
                'ACCEPTED' => 'Đã được chọn',
                'REJECTED' => 'Không được chọn',
                'EXPIRED' => 'Đã hết hạn',
                default => $currentApplication->statusLabel(),
            }
            : null;
    @endphp

    <x-slot name="title">{{ $title }} | GiaSu</x-slot>

    <section class="public-request-detail-page" aria-labelledby="public-request-title">
        <div class="container request-detail-container">
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

            <nav class="request-detail-breadcrumb" aria-label="Đường dẫn điều hướng">
                <a href="{{ route('home') }}">Trang chủ</a>
                <x-directory-icon name="chevron-right" />
                <a href="{{ route('requests.index') }}">Yêu cầu học</a>
                <x-directory-icon name="chevron-right" />
                <span aria-current="page">Chi tiết yêu cầu</span>
            </nav>

            <a class="request-detail-back" href="{{ route('requests.index') }}">
                <x-directory-icon name="arrow-left" />
                Quay lại danh sách
            </a>

            <div class="request-detail-layout">
                <main class="request-detail-main">
                    <header class="request-detail-heading">
                        <div class="request-detail-title-row">
                            <h1 id="public-request-title">{{ $title }}</h1>
                            <span class="request-detail-status" data-tone="{{ $tutoringRequest->statusTone() }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <p class="request-detail-meta">
                            @if ($createdLabel)
                                <span>Đăng {{ $createdLabel }}</span>
                                <span aria-hidden="true">•</span>
                            @endif
                            <span>Mã yêu cầu: #YC{{ str_pad((string) $tutoringRequest->request_id, 5, '0', STR_PAD_LEFT) }}</span>
                        </p>
                    </header>

                    <section class="request-detail-card request-detail-learner" aria-labelledby="request-learner-title">
                        <h2 id="request-learner-title">Thông tin người học</h2>
                        <div class="request-detail-learner-person">
                            <x-tutor-avatar :user="$learner" />
                            <div>
                                <strong>{{ $learnerName }}</strong>
                                <span>Người học</span>
                            </div>
                        </div>
                    </section>

                    <section class="request-detail-card request-detail-information" aria-labelledby="request-information-title">
                        <h2 id="request-information-title">Thông tin yêu cầu học</h2>
                        <dl class="request-detail-facts">
                            <div>
                                <dt><span><x-directory-icon name="book-open" /></span>Môn học</dt>
                                <dd>{{ $subject ?: 'Chưa cập nhật' }}</dd>
                            </div>
                            <div>
                                <dt><span><x-directory-icon name="education" /></span>Trình độ</dt>
                                <dd>{{ $level ?: 'Chưa cập nhật' }}</dd>
                            </div>
                            <div>
                                <dt><span><x-directory-icon :name="$isOffline ? 'home' : 'mode'" /></span>Hình thức học</dt>
                                <dd>{{ $tutoringRequest->learningModeLabel() }}</dd>
                            </div>
                            @if ($isOffline && $location !== '')
                                <div>
                                    <dt><span><x-directory-icon name="location" /></span>Khu vực</dt>
                                    <dd>{{ $location }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt><span><x-directory-icon name="wallet" /></span>Loại học phí</dt>
                                <dd>{{ strtoupper((string) $tutoringRequest->fee_type) === 'MONTHLY' ? 'Theo tháng' : 'Theo giờ' }}</dd>
                            </div>
                            <div>
                                <dt><span><x-directory-icon name="wallet" /></span>Học phí dự kiến</dt>
                                <dd>{{ $feeLabel }}</dd>
                            </div>
                            <div>
                                <dt><span><x-directory-icon name="users" /></span>Ưu tiên gia sư</dt>
                                <dd>{{ $genderLabel }}</dd>
                            </div>
                        </dl>

                        <div class="request-detail-schedule" aria-labelledby="request-schedule-title">
                            <h3 id="request-schedule-title">
                                <span><x-directory-icon name="calendar" /></span>
                                Lịch học mong muốn
                            </h3>
                            @if ($orderedSchedules->isNotEmpty())
                                <ul>
                                    @foreach ($orderedSchedules as $schedule)
                                        @php($timeSlot = $schedule->timeSlot)
                                        @if ($timeSlot)
                                            <li>
                                                {{ $schedule->dayLabel() }} ·
                                                {{ substr((string) $timeSlot->start_time, 0, 5) }}–{{ substr((string) $timeSlot->end_time, 0, 5) }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                <p>Chưa có lịch học mong muốn.</p>
                            @endif
                        </div>
                    </section>

                    <section class="request-detail-card request-detail-description" aria-labelledby="request-description-title">
                        <header>
                            <span><x-directory-icon name="document" /></span>
                            <h2 id="request-description-title">Mô tả nhu cầu</h2>
                        </header>
                        <p>{{ filled($tutoringRequest->description) ? $tutoringRequest->description : 'Yêu cầu này chưa có mô tả.' }}</p>
                    </section>
                </main>

                <aside class="request-detail-sidebar" aria-label="Tóm tắt và tác vụ yêu cầu học">
                    <section class="request-detail-card request-detail-deadline" aria-labelledby="request-deadline-title">
                        <span class="request-detail-deadline-icon"><x-directory-icon name="clock" /></span>
                        <div>
                            <h2 id="request-deadline-title">Hạn ứng tuyển</h2>
                            <strong>{{ $deadlineDate ?: 'Không có thời hạn' }}</strong>
                            <span>{{ $remainingLabel }}</span>
                        </div>
                    </section>

                    <section class="request-detail-card request-detail-role-panel" data-context="{{ $viewerContext }}">
                        @if ($viewerContext === 'owner')
                            <a class="request-detail-primary-action" href="{{ route('my-requests.applications.index', $tutoringRequest) }}">
                                <x-directory-icon name="users" />
                                <span class="request-detail-action-label"><span class="request-detail-action-prefix">Xem gia sư </span>ứng tuyển ({{ $tutoringRequest->applications_count }})</span>
                                <x-directory-icon name="chevron-right" />
                            </a>
                            <div class="request-detail-role-note">
                                <x-directory-icon name="info" />
                                <p><strong>Hãy sớm xem hồ sơ ứng tuyển</strong><span>So sánh thông tin và phản hồi gia sư phù hợp trước khi yêu cầu hết hạn.</span></p>
                            </div>
                        @elseif ($viewerContext === 'guest_eligible')
                            <a class="request-detail-primary-action" href="{{ route('requests.applications.login', $tutoringRequest) }}">
                                <x-directory-icon name="send" />
                                <span class="request-detail-action-label">Ứng tuyển ngay</span>
                            </a>
                            <div class="request-detail-role-note">
                                <x-directory-icon name="info" />
                                <p><span>Bạn cần đăng nhập để tiếp tục ứng tuyển.</span></p>
                            </div>
                        @elseif ($viewerContext === 'tutor_identity_required')
                            <a class="request-detail-primary-action" href="{{ route('identity-verification.show') }}">
                                <x-directory-icon name="verified" />
                                <span class="request-detail-action-label">Xác minh danh tính</span>
                                <x-directory-icon name="chevron-right" />
                            </a>
                            <div class="request-detail-role-note">
                                <x-directory-icon name="info" />
                                <p><strong>Chưa đủ điều kiện ứng tuyển</strong><span>{{ $identityVerificationMessage }}</span></p>
                            </div>
                        @elseif ($viewerContext === 'tutor_eligible')
                            <button
                                class="request-detail-primary-action"
                                type="button"
                                aria-haspopup="dialog"
                                aria-controls="request-application-dialog-{{ $tutoringRequest->getKey() }}"
                                data-request-application-open
                            >
                                <x-directory-icon name="send" />
                                <span class="request-detail-action-label">Ứng tuyển ngay</span>
                            </button>
                            <div class="request-detail-role-note">
                                <x-directory-icon name="info" />
                                <p><strong>Bạn đáp ứng điều kiện ứng tuyển</strong><span>Gửi mức học phí và lời nhắn để người học xem cùng hồ sơ của bạn.</span></p>
                            </div>
                        @elseif ($viewerContext === 'tutor_applied')
                            <div class="request-detail-applied-status">
                                <x-directory-icon name="circle-check" />
                                <p><strong>Đã ứng tuyển</strong><span>{{ $applicationStatusLabel }}</span></p>
                            </div>
                            <dl class="request-detail-application-meta">
                                <div>
                                    <dt>Học phí</dt>
                                    <dd>
                                        {{ $currentApplication->proposed_fee !== null
                                            ? number_format((float) $currentApplication->proposed_fee, 0, ',', '.').' VNĐ'.$feeUnit
                                            : 'Theo mức yêu cầu' }}
                                    </dd>
                                </div>
                                @if ($currentApplication->applied_at)
                                    <div><dt>Ngày ứng tuyển</dt><dd>{{ $currentApplication->applied_at->format('d/m/Y H:i') }}</dd></div>
                                @endif
                                @if (filled($currentApplication->message))
                                    <div class="request-detail-application-message"><dt>Lời nhắn</dt><dd>{{ $currentApplication->message }}</dd></div>
                                @endif
                            </dl>
                        @else
                            <div class="request-detail-role-note request-detail-role-note--neutral">
                                <x-directory-icon name="info" />
                                <p><strong>Đây là yêu cầu học công khai</strong><span>Thông tin liên hệ chỉ được chia sẻ theo quy trình ghép gia sư của GiaSu.</span></p>
                            </div>
                        @endif

                        @unless ($isAcceptingApplications)
                            <p class="request-detail-closed-note">Yêu cầu hiện không nhận thêm ứng tuyển.</p>
                        @endunless
                    </section>

                    <section class="request-detail-card request-detail-summary" aria-labelledby="request-summary-title">
                        <header>
                            <x-directory-icon name="document" />
                            <h2 id="request-summary-title">Tóm tắt nhanh</h2>
                        </header>
                        <dl>
                            <div><dt><x-directory-icon name="book-open" />Môn học</dt><dd>{{ $subject ?: 'Chưa cập nhật' }}</dd></div>
                            <div><dt><x-directory-icon name="education" />Trình độ</dt><dd>{{ $level ?: 'Chưa cập nhật' }}</dd></div>
                            <div><dt><x-directory-icon :name="$isOffline ? 'home' : 'mode'" />Hình thức học</dt><dd>{{ $tutoringRequest->learningModeLabel() }}</dd></div>
                            @if ($isOffline && $location !== '')
                                <div><dt><x-directory-icon name="location" />Khu vực</dt><dd>{{ $location }}</dd></div>
                            @endif
                            <div><dt><x-directory-icon name="wallet" />Học phí dự kiến</dt><dd>{{ $feeLabel }}</dd></div>
                            <div><dt><x-directory-icon name="calendar" />Lịch học</dt><dd>{{ $scheduleDaySummary ?: 'Chưa cập nhật' }}</dd></div>
                            <div><dt><x-directory-icon name="users" />Ưu tiên gia sư</dt><dd>{{ $genderLabel }}</dd></div>
                        </dl>
                    </section>

                    <section class="request-detail-tip" aria-label="Gợi ý theo vai trò">
                        <x-directory-icon name="support" />
                        <p>
                            <strong>{{ str_starts_with($viewerContext, 'tutor_') ? 'Mẹo nhỏ cho gia sư' : 'Thông tin dành cho người học' }}</strong>
                            <span>
                                {{ str_starts_with($viewerContext, 'tutor_')
                                    ? 'Đọc kỹ môn học, hình thức và lịch mong muốn trước khi ứng tuyển.'
                                    : 'Tạo yêu cầu học riêng nếu bạn có nhu cầu khác để nhận ứng viên phù hợp hơn.' }}
                            </span>
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </section>

    @if ($viewerContext === 'tutor_eligible')
        @include('requests.partials.application-modal')
    @endif
</x-app-layout>
