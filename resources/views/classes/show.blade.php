@php
    $request = $contract?->tutoringRequest;
    $subjectLevel = $request?->subjectLevel;
    $learningMode = strtoupper((string) $contract?->learning_mode);
    $isOffline = $learningMode === 'OFFLINE';
    $location = collect([
        $request?->address_detail,
        $request?->ward?->ward_name,
        $request?->ward?->province?->province_name,
    ])->filter()->unique()->implode(', ');
    $contactUser = $isLearner ? $tutor : $learner;
    $dayLabels = [
        1 => 'Thứ Hai',
        2 => 'Thứ Ba',
        3 => 'Thứ Tư',
        4 => 'Thứ Năm',
        5 => 'Thứ Sáu',
        6 => 'Thứ Bảy',
        7 => 'Chủ Nhật',
    ];
@endphp

<x-tutor-account-layout title="Chi tiết lớp học | GiaSu">
    <section class="class-detail-page" aria-labelledby="class-detail-title">
        <a class="class-detail-back" href="{{ route('classes.index') }}">
            <x-directory-icon name="arrow-left" />
            Lớp học của tôi
        </a>

        <header class="class-detail-header">
            <div>
                <p class="eyebrow">Lớp học đã hình thành</p>
                <h1 id="class-detail-title">{{ $tutoringClass->displayName($subjectLevel) }}</h1>
                <p>{{ $subjectLevel?->level_name ?: 'Chưa cập nhật cấp độ' }} · {{ $learningMode === 'ONLINE' ? 'Trực tuyến' : 'Tại nhà' }}</p>
            </div>
            <span class="class-status" data-status="{{ strtoupper((string) $tutoringClass->status) }}">{{ $tutoringClass->status }}</span>
        </header>

        <div class="class-detail-grid">
            <section class="class-detail-card" aria-labelledby="class-schedule-title">
                <header class="class-detail-card-heading">
                    <span><x-directory-icon name="calendar" /></span>
                    <div>
                        <h2 id="class-schedule-title">Lịch học</h2>
                        <p>Lịch học chính thức của lớp.</p>
                    </div>
                </header>
                <dl class="class-detail-facts">
                    <div><dt>Ngày bắt đầu</dt><dd>{{ $tutoringClass->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                    <div><dt>Ngày kết thúc</dt><dd>{{ $tutoringClass->end_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</dd></div>
                    <div class="class-detail-fact-wide"><dt>Khung giờ</dt><dd>
                        @forelse ($tutoringClass->schedules as $schedule)
                            <span>{{ $dayLabels[$schedule->day_of_week] ?? $schedule->day_of_week }} · {{ substr((string) $schedule->timeSlot?->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot?->end_time, 0, 5) }}</span>
                        @empty
                            Chưa có lịch chính thức
                        @endforelse
                    </dd></div>
                </dl>
            </section>

            @if ($isOffline)
                <section class="class-detail-card" aria-labelledby="class-location-title">
                    <header class="class-detail-card-heading">
                        <span><x-directory-icon name="location" /></span>
                        <div>
                            <h2 id="class-location-title">Địa điểm học</h2>
                            <p>Địa chỉ chi tiết sau khi lớp học được xác nhận.</p>
                        </div>
                    </header>
                    <p class="class-detail-location">{{ $location ?: 'Chưa cập nhật địa điểm học.' }}</p>
                    <p class="class-detail-note">Địa chỉ học chi tiết và thông tin liên hệ được hiển thị sau khi lớp học được xác nhận để hai bên thuận tiện trao đổi và sắp xếp buổi học.</p>
                </section>
            @endif

            @if ($isOnline)
                <section class="class-detail-card" aria-labelledby="class-online-title">
                    <header class="class-detail-card-heading">
                        <span><x-directory-icon name="mode" /></span>
                        <div>
                            <h2 id="class-online-title">Học trực tuyến</h2>
                            <p>Hình thức: Trực tuyến</p>
                        </div>
                    </header>
                    <p class="class-detail-note">Hai bên sử dụng thông tin liên hệ cá nhân để trao đổi và thống nhất phương tiện học trực tuyến.</p>
                </section>
            @endif

            <section class="class-detail-card class-detail-contact-card" aria-labelledby="class-contact-title">
                <header class="class-detail-card-heading">
                    <span><x-directory-icon name="user" /></span>
                    <div>
                        <h2 id="class-contact-title">Thông tin liên hệ</h2>
                        <p>Thông tin của {{ $isLearner ? 'gia sư' : 'người học' }} trong lớp học này.</p>
                    </div>
                </header>
                <div class="class-detail-contact">
                    <x-tutor-avatar :user="$contactUser" />
                    <div>
                        <strong>{{ $contactUser?->full_name ?: 'Chưa cập nhật' }}</strong>
                        <a href="mailto:{{ $contactUser?->email }}">{{ $contactUser?->email ?: 'Chưa cập nhật email' }}</a>
                        <a href="tel:{{ $contactUser?->phone }}">{{ $contactUser?->phone ?: 'Chưa cập nhật số điện thoại' }}</a>
                    </div>
                </div>
            </section>
        </div>
    </section>
</x-tutor-account-layout>
