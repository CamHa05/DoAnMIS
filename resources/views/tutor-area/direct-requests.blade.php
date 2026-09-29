@php
    $tabs = [
        [null, 'Tất cả'],
        ['PENDING', 'Chờ phản hồi'],
        ['MATCHED', 'Đã nhận'],
        ['REJECTED', 'Đã từ chối'],
        ['EXPIRED', 'Hết hạn'],
    ];
    $feeTypeLabels = [
        'HOURLY' => 'giờ',
        'MONTHLY' => 'tháng',
    ];
    $statusIcons = [
        'PENDING' => 'clock',
        'MATCHED' => 'check',
        'REJECTED' => 'x-circle',
        'EXPIRED' => 'clock',
    ];
@endphp

<x-tutor-account-layout title="Yêu cầu nhận lớp | GiaSu">
    <section class="direct-inbox-page" aria-labelledby="direct-requests-title">
        <header class="direct-inbox-heading">
            <div>
                <h1 id="direct-requests-title">Yêu cầu nhận lớp</h1>
                <p>Theo dõi các yêu cầu học được người học gửi trực tiếp đến bạn.</p>
            </div>
            <p class="direct-inbox-total" aria-live="polite">
                <strong>{{ number_format($requests->total(), 0, ',', '.') }}</strong>
                yêu cầu
            </p>
        </header>

        <nav class="direct-inbox-tabs" aria-label="Lọc yêu cầu nhận lớp">
            @foreach ($tabs as [$value, $label])
                @php
                    $isActive = $value === null ? ! $status : $status === $value;
                    $count = $value === null
                        ? $totalRequestCount
                        : (int) ($statusCounts[$value] ?? 0);
                @endphp
                <a
                    @class(['direct-inbox-tab', 'is-active' => $isActive])
                    href="{{ $value === null ? route('tutor-area.direct-requests') : route('tutor-area.direct-requests', ['status' => $value]) }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <span>{{ $label }}</span>
                    <strong>{{ number_format($count, 0, ',', '.') }}</strong>
                </a>
            @endforeach
        </nav>

        @if (session('success'))
            <div class="my-requests-alert" role="status"><x-directory-icon name="check" /> {{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="my-requests-alert" role="alert"><x-directory-icon name="info" /> {{ session('error') }}</div>
        @endif

        @if ($requests->isEmpty())
            <div class="direct-inbox-empty">
                <span><x-directory-icon name="send" /></span>
                <h2>Chưa có yêu cầu nhận lớp</h2>
                <p>Yêu cầu học trực tiếp gửi đến bạn sẽ xuất hiện tại đây.</p>
            </div>
        @else
            <div class="direct-inbox-list">
                @foreach ($requests as $requestItem)
                    @php
                        $requestStatus = strtoupper((string) $requestItem->status);
                        $subject = $requestItem->subjectLevel?->subject?->subject_name;
                        $level = $requestItem->subjectLevel?->level_name;
                        $feeType = strtoupper((string) $requestItem->fee_type);
                        $feeUnit = $feeTypeLabels[$feeType] ?? 'đơn vị';
                        $feeLabel = $requestItem->expected_fee !== null
                            ? number_format((float) $requestItem->expected_fee, 0, ',', '.') . ' VNĐ / ' . $feeUnit
                            : 'Chưa cập nhật học phí';
                    @endphp
                    <article class="direct-inbox-card" data-tone="{{ $requestItem->statusTone() }}">
                        <div class="direct-inbox-subject">
                            <span class="direct-inbox-subject-icon" aria-hidden="true"><x-directory-icon name="book-open" /></span>
                            <div>
                                <h2>{{ $subject ?: 'Môn học chưa cập nhật' }}</h2>
                                <p>{{ $level ?: 'Cấp độ chưa cập nhật' }}</p>
                                <span class="direct-inbox-mode"><x-directory-icon name="mode" /> {{ $requestItem->learningModeLabel() }}</span>
                            </div>
                        </div>

                        <dl class="direct-inbox-facts">
                            <div>
                                <dt><x-directory-icon name="wallet" /> Học phí</dt>
                                <dd>{{ $feeLabel }}</dd>
                            </div>
                            <div>
                                <dt><x-directory-icon name="calendar" /> Lịch mong muốn</dt>
                                <dd class="direct-inbox-schedules">
                                    @forelse ($requestItem->schedules as $schedule)
                                        <span>{{ $schedule->dayLabel() }} · {{ substr((string) $schedule->timeSlot?->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot?->end_time, 0, 5) }}</span>
                                    @empty
                                        <span>Chưa có lịch học</span>
                                    @endforelse
                                </dd>
                            </div>
                        </dl>

                        <div class="direct-inbox-learner">
                            <p>Người học</p>
                            <div>
                                <x-tutor-avatar :user="$requestItem->user" />
                                <span>
                                    <strong>{{ $requestItem->user?->full_name ?: 'Người học' }}</strong>
                                    <small>Gửi ngày {{ $requestItem->created_at?->format('d/m/Y') ?: 'Chưa cập nhật' }}</small>
                                </span>
                            </div>
                        </div>

                        <div class="direct-inbox-outcome">
                            <span class="direct-status-badge" data-tone="{{ $requestItem->statusTone() }}">
                                <x-directory-icon name="{{ $statusIcons[$requestStatus] ?? 'info' }}" />
                                {{ $requestItem->statusLabel() }}
                            </span>
                            <a class="direct-ui-button direct-ui-button--primary" href="{{ route('tutor-area.direct-requests.show', $requestItem) }}">
                                Xem chi tiết <x-directory-icon name="arrow" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($requests->hasPages())
                <nav class="direct-inbox-pagination" aria-label="Phân trang yêu cầu nhận lớp">
                    {{ $requests->links() }}
                </nav>
            @endif
        @endif
    </section>
</x-tutor-account-layout>
