@php
    $contract = $tutoringClass->contract;
    $learningRequest = $contract?->tutoringRequest;
    $learner = $learningRequest?->user;
    $tutorProfile = $contract?->tutorProfile;
    $tutor = $tutorProfile?->user;
    $subjectLevel = $learningRequest?->subjectLevel;
    $subjectName = $subjectLevel?->subject?->subject_name;
    $levelName = $subjectLevel?->level_name ?: $subjectLevel?->educationLevel?->level_name;
    $mode = strtoupper((string) $contract?->learning_mode);
    $classStatus = strtoupper((string) $tutoringClass->status);
    $contractStatus = strtoupper((string) $contract?->status);
    $classCode = '#CLS-'.str_pad((string) $tutoringClass->class_id, 3, '0', STR_PAD_LEFT);
    $requestCode = '#REQ-'.str_pad((string) $learningRequest?->request_id, 3, '0', STR_PAD_LEFT);
    $contractCode = '#C-'.str_pad((string) $contract?->contract_id, 3, '0', STR_PAD_LEFT);
    $className = $tutoringClass->displayName($subjectLevel);
    $statusMeta = [
        'ACTIVE' => [
            'label' => 'Đang hoạt động',
            'class' => 'active',
            'description' => 'Lớp học hiện đang được thực hiện.',
        ],
        'COMPLETED' => [
            'label' => 'Hoàn thành',
            'class' => 'completed',
            'description' => 'Lớp học đã hoàn thành theo trạng thái trên hệ thống.',
        ],
    ];
    $statusDisplay = $statusMeta[$classStatus] ?? [
        'label' => $classStatus !== '' ? $classStatus : 'Chưa xác định',
        'class' => 'neutral',
        'description' => 'Trạng thái hiện tại của lớp học.',
    ];
    $contractStatusDisplay = match ($contractStatus) {
        'CONFIRMED' => ['label' => 'Đã xác nhận', 'class' => 'active'],
        'PENDING' => ['label' => 'Chờ xác nhận', 'class' => 'pending'],
        'EXPIRED' => ['label' => 'Đã hết hạn', 'class' => 'neutral'],
        default => [
            'label' => $contractStatus !== '' ? $contractStatus : 'Chưa xác định',
            'class' => 'neutral',
        ],
    };
    $feeTypeLabel = match (strtoupper((string) $contract?->agreed_fee_type)) {
        'HOURLY' => 'Theo giờ',
        'MONTHLY' => 'Theo tháng',
        default => filled($contract?->agreed_fee_type) ? $contract->agreed_fee_type : 'Chưa cập nhật',
    };
    $feeUnit = match (strtoupper((string) $contract?->agreed_fee_type)) {
        'MONTHLY' => '/ tháng',
        default => '/ giờ',
    };
    $paymentMethodLabel = match (strtoupper((string) $contract?->payment_method)) {
        'BANK_TRANSFER' => 'Chuyển khoản',
        'CASH' => 'Tiền mặt',
        default => filled($contract?->payment_method) ? $contract->payment_method : 'Chưa cập nhật',
    };
    $location = collect([
        $learningRequest?->ward?->ward_name,
        $learningRequest?->ward?->province?->province_name,
    ])->filter()->unique()->implode(', ');
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

<x-admin-layout title="Chi tiết lớp học" active-section="classes">
    <article class="admin-class-detail-page">
        <nav class="admin-breadcrumb admin-class-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <a href="{{ route('admin.classes.index') }}">Lớp học</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">{{ $classCode }}</span>
        </nav>

        <header class="admin-class-detail-heading">
            <a class="admin-class-detail-back" href="{{ route('admin.classes.index') }}">
                <x-directory-icon name="arrow-left" />
                <span>Quay lại danh sách</span>
            </a>
            <div class="admin-class-detail-heading__meta">
                <strong>{{ $classCode }}</strong>
                <span aria-hidden="true">·</span>
                <span>{{ $className }}</span>
                <span aria-hidden="true">·</span>
                <span>Bắt đầu {{ $tutoringClass->start_date?->format('d/m/Y') ?: 'chưa cập nhật' }}</span>
                <span class="admin-class-status admin-class-status--{{ $statusDisplay['class'] }}">
                    <span aria-hidden="true"></span>
                    {{ $statusDisplay['label'] }}
                </span>
            </div>
        </header>

        <div class="admin-class-detail-layout">
            <div class="admin-class-detail-main">
                <section class="admin-class-detail-section" aria-labelledby="admin-class-parties-title">
                    <h2 id="admin-class-parties-title">
                        <x-directory-icon name="document" />
                        <span>Thông tin các bên</span>
                    </h2>
                    <div class="admin-class-parties">
                        <article>
                            <h3>Người học</h3>
                            <div class="admin-class-party">
                                <x-tutor-avatar :user="$learner" />
                                <span>
                                    <strong>{{ $learner?->full_name ?: 'Chưa cập nhật' }}</strong>
                                    <small>{{ $learner?->email ?: 'Chưa cập nhật email' }}</small>
                                </span>
                                @if ($learner && Route::has('admin.users.show'))
                                    <a class="admin-class-action" href="{{ route('admin.users.show', $learner) }}">
                                        <x-directory-icon name="user" />
                                        <span>Xem người dùng</span>
                                    </a>
                                @endif
                            </div>
                        </article>

                        <article>
                            <h3>Gia sư</h3>
                            <div class="admin-class-party">
                                <x-tutor-avatar :user="$tutor" />
                                <span>
                                    <strong>{{ $tutor?->full_name ?: 'Chưa cập nhật' }}</strong>
                                    <small>{{ $tutorProfile?->headline ?: 'Chưa cập nhật giới thiệu' }}</small>
                                    @if ($tutorProfile?->approval_status)
                                        <span class="admin-class-profile-status admin-class-profile-status--{{ strtolower((string) $tutorProfile->approval_status) }}">
                                            {{ strtoupper((string) $tutorProfile->approval_status) === 'APPROVED' ? 'Hồ sơ đã duyệt' : $tutorProfile->approval_status }}
                                        </span>
                                    @endif
                                </span>
                                @if ($tutorProfile && Route::has('admin.tutors.show'))
                                    <a class="admin-class-action" href="{{ route('admin.tutors.show', $tutorProfile) }}">
                                        <x-directory-icon name="user" />
                                        <span>Xem gia sư</span>
                                    </a>
                                @endif
                            </div>
                        </article>
                    </div>
                </section>

                <section class="admin-class-detail-section" aria-labelledby="admin-class-information-title">
                    <h2 id="admin-class-information-title">
                        <x-directory-icon name="file-check" />
                        <span>Thông tin lớp học</span>
                    </h2>
                    <dl class="admin-class-detail-facts">
                        <div>
                            <dt><x-directory-icon name="book-open" /><span>Môn học</span></dt>
                            <dd>{{ $subjectName ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="list" /><span>Cấp độ</span></dt>
                            <dd>{{ $levelName ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="mode" /><span>Hình thức học</span></dt>
                            <dd>
                                <span class="admin-class-mode admin-class-mode--{{ strtolower($mode) }}">
                                    {{ $mode === 'ONLINE' ? 'Online' : ($mode === 'OFFLINE' ? 'Trực tiếp' : 'Chưa xác định') }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="calendar" /><span>Ngày bắt đầu</span></dt>
                            <dd>{{ $tutoringClass->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="calendar" /><span>Ngày kết thúc</span></dt>
                            <dd>{{ $tutoringClass->end_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        @if ($mode === 'OFFLINE')
                            <div>
                                <dt><x-directory-icon name="location" /><span>Khu vực</span></dt>
                                <dd>{{ $location !== '' ? $location : 'Chưa cập nhật' }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt><x-directory-icon name="wallet" /><span>Học phí thỏa thuận</span></dt>
                            <dd>
                                {{ $contract?->agreed_fee !== null ? number_format((float) $contract->agreed_fee, 0, ',', '.').' VNĐ '.$feeUnit : 'Chưa cập nhật' }}
                            </dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="document" /><span>Loại học phí</span></dt>
                            <dd>{{ $feeTypeLabel }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="wallet" /><span>Phương thức thanh toán</span></dt>
                            <dd>{{ $paymentMethodLabel }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-class-detail-section" aria-labelledby="admin-class-schedule-title">
                    <h2 id="admin-class-schedule-title">
                        <x-directory-icon name="calendar" />
                        <span>Lịch học</span>
                    </h2>
                    @if ($tutoringClass->schedules->isNotEmpty())
                        <ul class="admin-class-detail-schedules">
                            @foreach ($tutoringClass->schedules as $schedule)
                                <li>
                                    <strong>{{ $dayLabels[(int) $schedule->day_of_week] ?? 'Thứ '.$schedule->day_of_week }}</strong>
                                    <span>
                                        {{ substr((string) $schedule->timeSlot?->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot?->end_time, 0, 5) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="admin-class-detail-empty">Chưa có lịch học chính thức.</p>
                    @endif
                </section>

                <section class="admin-class-detail-section" aria-labelledby="admin-class-contract-title">
                    <h2 id="admin-class-contract-title">
                        <x-directory-icon name="document" />
                        <span>Hợp đồng liên quan</span>
                    </h2>
                    <dl class="admin-class-contract-facts">
                        <div><dt>Mã hợp đồng</dt><dd>{{ $contract ? $contractCode : 'Chưa cập nhật' }}</dd></div>
                        <div>
                            <dt>Trạng thái hợp đồng</dt>
                            <dd><span class="admin-class-contract-status admin-class-contract-status--{{ $contractStatusDisplay['class'] }}">{{ $contractStatusDisplay['label'] }}</span></dd>
                        </div>
                        <div><dt>Học phí thỏa thuận</dt><dd>{{ $contract?->agreed_fee !== null ? number_format((float) $contract->agreed_fee, 0, ',', '.').' VNĐ '.$feeUnit : 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Ngày bắt đầu</dt><dd>{{ $contract?->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Ngày kết thúc</dt><dd>{{ $contract?->end_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Phương thức thanh toán</dt><dd>{{ $paymentMethodLabel }}</dd></div>
                    </dl>
                </section>
            </div>

            <aside class="admin-class-detail-sidebar" aria-label="Tóm tắt lớp học">
                <section class="admin-class-detail-section admin-class-status-card" aria-labelledby="admin-class-status-title">
                    <h2 id="admin-class-status-title">Trạng thái lớp học</h2>
                    <div class="admin-class-status-summary admin-class-status-summary--{{ $statusDisplay['class'] }}">
                        <span aria-hidden="true"></span>
                        <div>
                            <strong>{{ $statusDisplay['label'] }}</strong>
                            <p>{{ $statusDisplay['description'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="admin-class-detail-section" aria-labelledby="admin-class-summary-title">
                    <h2 id="admin-class-summary-title">Tóm tắt nhanh</h2>
                    <dl class="admin-class-summary">
                        <div><dt>Mã lớp</dt><dd>{{ $classCode }}</dd></div>
                        <div><dt>Tên lớp</dt><dd>{{ $className }}</dd></div>
                        <div><dt>Môn học / Cấp độ</dt><dd>{{ collect([$subjectName, $levelName])->filter()->implode(' · ') ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Hình thức</dt><dd>{{ $mode === 'ONLINE' ? 'Online' : ($mode === 'OFFLINE' ? 'Trực tiếp' : 'Chưa xác định') }}</dd></div>
                        <div><dt>Người học</dt><dd>{{ $learner?->full_name ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Gia sư</dt><dd>{{ $tutor?->full_name ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Số buổi / tuần</dt><dd>{{ $tutoringClass->schedules->count() }} buổi</dd></div>
                        <div><dt>Ngày bắt đầu</dt><dd>{{ $tutoringClass->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Ngày kết thúc</dt><dd>{{ $tutoringClass->end_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                        <div><dt>Học phí thỏa thuận</dt><dd>{{ $contract?->agreed_fee !== null ? number_format((float) $contract->agreed_fee, 0, ',', '.').' VNĐ '.$feeUnit : 'Chưa cập nhật' }}</dd></div>
                    </dl>
                </section>

                <section class="admin-class-detail-section" aria-labelledby="admin-class-links-title">
                    <h2 id="admin-class-links-title">
                        <x-directory-icon name="share" />
                        <span>Liên kết nghiệp vụ</span>
                    </h2>
                    <dl class="admin-class-business-links">
                        <div>
                            <dt>Yêu cầu học</dt>
                            <dd>
                                @if ($learningRequest && Route::has('admin.requests.show'))
                                    <a href="{{ route('admin.requests.show', $learningRequest) }}">{{ $requestCode }}</a>
                                @else
                                    {{ $learningRequest ? $requestCode : 'Chưa cập nhật' }}
                                @endif
                            </dd>
                        </div>
                        <div><dt>Hợp đồng</dt><dd>{{ $contract ? $contractCode : 'Chưa cập nhật' }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </article>
</x-admin-layout>
