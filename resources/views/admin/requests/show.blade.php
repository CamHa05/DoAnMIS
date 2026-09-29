@php
    $requestCode = '#REQ-'.str_pad((string) $tutoringRequest->request_id, 3, '0', STR_PAD_LEFT);
    $requestType = strtoupper(trim((string) $tutoringRequest->request_type));
    $requestStatus = strtoupper(trim((string) $tutoringRequest->status));
    $learningMode = strtoupper(trim((string) $tutoringRequest->learning_mode));
    $typeMeta = match ($requestType) {
        'PUBLIC' => ['label' => 'Công khai', 'class' => 'public'],
        'DIRECT' => ['label' => 'Gửi trực tiếp', 'class' => 'direct'],
        default => ['label' => 'Chưa xác định', 'class' => 'neutral'],
    };
    $statusMeta = match ($requestStatus) {
        'PENDING' => [
            'label' => 'Chờ phản hồi',
            'class' => 'pending',
            'description' => 'Đang chờ gia sư phản hồi yêu cầu trực tiếp.',
        ],
        'OPEN' => [
            'label' => 'Đang mở',
            'class' => 'open',
            'description' => 'Yêu cầu đang mở để gia sư ứng tuyển.',
        ],
        'MATCHED' => [
            'label' => 'Đã ghép gia sư',
            'class' => 'matched',
            'description' => 'Yêu cầu đã lựa chọn được gia sư.',
        ],
        'EXPIRED' => [
            'label' => 'Đã hết hạn',
            'class' => 'expired',
            'description' => $requestType === 'DIRECT'
                ? 'Yêu cầu trực tiếp đã hết thời hạn phản hồi.'
                : 'Yêu cầu đã hết thời hạn ứng tuyển.',
        ],
        'REJECTED' => [
            'label' => 'Đã từ chối',
            'class' => 'rejected',
            'description' => 'Gia sư đã từ chối yêu cầu trực tiếp.',
        ],
        default => [
            'label' => $requestStatus !== '' ? $requestStatus : 'Chưa xác định',
            'class' => 'neutral',
            'description' => 'Trạng thái yêu cầu chưa có mô tả hiển thị.',
        ],
    };
    $modeMeta = match ($learningMode) {
        'ONLINE' => ['label' => 'Online', 'class' => 'online'],
        'OFFLINE' => ['label' => 'Trực tiếp', 'class' => 'offline'],
        default => ['label' => 'Chưa xác định', 'class' => 'neutral'],
    };
    $feeUnit = match (strtoupper((string) $tutoringRequest->fee_type)) {
        'MONTHLY' => '/ tháng',
        'HOURLY' => '/ giờ',
        default => '',
    };
    $feeLabel = $tutoringRequest->expected_fee !== null
        ? number_format((float) $tutoringRequest->expected_fee, 0, ',', '.').' VNĐ '.$feeUnit
        : 'Chưa cập nhật';
    $subjectName = $tutoringRequest->subjectLevel?->subject?->subject_name ?: 'Chưa cập nhật';
    $levelName = $tutoringRequest->subjectLevel?->level_name
        ?: ($tutoringRequest->subjectLevel?->educationLevel?->level_name ?: 'Chưa cập nhật');
    $location = collect([
        $tutoringRequest->ward?->ward_name,
        $tutoringRequest->ward?->province?->province_name,
    ])->filter()->unique()->implode(', ');
    $createdLabel = $tutoringRequest->created_at?->format('d/m/Y · H:i') ?? 'Chưa cập nhật';
    $deadlineDate = $tutoringRequest->expires_at?->format('d/m/Y');
    $deadlineMeta = match ($requestStatus) {
        'MATCHED' => ['label' => 'Đã kết thúc', 'detail' => null, 'class' => 'finished'],
        'EXPIRED' => ['label' => 'Đã hết hạn', 'detail' => null, 'class' => 'expired'],
        default => null,
    };

    if ($deadlineMeta === null && in_array($requestStatus, ['OPEN', 'PENDING'], true)) {
        if ($tutoringRequest->expires_at === null) {
            $deadlineMeta = ['label' => 'Chưa cập nhật', 'detail' => null, 'class' => 'neutral'];
        } elseif ($tutoringRequest->expires_at->isFuture()) {
            $remainingDays = max(1, (int) ceil(now()->diffInSeconds($tutoringRequest->expires_at, false) / 86400));
            $deadlineMeta = [
                'label' => 'Còn '.$remainingDays.' ngày',
                'detail' => '(đến '.$deadlineDate.')',
                'class' => 'active',
            ];
        } else {
            $deadlineMeta = [
                'label' => 'Đã qua thời hạn',
                'detail' => '(chưa cập nhật trạng thái)',
                'class' => 'neutral',
            ];
        }
    }

    $deadlineMeta ??= [
        'label' => $deadlineDate ?: 'Chưa cập nhật',
        'detail' => null,
        'class' => 'neutral',
    ];

    $learner = $tutoringRequest->user;
    $learnerIsActive = $learner?->isActive() ?? false;
    $selectedTutorId = $selectedTutor?->getKey();
    $contract = $tutoringRequest->contract;
    $relatedClass = $contract?->tutoringClass;
    $businessTutor = $requestStatus === 'MATCHED' ? $selectedTutor : null;
    $showBusinessLinks = $businessTutor !== null || $contract !== null || $relatedClass !== null;
    $directResponseLabel = match ($requestStatus) {
        'PENDING' => 'Đang chờ gia sư phản hồi',
        'MATCHED' => 'Gia sư đã đồng ý nhận lớp',
        'EXPIRED' => 'Yêu cầu đã hết thời hạn phản hồi',
        'REJECTED' => 'Gia sư đã từ chối yêu cầu',
        default => $statusMeta['description'],
    };
@endphp

<x-admin-layout title="Chi tiết yêu cầu học" active-section="requests">
    <article class="admin-request-detail-page">
        <nav class="admin-breadcrumb admin-request-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <a href="{{ route('admin.requests.index') }}">Yêu cầu học</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">{{ $requestCode }}</span>
        </nav>

        <header class="admin-request-detail-heading">
            <a class="admin-request-detail-back" href="{{ route('admin.requests.index') }}">
                <x-directory-icon name="arrow-left" />
                <span>Quay lại danh sách</span>
            </a>
            <div class="admin-request-detail-heading__meta">
                <strong>{{ $requestCode }}</strong>
                <span aria-hidden="true">·</span>
                <time datetime="{{ $tutoringRequest->created_at?->toIso8601String() }}">Tạo lúc {{ $createdLabel }}</time>
                <span class="admin-request-type admin-request-type--{{ $typeMeta['class'] }}">{{ $typeMeta['label'] }}</span>
                <span class="admin-request-status admin-request-status--{{ $statusMeta['class'] }}">
                    <span aria-hidden="true"></span>
                    {{ $statusMeta['label'] }}
                </span>
            </div>
        </header>

        <div class="admin-request-detail-layout">
            <div class="admin-request-detail-main">
                <section class="admin-request-detail-section" aria-labelledby="admin-request-learner-title">
                    <h2 id="admin-request-learner-title">
                        <x-directory-icon name="user" />
                        <span>Thông tin người học</span>
                    </h2>
                    <div class="admin-request-detail-learner">
                        <x-tutor-avatar :user="$learner" />
                        <div class="admin-request-detail-learner__identity">
                            <strong>{{ $learner?->full_name ?: 'Chưa cập nhật' }}</strong>
                            <span><x-directory-icon name="mail" />{{ $learner?->email ?: 'Chưa cập nhật' }}</span>
                            @if ($learner)
                                <small class="admin-request-detail-account admin-request-detail-account--{{ $learnerIsActive ? 'active' : 'disabled' }}">
                                    <span aria-hidden="true"></span>
                                    {{ $learnerIsActive ? 'Tài khoản hoạt động' : 'Tài khoản đã vô hiệu hóa' }}
                                </small>
                            @endif
                        </div>
                        @if ($learner)
                            <a class="admin-request-detail-action" href="{{ route('admin.users.show', $learner) }}">
                                <x-directory-icon name="user" />
                                <span>Xem người dùng</span>
                            </a>
                        @endif
                    </div>
                </section>

                <section class="admin-request-detail-section" aria-labelledby="admin-request-information-title">
                    <h2 id="admin-request-information-title">
                        <x-directory-icon name="request" />
                        <span>Thông tin yêu cầu học</span>
                    </h2>
                    <dl class="admin-request-detail-facts">
                        <div>
                            <dt><x-directory-icon name="book-open" />Môn học</dt>
                            <dd>{{ $subjectName }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="education" />Cấp độ</dt>
                            <dd>{{ $levelName }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="request" />Loại yêu cầu</dt>
                            <dd><span class="admin-request-type admin-request-type--{{ $typeMeta['class'] }}">{{ $typeMeta['label'] }}</span></dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="mode" />Hình thức học</dt>
                            <dd><span class="admin-request-mode admin-request-mode--{{ $modeMeta['class'] }}">{{ $modeMeta['label'] }}</span></dd>
                        </div>
                        @if ($learningMode === 'OFFLINE')
                            <div>
                                <dt><x-directory-icon name="location" />Khu vực</dt>
                                <dd>{{ $location !== '' ? $location : 'Chưa cập nhật' }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt><x-directory-icon name="wallet" />Học phí mong muốn</dt>
                            <dd>{{ $feeLabel }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="calendar" />Ngày tạo</dt>
                            <dd>{{ $createdLabel }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="clock" />Thời hạn</dt>
                            <dd class="admin-request-detail-deadline admin-request-detail-deadline--{{ $deadlineMeta['class'] }}">
                                {{ $deadlineMeta['label'] }}
                                @if ($deadlineMeta['detail'])
                                    <small>{{ $deadlineMeta['detail'] }}</small>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-request-detail-section" aria-labelledby="admin-request-description-title">
                    <h2 id="admin-request-description-title">
                        <x-directory-icon name="document" />
                        <span>Mô tả chi tiết</span>
                    </h2>
                    <p class="admin-request-detail-description">
                        {{ filled($tutoringRequest->description) ? $tutoringRequest->description : 'Người học chưa cung cấp mô tả chi tiết.' }}
                    </p>
                </section>

                <section class="admin-request-detail-section" aria-labelledby="admin-request-schedule-title">
                    <h2 id="admin-request-schedule-title">
                        <x-directory-icon name="calendar-days" />
                        <span>Lịch học mong muốn</span>
                    </h2>
                    @if ($tutoringRequest->schedules->isNotEmpty())
                        <ul class="admin-request-detail-schedules">
                            @foreach ($tutoringRequest->schedules as $schedule)
                                <li>
                                    <strong>{{ $schedule->dayLabel() }}</strong>
                                    @if ($schedule->timeSlot)
                                        <span>{{ substr((string) $schedule->timeSlot->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot->end_time, 0, 5) }}</span>
                                    @else
                                        <span>Chưa cập nhật giờ học</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="admin-request-detail-empty">Người học chưa cung cấp lịch học mong muốn.</p>
                    @endif
                </section>

                @if ($requestType === 'PUBLIC')
                    <section class="admin-request-detail-section admin-request-detail-candidates" aria-labelledby="admin-request-applications-title">
                        <div class="admin-request-detail-section__heading">
                            <h2 id="admin-request-applications-title">
                                <x-directory-icon name="users" />
                                <span>Gia sư ứng tuyển ({{ $tutoringRequest->applications->count() }})</span>
                            </h2>
                            <small>Tổng số ứng tuyển: {{ $tutoringRequest->applications->count() }}</small>
                        </div>

                        @if ($tutoringRequest->applications->isNotEmpty())
                            <div class="admin-request-detail-application-list">
                                @foreach ($tutoringRequest->applications as $application)
                                    @php
                                        $profile = $application->tutorProfile;
                                        $applicationUser = $profile?->user;
                                        $isSelected = $requestStatus === 'MATCHED'
                                            && $selectedTutorId !== null
                                            && (int) $profile?->getKey() === (int) $selectedTutorId;
                                    @endphp
                                    <article class="admin-request-detail-application">
                                        <x-tutor-avatar :user="$applicationUser" />
                                        <div class="admin-request-detail-application__identity">
                                            <strong>{{ $applicationUser?->full_name ?: 'Gia sư chưa cập nhật tên' }}</strong>
                                            <small>{{ $profile?->headline ?: 'Chưa cập nhật tiêu đề hồ sơ' }}</small>
                                            @if ($isSelected)
                                                <span class="admin-request-detail-selected">
                                                    <x-directory-icon name="circle-check" />
                                                    Đã được chọn
                                                </span>
                                            @endif
                                        </div>
                                        <div class="admin-request-detail-application__meta">
                                            <strong>
                                                {{ $application->proposed_fee !== null
                                                    ? number_format((float) $application->proposed_fee, 0, ',', '.').' VNĐ / giờ'
                                                    : 'Không đề xuất học phí riêng' }}
                                            </strong>
                                            <time datetime="{{ $application->applied_at?->toIso8601String() }}">
                                                {{ $application->applied_at?->format('d/m/Y · H:i') ?? 'Chưa cập nhật thời gian' }}
                                            </time>
                                        </div>
                                        @if ($profile)
                                            <a class="admin-request-detail-action" href="{{ route('admin.tutors.show', $profile) }}">
                                                <x-directory-icon name="eye" />
                                                <span>Xem hồ sơ</span>
                                            </a>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="admin-request-detail-empty">Chưa có gia sư ứng tuyển yêu cầu này.</p>
                        @endif
                    </section>
                @elseif ($requestType === 'DIRECT')
                    <section class="admin-request-detail-section" aria-labelledby="admin-request-direct-tutor-title">
                        <h2 id="admin-request-direct-tutor-title">
                            <x-directory-icon name="education" />
                            <span>Gia sư nhận yêu cầu trực tiếp</span>
                        </h2>
                        @if ($directTutor)
                            <div class="admin-request-detail-direct-tutor">
                                <x-tutor-avatar :user="$directTutor->user" />
                                <div>
                                    <strong>{{ $directTutor->user?->full_name ?: 'Chưa cập nhật' }}</strong>
                                    <small>{{ $directTutor->headline ?: 'Chưa cập nhật tiêu đề hồ sơ' }}</small>
                                    <span class="admin-request-detail-direct-state admin-request-detail-direct-state--{{ $statusMeta['class'] }}">
                                        {{ $directResponseLabel }}
                                    </span>
                                </div>
                                <a class="admin-request-detail-action" href="{{ route('admin.tutors.show', $directTutor) }}">
                                    <x-directory-icon name="eye" />
                                    <span>Xem hồ sơ</span>
                                </a>
                            </div>
                        @else
                            <p class="admin-request-detail-empty">Không còn thông tin gia sư nhận yêu cầu trực tiếp.</p>
                        @endif
                    </section>
                @endif
            </div>

            <aside class="admin-request-detail-sidebar" aria-label="Trạng thái và tóm tắt yêu cầu">
                <section class="admin-request-detail-section admin-request-detail-status-card" aria-labelledby="admin-request-current-status-title">
                    <h2 id="admin-request-current-status-title">Trạng thái yêu cầu</h2>
                    <div class="admin-request-detail-status-summary admin-request-detail-status-summary--{{ $statusMeta['class'] }}">
                        <span class="admin-request-detail-status-summary__dot" aria-hidden="true"></span>
                        <div>
                            <strong>{{ $statusMeta['label'] }}</strong>
                            <p>{{ $statusMeta['description'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="admin-request-detail-section" aria-labelledby="admin-request-summary-title">
                    <h2 id="admin-request-summary-title">Tóm tắt nhanh</h2>
                    <dl class="admin-request-detail-summary">
                        <div><dt>Mã yêu cầu</dt><dd>{{ $requestCode }}</dd></div>
                        <div><dt>Loại yêu cầu</dt><dd>{{ $typeMeta['label'] }}</dd></div>
                        <div><dt>Hình thức học</dt><dd>{{ $modeMeta['label'] }}</dd></div>
                        <div><dt>Môn học / Cấp độ</dt><dd>{{ $subjectName }} / {{ $levelName }}</dd></div>
                        <div><dt>Học phí mong muốn</dt><dd>{{ $feeLabel }}</dd></div>
                        <div><dt>Số buổi / tuần</dt><dd>{{ $tutoringRequest->schedules->count() }} buổi</dd></div>
                        @if ($requestType === 'PUBLIC')
                            <div><dt>Số gia sư ứng tuyển</dt><dd>{{ $tutoringRequest->applications->count() }} gia sư</dd></div>
                        @endif
                        <div><dt>Ngày tạo</dt><dd>{{ $createdLabel }}</dd></div>
                        <div>
                            <dt>Thời hạn</dt>
                            <dd class="admin-request-detail-deadline admin-request-detail-deadline--{{ $deadlineMeta['class'] }}">
                                {{ $deadlineMeta['label'] }}
                                @if ($deadlineMeta['detail'])<small>{{ $deadlineMeta['detail'] }}</small>@endif
                            </dd>
                        </div>
                    </dl>
                </section>

                @if ($showBusinessLinks)
                    <section class="admin-request-detail-section" aria-labelledby="admin-request-related-title">
                        <h2 id="admin-request-related-title">Liên kết nghiệp vụ</h2>
                        <div class="admin-request-detail-related">
                            @if ($businessTutor)
                                <a href="{{ route('admin.tutors.show', $businessTutor) }}">
                                    <x-directory-icon name="education" />
                                    <span><small>Gia sư được chọn</small><strong>{{ $businessTutor->user?->full_name ?: 'Xem hồ sơ gia sư' }}</strong></span>
                                    <x-directory-icon name="chevron-right" />
                                </a>
                            @endif
                            @if ($contract)
                                <div>
                                    <x-directory-icon name="document" />
                                    <span><small>Hợp đồng</small><strong>#HĐ-{{ str_pad((string) $contract->contract_id, 3, '0', STR_PAD_LEFT) }}</strong></span>
                                </div>
                            @endif
                            @if ($relatedClass)
                                <div>
                                    <x-directory-icon name="book-open" />
                                    <span><small>Lớp học</small><strong>{{ $relatedClass->displayName($tutoringRequest->subjectLevel) }}</strong></span>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </article>
</x-admin-layout>
