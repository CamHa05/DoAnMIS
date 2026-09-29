@props(['request'])

@php
    $subjectName = trim((string) $request->subjectLevel?->subject?->subject_name);
    $levelName = trim((string) $request->subjectLevel?->level_name);
    $title = collect([$subjectName, $levelName])->filter()->join(' · ') ?: 'Môn học chưa cập nhật';
    $requestType = strtoupper((string) $request->request_type);
    $isDirect = $requestType === 'DIRECT';
    $isPublic = $requestType === 'PUBLIC';
    $isOffline = strtoupper((string) $request->learning_mode) === 'OFFLINE';
    $targetUser = $request->targetTutor?->user;
    $areaLabel = collect([
        $request->ward?->ward_name,
        $request->ward?->province?->province_name,
    ])->filter(fn ($value) => filled($value))->unique()->join(', ');
    $addressDetail = trim((string) $request->address_detail);
    $scheduleItems = $request->schedules
        ->map(function ($schedule) {
            $timeSlot = $schedule->timeSlot;

            if (! $timeSlot) {
                return null;
            }

            return $schedule->dayLabel() . ' · '
                . substr((string) $timeSlot->start_time, 0, 5) . '–'
                . substr((string) $timeSlot->end_time, 0, 5);
        })
        ->filter()
        ->unique()
        ->values();
@endphp

<article class="my-request-card">
    <div class="my-request-card-body">
        <header class="my-request-card-header">
            <div class="my-request-card-heading">
                <span class="my-request-type" data-type="{{ strtolower($requestType) }}">
                    {{ $request->typeLabel() }}
                </span>
                <h2>{{ $title }}</h2>
            </div>

            <span class="my-request-status" data-tone="{{ $request->statusTone() }}">
                {{ $request->statusLabel() }}
            </span>
        </header>

        @if (filled($request->description))
            <p class="my-request-description">{{ \Illuminate\Support\Str::limit($request->description, 170) }}</p>
        @endif

        <div class="my-request-facts">
            <div class="my-request-fact">
                <x-directory-icon name="mode" />
                <span>
                    <small>Hình thức</small>
                    <strong>{{ $request->learningModeLabel() }}</strong>
                </span>
            </div>

            @if ($isOffline)
                <div class="my-request-fact">
                    <x-directory-icon name="location" />
                    <span>
                        <small>Khu vực</small>
                        <strong>
                            {{ collect([$addressDetail, $areaLabel])->filter()->join(' · ') ?: 'Chưa cập nhật' }}
                        </strong>
                    </span>
                </div>
            @endif

            <div class="my-request-fact my-request-fact--schedule">
                <x-directory-icon name="calendar" />
                <span>
                    <small>Lịch mong muốn</small>
                    @if ($scheduleItems->isNotEmpty())
                        <strong>{{ $scheduleItems->take(2)->join('; ') }}</strong>
                        @if ($scheduleItems->count() > 2)
                            <em>+{{ $scheduleItems->count() - 2 }} lịch khác</em>
                        @endif
                    @else
                        <strong>Chưa cập nhật</strong>
                    @endif
                </span>
            </div>

            <div class="my-request-fact">
                <x-directory-icon name="wallet" />
                <span>
                    <small>Học phí dự kiến</small>
                    <strong>
                        @if ($request->expected_fee !== null)
                            {{ number_format((float) $request->expected_fee, 0, ',', '.') }}đ / giờ
                        @else
                            Chưa cập nhật
                        @endif
                    </strong>
                </span>
            </div>
        </div>

        @if ($isDirect)
            <div class="my-request-recipient">
                <span>Gửi đến:</span>
                @if ($targetUser)
                    <x-tutor-avatar :user="$targetUser" />
                    <strong>{{ $targetUser->full_name }}</strong>
                @else
                    <strong>Gia sư không còn khả dụng</strong>
                @endif
            </div>
        @elseif ($isPublic)
            <p class="my-request-applications">
                <x-directory-icon name="user" />
                <strong>{{ $request->applications_count }}</strong> gia sư đã ứng tuyển
            </p>
        @endif
    </div>

    <footer class="my-request-card-footer">
        <div class="my-request-dates">
            @if ($request->created_at)
                <span>Tạo ngày <time datetime="{{ $request->created_at->toDateString() }}">{{ $request->created_at->format('d/m/Y') }}</time></span>
            @endif
            @if ($request->expires_at)
                <span>Hết hạn <time datetime="{{ $request->expires_at->toDateTimeString() }}">{{ $request->expires_at->format('d/m/Y, H:i') }}</time></span>
            @endif
        </div>

        <div class="my-request-card-actions">
            @if ($isPublic)
                <a class="my-request-detail-link" href="{{ route('my-requests.applications.index', $request) }}">
                    Xem gia sư ứng tuyển ({{ $request->applications_count }})
                    <x-directory-icon name="arrow" />
                </a>
            @endif
            <a class="my-request-detail-link" href="{{ route('my-requests.show', $request) }}">
                Xem chi tiết
                <x-directory-icon name="arrow" />
            </a>
        </div>
    </footer>
</article>
