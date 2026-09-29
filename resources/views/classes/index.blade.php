<x-tutor-account-layout title="Lớp học | GiaSu">

    @php
        $dayNames = [
            1 => 'Thứ Hai',
            2 => 'Thứ Ba',
            3 => 'Thứ Tư',
            4 => 'Thứ Năm',
            5 => 'Thứ Sáu',
            6 => 'Thứ Bảy',
            7 => 'Chủ Nhật',
        ];
    @endphp

    <section class="classes-page" aria-labelledby="classes-page-title">
        <div class="container classes-container">
            <header class="classes-page-header">
                <div>
                    <h1 id="classes-page-title">Lớp học của tôi</h1>
                    <p>Theo dõi các lớp đã được hình thành và lịch học chính thức của bạn.</p>
                </div>

                @if ($classes->total() > 0)
                    <span class="classes-total"><strong>{{ $classes->total() }}</strong> lớp học</span>
                @endif
            </header>

            @if ($classes->isNotEmpty())
                <div class="classes-grid">
                    @foreach ($classes as $class)
                        @php
                            $contract = $class->contract;
                            $learningRequest = $contract?->tutoringRequest;
                            $subjectLevel = $learningRequest?->subjectLevel;
                            $subjectName = $subjectLevel?->subject?->subject_name;
                            $levelName = $subjectLevel?->level_name;
                            $educationLevelName = $subjectLevel?->educationLevel?->level_name;
                            $className = $class->displayName($subjectLevel);
                            $levelLabel = collect([$educationLevelName, $levelName])
                                ->filter()
                                ->unique()
                                ->join(' · ');
                            $tutorUser = $contract?->tutorProfile?->user;
                            $learningMode = $contract?->learning_mode;
                            $ward = $learningRequest?->ward;
                        @endphp

                        <a class="class-card" href="{{ route('classes.show', $class) }}">
                            <header class="class-card-header">
                                <div class="class-card-heading">
                                    @if ($subjectName || $levelLabel)
                                        <p class="class-card-subject">
                                            {{ collect([$subjectName, $levelLabel])->filter()->join(' · ') }}
                                        </p>
                                    @endif
                                    <h2>{{ $className }}</h2>
                                </div>

                                <span class="class-status" data-status="{{ strtoupper((string) $class->status) }}">
                                    {{ $class->status }}
                                </span>
                            </header>

                            <div class="class-card-facts">
                                @if ($tutorUser)
                                    <div class="class-card-fact">
                                        <x-directory-icon name="user" />
                                        <span>
                                            <small>Gia sư</small>
                                            <strong>{{ $tutorUser->full_name }}</strong>
                                        </span>
                                    </div>
                                @endif

                                @if ($learningMode)
                                    <div class="class-card-fact">
                                        <x-directory-icon name="mode" />
                                        <span>
                                            <small>Hình thức</small>
                                            <strong>{{ $learningMode === 'ONLINE' ? 'Trực tuyến' : 'Tại nhà' }}</strong>
                                        </span>
                                    </div>
                                @endif

                                @if ($learningMode === 'OFFLINE' && $ward)
                                    <div class="class-card-fact class-card-fact--location">
                                        <x-directory-icon name="location" />
                                        <span>
                                            <small>Khu vực</small>
                                            <strong>{{ $ward->ward_name }}@if ($ward->province), {{ $ward->province->province_name }}@endif</strong>
                                        </span>
                                    </div>
                                @endif

                                @if ($contract?->agreed_fee !== null)
                                    <div class="class-card-fact">
                                        <x-directory-icon name="wallet" />
                                        <span>
                                            <small>Học phí</small>
                                            <strong>{{ number_format((float) $contract->agreed_fee, 0, ',', '.') }}đ / giờ</strong>
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="class-card-schedule">
                                <div class="class-card-schedule-heading">
                                    <x-directory-icon name="calendar" />
                                    <strong>Lịch học chính thức</strong>
                                </div>

                                @if ($class->schedules->isNotEmpty())
                                    <ul>
                                        @foreach ($class->schedules as $schedule)
                                            @php($timeSlot = $schedule->timeSlot)
                                            <li>
                                                <span>{{ $dayNames[$schedule->day_of_week] ?? $schedule->day_of_week }}</span>
                                                @if ($timeSlot)
                                                    <strong>
                                                        {{ substr((string) $timeSlot->start_time, 0, 5) }}–{{ substr((string) $timeSlot->end_time, 0, 5) }}
                                                    </strong>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>Chưa có lịch chính thức</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                @if ($classes->hasPages())
                    <nav class="request-pagination classes-pagination" aria-label="Phân trang lớp học của tôi">
                        {{ $classes->links() }}
                    </nav>
                @endif
            @else
                <div class="classes-empty-state">
                    <span class="classes-empty-icon"><x-directory-icon name="calendar" /></span>
                    <h2>Bạn chưa có lớp học nào</h2>
                    <p>Các lớp đã hình thành sau khi thỏa thuận được xác nhận sẽ xuất hiện tại đây.</p>
                </div>
            @endif
        </div>
    </section>
</x-tutor-account-layout>
