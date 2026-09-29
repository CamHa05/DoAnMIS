@props(['request', 'variant' => 'default'])

@php
    $subjectLevel = $request->subjectLevel;
    $subject = trim((string) $subjectLevel?->subject?->subject_name);
    $level = trim((string) $subjectLevel?->level_name);
    $displayLevel = preg_replace('/^Lớp(?=\s|$)/iu', 'lớp', $level) ?? $level;
    $subjectAndLevel = collect([$subject, $displayLevel])
        ->filter(fn ($value) => filled($value))
        ->implode(' ');
    $learningMode = strtoupper(trim((string) $request->learning_mode));
    $province = $learningMode === 'OFFLINE'
        ? trim((string) $request->ward?->province?->province_name)
        : '';
    $location = $learningMode === 'OFFLINE'
        ? collect([
            trim((string) $request->ward?->ward_name),
            $province,
        ])->filter(fn ($value) => filled($value))->unique()->implode(', ')
        : '';
    $mode = match ($learningMode) {
        'ONLINE' => 'Trực tuyến',
        'OFFLINE' => 'Tại nhà',
        default => trim((string) $request->learning_mode),
    };
    $title = match (true) {
        $learningMode === 'ONLINE' && $subjectAndLevel !== '' => 'Cần gia sư '.$subjectAndLevel.' học Online',
        $learningMode === 'OFFLINE' && $subjectAndLevel !== '' && $province !== '' => 'Cần gia sư '.$subjectAndLevel.' tại '.$province,
        $subjectAndLevel !== '' => 'Cần gia sư '.$subjectAndLevel,
        default => 'Yêu cầu tìm gia sư',
    };

    $orderedSchedules = $request->schedules
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
    $scheduleItems = $orderedSchedules->map(function ($schedule) {
        $timeSlot = $schedule->timeSlot;
        $shortDay = str_replace(['Chủ nhật', 'Thứ '], ['CN', 'T'], $schedule->dayLabel());
        $startTime = substr((string) $timeSlot?->start_time, 0, 5);
        $endTime = substr((string) $timeSlot?->end_time, 0, 5);
        $timeRange = $startTime !== '' && $endTime !== '' ? $startTime.'–'.$endTime : '';

        return [
            'day' => $shortDay,
            'label' => collect([$shortDay, $timeRange])->filter()->implode(' · '),
        ];
    });
    $scheduleCount = $request->schedules->count();
    $scheduleCountLabel = $scheduleCount > 0
        ? $scheduleCount.' buổi/tuần'
        : 'Chưa cập nhật';
    $visibleScheduleItems = $scheduleItems->pluck('label')->filter()->take(2);
    $genderPreference = match (strtoupper(trim((string) $request->preferred_tutor_gender))) {
        'FEMALE' => 'Nữ',
        'MALE' => 'Nam',
        default => 'Không yêu cầu',
    };
    $feeUnit = match (strtoupper(trim((string) $request->fee_type))) {
        'HOURLY' => '/giờ',
        'MONTHLY' => '/tháng',
        default => '',
    };
    $formattedFee = $request->expected_fee !== null
        ? number_format((float) $request->expected_fee, 0, ',', '.').'đ'.$feeUnit
        : null;
    $createdLabel = $request->created_at?->locale('vi')->diffForHumans();
    $remainingSeconds = $request->expires_at
        ? now()->diffInSeconds($request->expires_at, false)
        : null;
    $deadlineLabel = match (true) {
        $remainingSeconds === null => 'Chưa cập nhật',
        $remainingSeconds <= 0 => 'Đã hết hạn',
        $remainingSeconds < 86400 => 'Còn dưới 1 ngày',
        default => 'Còn '.(int) ceil($remainingSeconds / 86400).' ngày',
    };
    $deadlineIsSoon = $remainingSeconds !== null && $remainingSeconds > 0 && $remainingSeconds <= 172800;
@endphp

@if ($variant === 'directory')
    <article class="request-card request-card--directory">
        <header class="request-card-header">
            <div class="request-card-kicker">
                <span @class(['request-card-deadline', 'is-urgent' => $deadlineIsSoon])>
                    <x-directory-icon name="clock" />
                    {{ $deadlineLabel }}
                </span>
                @if ($createdLabel)
                    <span class="request-card-age"><x-directory-icon name="clock" />Đăng {{ $createdLabel }}</span>
                @endif
            </div>

            <h3>{{ $title }}</h3>
        </header>

        <dl class="request-card-summary">
            <div class="request-card-summary-item" data-kind="mode">
                <span class="request-card-summary-icon">
                    <x-directory-icon :name="$learningMode === 'OFFLINE' ? 'home' : 'mode'" />
                </span>
                <div>
                    <dt>Hình thức</dt>
                    <dd>{{ $mode ?: 'Chưa cập nhật' }}</dd>
                </div>
            </div>

            @if ($location)
                <div class="request-card-summary-item" data-kind="location">
                    <span class="request-card-summary-icon"><x-directory-icon name="location" /></span>
                    <div>
                        <dt>Khu vực</dt>
                        <dd>{{ $location }}</dd>
                    </div>
                </div>
            @endif

            <div class="request-card-summary-item" data-kind="gender">
                <span class="request-card-summary-icon"><x-directory-icon name="user" /></span>
                <div>
                    <dt>Ưu tiên gia sư</dt>
                    <dd>{{ $genderPreference }}</dd>
                </div>
            </div>

            <div class="request-card-summary-item" data-kind="schedule">
                <span class="request-card-summary-icon"><x-directory-icon name="calendar" /></span>
                <div>
                    <dt>Lịch học</dt>
                    <dd>{{ $scheduleCountLabel }}</dd>
                </div>
            </div>
        </dl>

        <footer class="request-card-bottom">
            <div class="request-card-price">
                <span class="request-card-price-icon"><x-directory-icon name="wallet" /></span>
                <span>
                    <small>Học phí dự kiến</small>
                    <strong>{{ $formattedFee ?: 'Chưa cập nhật' }}</strong>
                </span>
            </div>
            <a
                class="request-card-detail-cta"
                href="{{ route('requests.show', $request) }}"
                aria-label="Xem chi tiết {{ $title }}"
            >
                Xem chi tiết
                <x-directory-icon name="arrow" />
            </a>
        </footer>
    </article>
@else
    <article class="request-card">
        <div class="request-card-primary">
            <div class="request-card-heading">
                <h3>{{ $title }}</h3>
            </div>
            <div class="request-card-top">
                @if ($createdLabel)
                    <span class="request-age">{{ $createdLabel }}</span>
                @endif
                <span class="request-tag">Đang tìm gia sư</span>
            </div>
        </div>

        @if ($subject || $level)
            <div class="request-card-tags">
                @if ($subject)
                    <span>{{ $subject }}</span>
                @endif
                @if ($level)
                    <span>{{ $level }}</span>
                @endif
            </div>
        @endif

        @if ($mode || $location || $visibleScheduleItems->isNotEmpty() || $genderPreference)
            <div class="request-card-details">
                @if ($mode)
                    <span><x-directory-icon name="mode" />{{ $mode }}</span>
                @endif
                @if ($location)
                    <span><x-directory-icon name="location" />{{ $location }}</span>
                @endif
                @foreach ($visibleScheduleItems as $scheduleItem)
                    <span><x-directory-icon name="calendar" />{{ $scheduleItem }}</span>
                @endforeach
                @if ($genderPreference)
                    <span><x-directory-icon name="user" />{{ $genderPreference }}</span>
                @endif
            </div>
        @endif

        @if (filled($request->description))
            <p class="request-card-description">{{ $request->description }}</p>
        @endif

        @if ($formattedFee)
            <div class="request-card-footer">
                <p class="request-card-fee"><x-directory-icon name="wallet" />{{ $formattedFee }}</p>
            </div>
        @endif
    </article>
@endif
