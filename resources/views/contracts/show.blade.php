<x-app-layout>
    @php
        $learner = $tutoringRequest?->user;
        $tutor = $tutorProfile?->user;
        $subjectName = trim((string) $tutoringRequest?->subjectLevel?->subject?->subject_name);
        $levelName = trim((string) $tutoringRequest?->subjectLevel?->level_name);
        $requestLabel = collect([$subjectName, $levelName])->filter()->implode(' · ');
        $status = strtoupper((string) $contract->status);
        $isPending = $status === \App\Models\Contract::STATUS_PENDING;
        $isConfirmed = $status === \App\Models\Contract::STATUS_CONFIRMED;
        $statusLabel = match ($status) {
            \App\Models\Contract::STATUS_PENDING => 'Chờ xác nhận',
            \App\Models\Contract::STATUS_CONFIRMED => 'Đã xác nhận',
            default => $status ?: 'Chưa cập nhật',
        };
        $statusHelper = $isConfirmed
            ? 'Hai bên đã hoàn tất xác nhận hợp đồng.'
            : 'Hợp đồng đang chờ hai bên xác nhận.';
        $learningMode = strtoupper((string) $contract->learning_mode);
        $isOffline = $learningMode === 'OFFLINE';
        $learningModeLabel = match ($learningMode) {
            'ONLINE' => 'Trực tuyến',
            'OFFLINE' => 'Tại nhà',
            default => $contract->learning_mode ?: 'Chưa cập nhật',
        };
        $location = $isOffline
            ? collect([
                trim((string) $tutoringRequest?->ward?->ward_name),
                trim((string) $tutoringRequest?->ward?->province?->province_name),
            ])->filter()->unique()->implode(', ')
            : null;
        $feeType = strtoupper((string) $contract->agreed_fee_type);
        $feeUnit = match ($feeType) {
            'HOURLY' => '/ giờ',
            default => '',
        };
        $feeLabel = number_format((float) $contract->agreed_fee, 0, ',', '.').' VNĐ'.($feeUnit ? ' '.$feeUnit : '');
        $paymentMethod = old('payment_method', $contract->payment_method);
        $startDate = old('start_date', $contract->start_date?->format('Y-m-d'));
        $endDate = old('end_date', $contract->end_date?->format('Y-m-d'));
        $paymentMethodLabel = match ((string) $contract->payment_method) {
            \App\Models\Contract::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
            \App\Models\Contract::PAYMENT_METHOD_CASH => 'Tiền mặt',
            default => 'Chưa được người học xác định',
        };
        $hasPaymentMethod = in_array((string) $contract->payment_method, \App\Models\Contract::PAYMENT_METHODS, true);
        $hasTermsSnapshot = $hasLearnerConfirmed && filled($contract->terms);
        $canEditContractDetails = $isLearner && $isPending && ! $hasCurrentUserConfirmed;
        $canOpenConfirmation = $isPending && ! $hasCurrentUserConfirmed && (
            $isLearner || ($hasLearnerConfirmed && $hasPaymentMethod && $hasTermsSnapshot)
        );
        $termArticles = static fn (?string $terms): array => array_values(array_filter(
            preg_split('/\R{2,}/u', trim((string) $terms)) ?: [],
            static fn (string $article): bool => trim($article) !== ''
        ));
    @endphp

    <x-slot name="title">Hợp đồng #{{ $contract->getKey() }} | GiaSu</x-slot>

    <section class="contract-detail-page" aria-labelledby="contract-detail-title" data-contract-detail>
        <div class="container contract-detail-container">
            @if (session('success'))
                <div class="contract-flash contract-flash--success" role="status">
                    <x-directory-icon name="circle-check" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="contract-flash contract-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->has('payment_method'))
                <div class="contract-flash contract-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ $errors->first('payment_method') }}</span>
                </div>
            @endif

            @if ($errors->has('start_date'))
                <div class="contract-flash contract-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ $errors->first('start_date') }}</span>
                </div>
            @endif

            @if ($errors->has('end_date'))
                <div class="contract-flash contract-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ $errors->first('end_date') }}</span>
                </div>
            @endif

            <nav class="contract-breadcrumb" aria-label="Đường dẫn điều hướng">
                <a href="{{ route('my-requests.index') }}">Yêu cầu của tôi</a>
                <x-directory-icon name="chevron-right" />
                @if ($requestDetailRoute)
                    <a href="{{ $requestDetailRoute }}">Chi tiết yêu cầu</a>
                    <x-directory-icon name="chevron-right" />
                @endif
                <span aria-current="page">Hợp đồng</span>
            </nav>

            <a class="contract-back-link" href="{{ $requestDetailRoute ?: route('home') }}">
                <x-directory-icon name="arrow-left" />
                {{ $requestDetailRoute ? 'Quay lại yêu cầu' : 'Quay lại trang chủ' }}
            </a>

            <div class="contract-layout">
                <main class="contract-main">
                    <header class="contract-heading">
                        <h1 id="contract-detail-title">Chi tiết hợp đồng</h1>
                        <div class="contract-title-row">
                            <strong>Hợp đồng #{{ $contract->getKey() }}</strong>
                            <span class="contract-status-badge" data-status="{{ strtolower($status) }}">
                                <x-directory-icon :name="$isConfirmed ? 'circle-check' : 'clock'" />
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <p>
                            Từ yêu cầu: <strong>{{ $requestLabel ?: 'Yêu cầu học' }}</strong>
                            @if ($requestDetailRoute)
                                <a href="{{ $requestDetailRoute }}">Xem chi tiết yêu cầu <x-directory-icon name="arrow" /></a>
                            @endif
                        </p>
                    </header>

                    <section class="contract-card" aria-labelledby="contract-parties-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="users" />
                            <h2 id="contract-parties-title">Thông tin các bên</h2>
                        </header>
                        <div class="contract-parties">
                            <article class="contract-party">
                                <x-tutor-avatar :user="$learner" />
                                <div>
                                    <span>Người học</span>
                                    <strong>{{ $learner?->full_name ?: 'Người học' }}</strong>
                                    <small>Người học</small>
                                </div>
                            </article>
                            <article class="contract-party">
                                <x-tutor-avatar :user="$tutor" />
                                <div>
                                    <span>Gia sư</span>
                                    <strong>{{ $tutor?->full_name ?: 'Gia sư' }}</strong>
                                    @if (filled($tutorProfile?->headline))
                                        <small>{{ $tutorProfile->headline }}</small>
                                    @endif
                                    @if ($tutorProfile)
                                        <a href="{{ route('tutors.show', $tutorProfile) }}">Xem hồ sơ gia sư <x-directory-icon name="arrow" /></a>
                                    @endif
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="contract-card" aria-labelledby="contract-agreement-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="document" />
                            <h2 id="contract-agreement-title">Thông tin thỏa thuận</h2>
                        </header>

                        <div class="contract-agreement-group">
                            <h3><x-directory-icon name="book-open" />Thông tin học tập</h3>
                            <dl class="contract-facts">
                                <div><dt>Môn học</dt><dd>{{ $subjectName ?: 'Chưa cập nhật' }}</dd></div>
                                <div><dt>Trình độ</dt><dd>{{ $levelName ?: 'Chưa cập nhật' }}</dd></div>
                                <div><dt>Hình thức học</dt><dd>{{ $learningModeLabel }}</dd></div>
                                @if ($isOffline)
                                    <div><dt>Khu vực học</dt><dd>{{ $location ?: 'Chưa cập nhật' }}</dd></div>
                                @endif
                            </dl>
                        </div>

                        <div class="contract-agreement-group">
                            <h3><x-directory-icon name="calendar" />Lịch học theo thỏa thuận</h3>
                            @if ($tutoringRequest?->schedules?->isNotEmpty())
                                <ul class="contract-schedule-list">
                                    @foreach ($tutoringRequest->schedules as $schedule)
                                        @if ($schedule->timeSlot)
                                            <li>
                                                <x-directory-icon name="calendar" />
                                                {{ $schedule->dayLabel() }} ·
                                                {{ substr((string) $schedule->timeSlot->start_time, 0, 5) }}–{{ substr((string) $schedule->timeSlot->end_time, 0, 5) }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                <p class="contract-empty-copy">Chưa có lịch học được ghi nhận.</p>
                            @endif
                        </div>

                        <div class="contract-agreement-group">
                            <h3><x-directory-icon name="wallet" />Học phí và thanh toán</h3>
                            <dl class="contract-facts contract-facts--commercial">
                                <div><dt>Học phí thỏa thuận</dt><dd class="contract-fee">{{ $feeLabel }}</dd></div>
                                <div class="contract-payment-fact">
                                    <dt>Phương thức thanh toán</dt>
                                    <dd>
                                        @if ($canEditContractDetails)
                                            <fieldset class="contract-payment-options" data-contract-payment-options>
                                                <legend class="sr-only">Chọn phương thức thanh toán</legend>
                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        value="{{ \App\Models\Contract::PAYMENT_METHOD_BANK_TRANSFER }}"
                                                        form="contract-confirmation-form"
                                                        @checked($paymentMethod === \App\Models\Contract::PAYMENT_METHOD_BANK_TRANSFER)
                                                    >
                                                    <span>Chuyển khoản</span>
                                                </label>
                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        value="{{ \App\Models\Contract::PAYMENT_METHOD_CASH }}"
                                                        form="contract-confirmation-form"
                                                        @checked($paymentMethod === \App\Models\Contract::PAYMENT_METHOD_CASH)
                                                    >
                                                    <span>Tiền mặt</span>
                                                </label>
                                            </fieldset>
                                            <small class="contract-payment-help" data-contract-payment-help>
                                                Chọn phương thức trước khi xác nhận hợp đồng.
                                            </small>
                                        @else
                                            <span class="contract-readonly-value">{{ $paymentMethodLabel }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="contract-agreement-group">
                            <h3><x-directory-icon name="calendar" />Thời hạn hợp đồng</h3>
                            <dl class="contract-facts contract-facts--commercial">
                                <div class="contract-date-fact">
                                    <dt>Ngày bắt đầu</dt>
                                    <dd>
                                        @if ($canEditContractDetails)
                                            <label class="contract-date-field">
                                                <span class="sr-only">Ngày bắt đầu</span>
                                                <input
                                                    type="date"
                                                    name="start_date"
                                                    value="{{ $startDate }}"
                                                    form="contract-confirmation-form"
                                                    required
                                                    data-contract-start-date
                                                    @if ($errors->has('start_date')) aria-invalid="true" @endif
                                                >
                                            </label>
                                            <small class="contract-date-help" data-contract-start-date-help>
                                                Vui lòng chọn ngày bắt đầu.
                                            </small>
                                        @else
                                            {{ $contract->start_date?->format('d/m/Y') ?: 'Chưa xác định' }}
                                        @endif
                                    </dd>
                                </div>
                                <div class="contract-date-fact">
                                    <dt>Ngày kết thúc</dt>
                                    <dd>
                                        @if ($canEditContractDetails)
                                            <label class="contract-date-field">
                                                <span class="sr-only">Ngày kết thúc</span>
                                                <input
                                                    type="date"
                                                    name="end_date"
                                                    value="{{ $endDate }}"
                                                    form="contract-confirmation-form"
                                                    required
                                                    data-contract-end-date
                                                    @if ($errors->has('end_date')) aria-invalid="true" @endif
                                                >
                                            </label>
                                            <small class="contract-date-help" data-contract-end-date-help>
                                                Vui lòng chọn ngày kết thúc.
                                            </small>
                                        @else
                                            {{ $contract->end_date?->format('d/m/Y') ?: 'Chưa xác định' }}
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="contract-card" aria-labelledby="contract-terms-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="document" />
                            <h2 id="contract-terms-title">Điều khoản hợp đồng</h2>
                        </header>
                        @if ($hasTermsSnapshot)
                            <div class="contract-terms" data-contract-terms-snapshot>
                                @foreach ($termArticles($contract->terms) as $article)
                                    @php
                                        [$articleTitle, $articleBody] = array_pad(explode("\n", $article, 2), 2, '');
                                    @endphp
                                    <article class="contract-term-article">
                                        <h3>{{ $articleTitle }}</h3>
                                        @if ($articleBody !== '')
                                            <div>{{ $articleBody }}</div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @elseif ($shouldShowTermsPreview)
                            <div class="contract-terms-intro">
                                <strong>Bản xem trước điều khoản</strong>
                                <span>Nội dung bên dưới tự động cập nhật theo phương thức thanh toán và thời hạn bạn chọn.</span>
                            </div>
                            <div
                                class="contract-terms"
                                aria-live="polite"
                                data-contract-terms-preview
                            >
                                @foreach ($termArticles($termsPreview) as $article)
                                    @php
                                        [$articleTitle, $articleBody] = array_pad(explode("\n", $article, 2), 2, '');
                                    @endphp
                                    <article class="contract-term-article">
                                        <h3>{{ $articleTitle }}</h3>
                                        @if ($articleBody !== '')
                                            <div>{{ $articleBody }}</div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            <template
                                data-contract-terms-template
                                data-payment-token="{{ \App\Services\ContractTermsGenerator::PREVIEW_PAYMENT_METHOD_TOKEN }}"
                                data-start-date-token="{{ \App\Services\ContractTermsGenerator::PREVIEW_START_DATE_TOKEN }}"
                                data-end-date-token="{{ \App\Services\ContractTermsGenerator::PREVIEW_END_DATE_TOKEN }}"
                                data-payment-placeholder="{{ \App\Services\ContractTermsGenerator::PREVIEW_PAYMENT_METHOD_PLACEHOLDER }}"
                                data-start-date-placeholder="{{ \App\Services\ContractTermsGenerator::PREVIEW_START_DATE_PLACEHOLDER }}"
                                data-end-date-placeholder="{{ \App\Services\ContractTermsGenerator::PREVIEW_END_DATE_PLACEHOLDER }}"
                            >{{ $termsPreviewTemplate }}</template>
                        @else
                            <div class="contract-terms-placeholder">
                                <x-directory-icon name="clock" />
                                <p>Đang chờ người học hoàn tất thông tin và xác nhận hợp đồng.</p>
                            </div>
                        @endif
                    </section>
                </main>

                <aside class="contract-sidebar" aria-label="Trạng thái và xác nhận hợp đồng">
                    <section class="contract-card" aria-labelledby="contract-status-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="info" />
                            <h2 id="contract-status-title">Trạng thái hợp đồng</h2>
                        </header>
                        <div class="contract-status-panel" data-status="{{ strtolower($status) }}">
                            <x-directory-icon :name="$isConfirmed ? 'circle-check' : 'clock'" />
                            <p><strong>{{ $statusLabel }}</strong><span>{{ $statusHelper }}</span></p>
                        </div>
                    </section>

                    <section class="contract-card" aria-labelledby="contract-confirmations-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="users" />
                            <h2 id="contract-confirmations-title">Xác nhận của hai bên</h2>
                        </header>
                        <div class="contract-confirmation-list">
                            @foreach ([
                                ['label' => 'Người học', 'user' => $learner, 'confirmation' => $learnerConfirmation],
                                ['label' => 'Gia sư', 'user' => $tutor, 'confirmation' => $tutorConfirmation],
                            ] as $participant)
                                @php
                                    $participantConfirmed = strtoupper((string) $participant['confirmation']?->confirmation_status)
                                        === \App\Models\ContractConfirmation::STATUS_CONFIRMED;
                                @endphp
                                <article class="contract-confirmation-person">
                                    <x-tutor-avatar :user="$participant['user']" />
                                    <div>
                                        <span>{{ $participant['label'] }}</span>
                                        <strong>{{ $participant['user']?->full_name ?: $participant['label'] }}</strong>
                                        @if ($participantConfirmed && $participant['confirmation']->confirmed_at)
                                            <time datetime="{{ $participant['confirmation']->confirmed_at->toIso8601String() }}">
                                                {{ $participant['confirmation']->confirmed_at->format('d/m/Y H:i') }}
                                            </time>
                                        @endif
                                    </div>
                                    <span class="contract-confirmation-state" data-confirmed="{{ $participantConfirmed ? 'true' : 'false' }}">
                                        <x-directory-icon :name="$participantConfirmed ? 'circle-check' : 'clock'" />
                                        {{ $participantConfirmed ? 'Đã xác nhận' : 'Chưa xác nhận' }}
                                    </span>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <section class="contract-card contract-action-card" aria-labelledby="contract-action-title">
                        <header class="contract-card-heading">
                            <x-directory-icon name="circle-check" />
                            <h2 id="contract-action-title">Hành động của bạn</h2>
                        </header>

                        @if ($isConfirmed)
                            <div class="contract-action-message contract-action-message--success">
                                <x-directory-icon name="circle-check" />
                                <p><strong>Hợp đồng đã được xác nhận.</strong><span>Hai bên đã hoàn tất xác nhận hợp đồng.</span></p>
                            </div>
                            @if ($tutoringClass)
                                <a class="contract-confirm-open contract-class-link" href="{{ route('classes.show', $tutoringClass) }}">
                                    <x-directory-icon name="calendar" />
                                    Xem lớp học
                                    <x-directory-icon name="arrow" />
                                </a>
                            @endif
                        @elseif ($hasCurrentUserConfirmed)
                            <div class="contract-action-message contract-action-message--success">
                                <x-directory-icon name="circle-check" />
                                <p>
                                    <strong>Bạn đã xác nhận hợp đồng.</strong>
                                    @if ($currentConfirmation?->confirmed_at)
                                        <time datetime="{{ $currentConfirmation->confirmed_at->toIso8601String() }}">
                                            {{ $currentConfirmation->confirmed_at->format('d/m/Y H:i') }}
                                        </time>
                                    @endif
                                    <span>Đang chờ bên còn lại xác nhận.</span>
                                </p>
                            </div>
                        @elseif (! $isLearner && ! $hasLearnerConfirmed)
                            <div class="contract-action-message">
                                <x-directory-icon name="clock" />
                                <p><strong>Chưa thể xác nhận</strong><span>Đang chờ người học xác nhận.</span></p>
                            </div>
                        @elseif (! $isLearner && (! $hasPaymentMethod || ! $hasTermsSnapshot))
                            <div class="contract-action-message">
                                <x-directory-icon name="info" />
                                <p><strong>Chưa thể xác nhận</strong><span>Hợp đồng chưa có snapshot điều khoản hoàn chỉnh.</span></p>
                            </div>
                        @else
                            <div class="contract-action-message">
                                <x-directory-icon name="info" />
                                <p><span>Vui lòng kiểm tra kỹ các thông tin và điều khoản trước khi xác nhận.</span></p>
                            </div>
                            <button
                                class="contract-confirm-open"
                                type="button"
                                aria-haspopup="dialog"
                                aria-controls="contract-confirmation-dialog"
                                data-contract-confirm-open
                                @disabled($isLearner && (
                                    ! in_array((string) $paymentMethod, \App\Models\Contract::PAYMENT_METHODS, true)
                                    || blank($startDate)
                                    || blank($endDate)
                                ))
                            >
                                Xác nhận hợp đồng
                            </button>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </section>

    @if ($canOpenConfirmation)
        <dialog
            class="contract-confirm-dialog"
            id="contract-confirmation-dialog"
            aria-labelledby="contract-confirm-dialog-title"
            aria-describedby="contract-confirm-dialog-description"
            data-contract-confirm-dialog
        >
            <div class="contract-confirm-dialog__surface">
                <header>
                    <span><x-directory-icon name="file-check" /></span>
                    <div>
                        <h2 id="contract-confirm-dialog-title">Xác nhận hợp đồng?</h2>
                        <p id="contract-confirm-dialog-description">Bạn xác nhận rằng bạn đã đọc và đồng ý với các thông tin và điều khoản của hợp đồng này.</p>
                    </div>
                    <button type="button" aria-label="Đóng hộp thoại" data-contract-confirm-close><x-directory-icon name="x-circle" /></button>
                </header>
                <footer>
                    <button type="button" data-contract-confirm-close data-contract-confirm-initial>Hủy</button>
                    <form
                        id="contract-confirmation-form"
                        method="POST"
                        action="{{ route('contracts.confirm', $contract) }}"
                        data-contract-confirm-form
                    >
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            data-contract-confirm-submit
                            @disabled($isLearner && (
                                ! in_array((string) $paymentMethod, \App\Models\Contract::PAYMENT_METHODS, true)
                                || blank($startDate)
                                || blank($endDate)
                            ))
                        >
                            <span data-contract-confirm-submit-label>Xác nhận</span>
                        </button>
                    </form>
                </footer>
            </div>
        </dialog>
    @endif
</x-app-layout>
