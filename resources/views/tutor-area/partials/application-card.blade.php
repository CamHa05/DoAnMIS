@php
    $request = $application->tutoringRequest;
    $subjectLevel = $request?->subjectLevel;
    $status = strtoupper((string) $application->status);
    $statusMeta = match ($status) {
        'ACCEPTED' => ['label' => 'Được chọn', 'tone' => 'accepted', 'message' => 'Người học đã lựa chọn bạn cho yêu cầu này.'],
        'REJECTED' => ['label' => 'Không được lựa chọn', 'tone' => 'rejected', 'message' => 'Người học đã lựa chọn một gia sư khác cho yêu cầu này.'],
        'EXPIRED' => ['label' => 'Đã hết hạn', 'tone' => 'expired', 'message' => 'Yêu cầu học đã hết thời hạn trước khi ứng tuyển của bạn được lựa chọn.'],
        default => ['label' => 'Đang chờ phản hồi', 'tone' => 'pending', 'message' => 'Ứng tuyển của bạn đã được gửi và đang chờ người học phản hồi.'],
    };
@endphp

<article class="tutor-application-card" data-status="{{ $statusMeta['tone'] }}">
    <div class="tutor-application-subject">
        <span class="tutor-application-subject-icon"><x-directory-icon name="book-open" /></span>
        <div>
            <h2>{{ $subjectLevel?->subject?->subject_name ?: 'Môn học' }}</h2>
            <p>{{ $subjectLevel?->level_name ?: 'Chưa xác định cấp độ' }}</p>
            <span class="tutor-application-mode">
                <x-directory-icon name="mode" />
                {{ $request?->learningModeLabel() }}
            </span>
        </div>
    </div>

    <dl class="tutor-application-fact-list">
        <div><dt>Học phí người học mong muốn</dt><dd>{{ $request?->expected_fee !== null ? number_format((float) $request->expected_fee, 0, ',', '.') . ' VNĐ/' . ($request->fee_type ?: 'giờ') : 'Chưa cung cấp' }}</dd></div>
        <div><dt>Học phí bạn đề xuất</dt><dd>{{ $application->proposed_fee !== null ? number_format((float) $application->proposed_fee, 0, ',', '.') . ' VNĐ/' . ($request?->fee_type ?: 'giờ') : 'Theo mức mong muốn' }}</dd></div>
        <div><dt>Lịch học</dt><dd>{{ $request?->schedules?->isNotEmpty() ? $request->schedules->map(fn ($schedule) => 'Thứ ' . $schedule->day_of_week . ', ' . substr((string) $schedule->timeSlot?->start_time, 0, 5) . ' - ' . substr((string) $schedule->timeSlot?->end_time, 0, 5))->join(' · ') : 'Chưa cung cấp' }}</dd></div>
        <div><dt>Ngày ứng tuyển</dt><dd>{{ $application->applied_at?->format('d/m/Y') ?: 'Chưa ghi nhận' }}<small>{{ $application->applied_at?->diffForHumans() }}</small></dd></div>
    </dl>

    <div class="tutor-application-status">
        <span class="tutor-application-status-badge" data-tone="{{ $statusMeta['tone'] }}">{{ $statusMeta['label'] }}</span>
        <p>{{ $statusMeta['message'] }}</p>
        <a class="tutor-application-detail-link" href="{{ route('tutor-area.applications.show', $application) }}">Xem chi tiết <x-directory-icon name="arrow" /></a>
    </div>
</article>