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
    $contract = $request?->contract;
    $tutoringClass = $contract?->tutoringClass;
@endphp

<x-tutor-account-layout title="Chi tiết ứng tuyển | GiaSu">
    <section class="tutor-application-detail-page" aria-labelledby="application-detail-title">
        <nav class="tutor-application-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('tutor-area.applications') }}">Ứng tuyển của tôi</a>
            <span>/</span>
            <strong>Chi tiết ứng tuyển</strong>
        </nav>

        <a class="tutor-application-back" href="{{ route('tutor-area.applications') }}">
            <x-directory-icon name="arrow-left" />
            Quay lại danh sách
        </a>

        <header class="tutor-application-detail-header">
            <span class="tutor-application-detail-icon"><x-directory-icon name="book-open" /></span>
            <div>
                <p>{{ $subjectLevel?->subject?->subject_name ?: 'Môn học' }}</p>
                <h1 id="application-detail-title">{{ $subjectLevel?->level_name ?: 'Chưa xác định cấp độ' }}</h1>
            </div>
            <span class="tutor-application-status-badge" data-tone="{{ $statusMeta['tone'] }}">{{ $statusMeta['label'] }}</span>
            <div class="tutor-application-detail-meta">
                <span><x-directory-icon name="calendar" /> Ngày bạn ứng tuyển: <strong>{{ $application->applied_at?->format('d/m/Y H:i') ?: 'Chưa ghi nhận' }}</strong></span>
                <span>Mã yêu cầu: <strong>#YC{{ str_pad((string) $request?->getKey(), 6, '0', STR_PAD_LEFT) }}</strong></span>
            </div>
        </header>

        <div class="tutor-application-detail-grid">
            <section class="tutor-application-detail-card" aria-labelledby="request-information-title">
                <header class="tutor-detail-card-heading">
                    <span><x-directory-icon name="document" /></span>
                    <div>
                        <h2 id="request-information-title">Thông tin yêu cầu học</h2>
                        <p>Thông tin chi tiết về nhu cầu tìm gia sư của người học.</p>
                    </div>
                </header>
                <dl class="tutor-detail-facts">
                    <div><dt><x-directory-icon name="book-open" /> Môn học</dt><dd>{{ $subjectLevel?->subject?->subject_name ?: 'Chưa cung cấp' }}</dd></div>
                    <div><dt><x-directory-icon name="education" /> Cấp độ</dt><dd>{{ $subjectLevel?->level_name ?: 'Chưa cung cấp' }}</dd></div>
                    <div><dt><x-directory-icon name="mode" /> Hình thức học</dt><dd><span class="tutor-detail-mode">{{ $request?->learningModeLabel() }}</span></dd></div>
                    @if (strtoupper((string) $request?->learning_mode) === 'OFFLINE')
                        <div><dt><x-directory-icon name="location" /> Khu vực</dt><dd>{{ collect([$request?->ward?->ward_name, $request?->ward?->province?->province_name])->filter()->join(', ') ?: 'Chưa cung cấp' }}</dd></div>
                    @endif
                    <div><dt><x-directory-icon name="wallet" /> Học phí mong muốn</dt><dd>{{ $request?->expected_fee !== null ? number_format((float) $request->expected_fee, 0, ',', '.') . ' VNĐ/' . ($request->fee_type ?: 'giờ') : 'Chưa cung cấp' }}</dd></div>
                    <div><dt><x-directory-icon name="calendar" /> Lịch học</dt><dd>{{ $request?->schedules?->isNotEmpty() ? $request->schedules->map(fn ($schedule) => $schedule->dayLabel() . ', ' . substr((string) $schedule->timeSlot?->start_time, 0, 5) . ' - ' . substr((string) $schedule->timeSlot?->end_time, 0, 5))->join(' · ') : 'Chưa cung cấp' }}</dd></div>
                    <div class="tutor-detail-fact-description"><dt><x-directory-icon name="document" /> Mô tả</dt><dd>{{ $request?->description ?: 'Người học chưa cung cấp mô tả.' }}</dd></div>
                </dl>
            </section>

            <div class="tutor-application-detail-side">
                <section class="tutor-application-detail-card" aria-labelledby="application-status-title">
                    <header class="tutor-detail-card-heading">
                        <span><x-directory-icon name="circle-check" /></span>
                        <h2 id="application-status-title">Trạng thái ứng tuyển</h2>
                    </header>
                    <div class="tutor-detail-status-panel" data-tone="{{ $statusMeta['tone'] }}">
                        <span class="tutor-application-status-badge" data-tone="{{ $statusMeta['tone'] }}">{{ $statusMeta['label'] }}</span>
                        <p>{{ $statusMeta['message'] }}</p>
                        @if ($status === 'ACCEPTED' && $contract?->status === \App\Models\Contract::STATUS_PENDING)
                            <p>Vui lòng kiểm tra thỏa thuận trước khi xác nhận nhận lớp.</p>
                        @elseif ($status === 'ACCEPTED' && $contract?->status === \App\Models\Contract::STATUS_CONFIRMED && $tutoringClass)
                            <p>Hai bên đã xác nhận thỏa thuận và lớp học đã được tạo.</p>
                        @endif
                    </div>
                    @if ($status === 'ACCEPTED' && $contract?->status === \App\Models\Contract::STATUS_PENDING)
                        <a class="tutor-detail-action" href="{{ route('contracts.show', $contract) }}">Xem &amp; xác nhận thỏa thuận <x-directory-icon name="arrow" /></a>
                    @elseif ($status === 'ACCEPTED' && $contract?->status === \App\Models\Contract::STATUS_CONFIRMED && $tutoringClass)
                        <a class="tutor-detail-action" href="{{ route('classes.index') }}">Xem lớp học <x-directory-icon name="arrow" /></a>
                    @endif
                </section>

                <section class="tutor-application-detail-card" aria-labelledby="your-application-title">
                    <header class="tutor-detail-card-heading">
                        <span><x-directory-icon name="user" /></span>
                        <div>
                            <h2 id="your-application-title">Ứng tuyển của bạn</h2>
                            <p>Thông tin bạn đã gửi cho người học.</p>
                        </div>
                    </header>
                    <dl class="tutor-detail-facts tutor-detail-facts-compact">
                        <div><dt><x-directory-icon name="wallet" /> Học phí đề xuất</dt><dd>{{ $application->proposed_fee !== null ? number_format((float) $application->proposed_fee, 0, ',', '.') . ' VNĐ/' . ($request?->fee_type ?: 'giờ') : 'Theo mức mong muốn' }}</dd></div>
                        <div><dt><x-directory-icon name="support" /> Lời nhắn</dt><dd>{{ $application->message ?: 'Bạn chưa gửi lời nhắn.' }}</dd></div>
                        <div><dt><x-directory-icon name="calendar" /> Thời gian ứng tuyển</dt><dd>{{ $application->applied_at?->format('d/m/Y H:i') ?: 'Chưa ghi nhận' }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>

        @if ($status === 'ACCEPTED' && $contract)
            <section class="tutor-agreement-card" aria-labelledby="agreement-title">
                <header class="tutor-detail-card-heading">
                    <span><x-directory-icon name="file-check" /></span>
                    <div>
                        <h2 id="agreement-title">Thỏa thuận học</h2>
                        <p>Thông tin thỏa thuận giữa bạn và người học.</p>
                    </div>
                </header>
                <dl class="tutor-agreement-facts">
                    <div><dt>Học phí thống nhất</dt><dd>{{ number_format((float) $contract->agreed_fee, 0, ',', '.') }} VNĐ/{{ $contract->agreed_fee_type ?: 'giờ' }}</dd></div>
                    <div><dt>Phương thức thanh toán</dt><dd>{{ $contract->payment_method ?: 'Chưa cung cấp' }}</dd></div>
                    <div><dt>Ngày bắt đầu</dt><dd>{{ $contract->start_date?->format('d/m/Y') ?: 'Chưa cung cấp' }}</dd></div>
                    <div><dt>Ngày kết thúc</dt><dd>{{ $contract->end_date?->format('d/m/Y') ?: 'Chưa cung cấp' }}</dd></div>
                </dl>
                @if ($contract->status === \App\Models\Contract::STATUS_PENDING)
                    <a class="tutor-detail-action" href="{{ route('contracts.show', $contract) }}">Xem &amp; xác nhận thỏa thuận <x-directory-icon name="arrow" /></a>
                @endif
            </section>
        @endif
    </section>
</x-tutor-account-layout>