@php
    $request = $tutoringRequest;
    $subject = $request->subjectLevel?->subject?->subject_name;
    $level = $request->subjectLevel?->level_name;
    $status = strtoupper((string) $request->status);
    $isPending = $status === 'PENDING';
    $areaLabel = collect([
        $request->ward?->ward_name,
        $request->ward?->province?->province_name,
    ])->filter()->join(', ');
    $feeTypeLabels = [
        'HOURLY' => 'giờ',
        'MONTHLY' => 'tháng',
    ];
    $feeType = strtoupper((string) $request->fee_type);
    $feeUnit = $feeTypeLabels[$feeType] ?? 'đơn vị';
    $feeLabel = $request->expected_fee !== null
        ? number_format((float) $request->expected_fee, 0, ',', '.') . ' VNĐ / ' . $feeUnit
        : 'Chưa cập nhật học phí';
    $statusIcon = match ($status) {
        'PENDING', 'EXPIRED' => 'clock',
        'MATCHED' => 'check',
        'REJECTED' => 'x-circle',
        default => 'info',
    };
    $statusMessage = match ($status) {
        'PENDING' => 'Yêu cầu đang chờ bạn kiểm tra và phản hồi.',
        'MATCHED' => 'Bạn đã đồng ý nhận yêu cầu này.',
        'REJECTED' => 'Bạn đã từ chối yêu cầu nhận lớp này.',
        'EXPIRED' => 'Yêu cầu đã hết thời hạn phản hồi.',
        default => 'Yêu cầu hiện không có thao tác tiếp theo.',
    };
@endphp

<x-tutor-account-layout title="Chi tiết yêu cầu nhận lớp | GiaSu">
    <section class="direct-detail-page" aria-labelledby="direct-request-detail-title">
        <a class="direct-detail-back" href="{{ route('tutor-area.direct-requests') }}">
            <x-directory-icon name="arrow-left" /> Quay lại danh sách
        </a>

        <header class="direct-detail-hero">
            <span class="direct-detail-hero__icon" aria-hidden="true"><x-directory-icon name="book-open" /></span>
            <div class="direct-detail-hero__copy">
                <h1 id="direct-request-detail-title">{{ $subject ?: 'Môn học chưa cập nhật' }}</h1>
                <p>{{ $level ?: 'Cấp độ chưa cập nhật' }}</p>
                <span>Yêu cầu học trực tiếp</span>
            </div>
            <span class="direct-status-badge direct-status-badge--large" data-tone="{{ $request->statusTone() }}">
                <x-directory-icon name="{{ $statusIcon }}" />
                {{ $request->statusLabel() }}
            </span>
        </header>

        @if (session('success'))
            <div class="my-requests-alert" role="status"><x-directory-icon name="check" /> {{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="my-requests-alert" role="alert"><x-directory-icon name="info" /> {{ session('error') }}</div>
        @endif

        <div class="direct-detail-layout">
            <div class="direct-detail-main">
                <section class="direct-detail-card" aria-labelledby="learner-information-title">
                    <header class="direct-detail-card__heading">
                        <span aria-hidden="true"><x-directory-icon name="user" /></span>
                        <div>
                            <h2 id="learner-information-title">Thông tin người học</h2>
                            <p>Thông tin cần thiết để xem xét yêu cầu.</p>
                        </div>
                    </header>
                    <div class="direct-detail-learner">
                        <x-tutor-avatar :user="$request->user" />
                        <div>
                            <h3>{{ $request->user?->full_name ?: 'Người học' }}</h3>
                            <p>Người học</p>
                        </div>
                    </div>
                </section>

                <section class="direct-detail-card" aria-labelledby="request-information-title">
                    <header class="direct-detail-card__heading">
                        <span aria-hidden="true"><x-directory-icon name="document" /></span>
                        <div>
                            <h2 id="request-information-title">Thông tin yêu cầu học</h2>
                            <p>Các thông tin chi tiết về môn học, hình thức, học phí và lịch học.</p>
                        </div>
                    </header>

                    <dl class="direct-detail-facts">
                        <div>
                            <dt><x-directory-icon name="book-open" /> Môn học</dt>
                            <dd>{{ $subject ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="education" /> Cấp độ</dt>
                            <dd>{{ $level ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="mode" /> Hình thức</dt>
                            <dd>{{ $request->learningModeLabel() }}</dd>
                        </div>
                        @if (strtoupper((string) $request->learning_mode) === 'OFFLINE')
                            <div>
                                <dt><x-directory-icon name="location" /> Khu vực</dt>
                                <dd>{{ $areaLabel ?: 'Chưa cập nhật' }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt><x-directory-icon name="wallet" /> Học phí</dt>
                            <dd class="direct-detail-number">{{ $feeLabel }}</dd>
                        </div>
                        <div class="direct-detail-facts__schedules">
                            <dt><x-directory-icon name="calendar" /> Lịch học mong muốn</dt>
                            <dd>
                                <ul class="direct-detail-schedule-list">
                                    @forelse ($request->schedules as $schedule)
                                        <li>
                                            <strong>{{ $schedule->dayLabel() }}</strong>
                                            <span>{{ substr((string) $schedule->timeSlot?->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot?->end_time, 0, 5) }}</span>
                                        </li>
                                    @empty
                                        <li><span>Chưa có lịch học</span></li>
                                    @endforelse
                                </ul>
                            </dd>
                        </div>
                    </dl>

                    <section class="direct-detail-description" aria-labelledby="request-description-title">
                        <h3 id="request-description-title"><x-directory-icon name="document" /> Mô tả yêu cầu</h3>
                        <p>{{ $request->description ?: 'Không có mô tả bổ sung.' }}</p>
                    </section>
                </section>
            </div>

            <aside class="direct-detail-side" aria-label="Trạng thái và thao tác yêu cầu">
                <section class="direct-detail-card direct-detail-status-card" aria-labelledby="request-status-title">
                    <header class="direct-detail-card__heading">
                        <span aria-hidden="true"><x-directory-icon name="clock" /></span>
                        <div><h2 id="request-status-title">Trạng thái yêu cầu</h2></div>
                    </header>

                    <span class="direct-status-badge direct-status-badge--large" data-tone="{{ $request->statusTone() }}">
                        <x-directory-icon name="{{ $statusIcon }}" />
                        {{ $request->statusLabel() }}
                    </span>
                    <p class="direct-detail-status-message">{{ $statusMessage }}</p>

                    @if ($request->expires_at)
                        <div class="direct-detail-deadline">
                            <x-directory-icon name="clock" />
                            <div>
                                <span>Hạn phản hồi</span>
                                <strong>{{ $request->expires_at->format('d/m/Y · H:i') }}</strong>
                            </div>
                        </div>
                    @endif
                </section>

                @if ($isPending)
                    <section class="direct-detail-card direct-detail-action-card" aria-labelledby="request-action-title">
                        <header class="direct-detail-card__heading">
                            <span aria-hidden="true"><x-directory-icon name="file-check" /></span>
                            <div>
                                <h2 id="request-action-title">Thao tác tiếp theo</h2>
                                <p>Kiểm tra thông tin và điều khoản trước khi phản hồi.</p>
                            </div>
                        </header>

                        <div class="direct-detail-terms">
                            <h3>Điều khoản nhận lớp</h3>
                            <ul>
                                <li>Kiểm tra kỹ thông tin yêu cầu và lịch học.</li>
                                <li>Sau khi nhận lớp, hai bên xác nhận thỏa thuận.</li>
                                <li>Lớp chỉ được tạo khi cả hai bên đã xác nhận.</li>
                                <li>Hệ thống sẽ kiểm tra lại xung đột lịch.</li>
                            </ul>
                        </div>

                        <form class="direct-detail-accept-form" method="POST" action="{{ route('tutor-area.direct-requests.accept', $request) }}">
                            @csrf
                            @method('PATCH')
                            <label class="direct-detail-consent">
                                <input type="checkbox" name="terms_accepted" value="1" required data-direct-terms>
                                <span>Tôi đã đọc và đồng ý với điều khoản nhận lớp</span>
                            </label>
                            <button class="direct-ui-button direct-ui-button--primary direct-ui-button--wide" type="submit" disabled data-direct-submit>
                                Nhận lớp <x-directory-icon name="check" />
                            </button>
                        </form>

                        <form class="direct-detail-reject-form" method="POST" action="{{ route('tutor-area.direct-requests.reject', $request) }}">
                            @csrf
                            @method('PATCH')
                            <label for="direct-reject-reason">Lý do từ chối <span>(không bắt buộc)</span></label>
                            <textarea
                                id="direct-reject-reason"
                                name="reason"
                                rows="3"
                                maxlength="300"
                                @error('reason') aria-invalid="true" aria-describedby="direct-reject-error" @else aria-describedby="direct-reject-help" @enderror
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <p id="direct-reject-error" class="direct-detail-field-error" role="alert">{{ $message }}</p>
                            @else
                                <small id="direct-reject-help">Tối đa 300 ký tự.</small>
                            @enderror
                            <button class="direct-ui-button direct-ui-button--danger direct-ui-button--wide" type="submit">
                                Từ chối yêu cầu <x-directory-icon name="x-circle" />
                            </button>
                        </form>
                    </section>
                @elseif ($status === 'MATCHED' && $request->contract)
                    <section class="direct-detail-card direct-detail-action-card" aria-labelledby="request-action-title">
                        <header class="direct-detail-card__heading">
                            <span aria-hidden="true"><x-directory-icon name="file-check" /></span>
                            <div>
                                <h2 id="request-action-title">Thao tác tiếp theo</h2>
                                <p>Kiểm tra các thông tin thỏa thuận trước khi xác nhận.</p>
                            </div>
                        </header>
                        <a class="direct-ui-button direct-ui-button--primary direct-ui-button--wide" href="{{ route('contracts.show', $request->contract) }}">
                            Xem &amp; xác nhận thỏa thuận <x-directory-icon name="arrow" />
                        </a>
                    </section>
                @endif
            </aside>
        </div>
    </section>
</x-tutor-account-layout>
