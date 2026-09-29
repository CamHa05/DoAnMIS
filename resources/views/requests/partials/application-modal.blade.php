@php
    $applicationErrors = $errors->getBag('tutorApplication');
    $feeChoice = old('fee_option', 'expected');
    $feeChoice = in_array($feeChoice, ['expected', 'custom'], true) ? $feeChoice : 'expected';
    $isCustomFee = $feeChoice === 'custom';
    $modalId = 'request-application-dialog-'.$tutoringRequest->getKey();
@endphp

<dialog
    id="{{ $modalId }}"
    class="request-application-dialog"
    aria-labelledby="{{ $modalId }}-title"
    aria-describedby="{{ $modalId }}-subtitle"
    data-request-application-dialog
    data-auto-open="{{ $applicationErrors->any() ? 'true' : 'false' }}"
>
    <div class="request-application-dialog__surface">
        <header class="request-application-dialog__header">
            <span class="request-application-dialog__mark" aria-hidden="true">
                <x-directory-icon name="send" />
            </span>
            <div>
                <h2 id="{{ $modalId }}-title">Ứng tuyển yêu cầu học</h2>
                <p id="{{ $modalId }}-subtitle">Gửi đề xuất của bạn đến người học.</p>
            </div>
            <button
                class="request-application-dialog__close"
                type="button"
                aria-label="Đóng hộp thoại ứng tuyển"
                data-request-application-close
            >
                <x-directory-icon name="x-circle" />
            </button>
        </header>

        <form
            method="POST"
            action="{{ route('requests.applications.store', $tutoringRequest) }}"
            data-request-application-form
        >
            @csrf

            <section class="request-application-summary" aria-label="Tóm tắt yêu cầu học">
                <span class="request-application-summary__icon" aria-hidden="true">
                    <x-directory-icon name="document" />
                </span>
                <div>
                    <h3>{{ $title }}</h3>
                    <p>
                        <span><x-directory-icon name="book-open" />{{ collect([$subject, $level])->filter()->implode(' · ') ?: 'Chưa cập nhật' }}</span>
                        <span><x-directory-icon :name="$isOffline ? 'home' : 'mode'" />{{ $tutoringRequest->learningModeLabel() }}</span>
                        <strong><x-directory-icon name="wallet" />Học phí dự kiến: {{ $feeLabel }}</strong>
                    </p>
                </div>
            </section>

            <fieldset class="request-application-fee">
                <legend>Mức học phí</legend>

                <label class="request-application-choice">
                    <input
                        type="radio"
                        name="fee_option"
                        value="expected"
                        @checked($feeChoice === 'expected')
                        data-request-fee-choice
                        @if ($feeChoice === 'expected') data-request-application-initial @endif
                    >
                    <span>
                        <strong>Đồng ý với học phí dự kiến</strong>
                        <b>{{ $feeLabel }}</b>
                    </span>
                </label>

                <label class="request-application-choice">
                    <input
                        type="radio"
                        name="fee_option"
                        value="custom"
                        @checked($isCustomFee)
                        data-request-fee-choice
                        @if ($isCustomFee) data-request-application-initial @endif
                    >
                    <span>
                        <strong>Đề xuất mức khác</strong>
                        <small>Nhập mức học phí bạn muốn đề xuất cho người học.</small>
                    </span>
                </label>

                <div class="request-application-fee__custom @if ($applicationErrors->has('proposed_fee')) has-error @endif" data-request-custom-fee @if (! $isCustomFee) hidden @endif>
                    <label for="{{ $modalId }}-proposed-fee">Học phí đề xuất</label>
                    <div class="request-application-money-input">
                        <input
                            id="{{ $modalId }}-proposed-fee"
                            name="proposed_fee"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            value="{{ old('proposed_fee') }}"
                            aria-describedby="{{ $modalId }}-proposed-fee-error"
                            aria-invalid="{{ $applicationErrors->has('proposed_fee') ? 'true' : 'false' }}"
                            @disabled(! $isCustomFee)
                            data-request-proposed-fee
                        >
                        <span>VNĐ{{ $feeUnit }}</span>
                    </div>
                    <p
                        id="{{ $modalId }}-proposed-fee-error"
                        class="request-application-error"
                        role="alert"
                        @if (! $applicationErrors->has('proposed_fee')) hidden @endif
                        data-request-proposed-fee-error
                    >{{ $applicationErrors->first('proposed_fee') }}</p>
                </div>

                @if ($applicationErrors->has('fee_option'))
                    <p class="request-application-error" role="alert">{{ $applicationErrors->first('fee_option') }}</p>
                @endif
            </fieldset>

            <div class="request-application-message @if ($applicationErrors->has('message')) has-error @endif">
                <label for="{{ $modalId }}-message">
                    Lời nhắn đến người học <span aria-hidden="true">*</span>
                </label>
                <textarea
                    id="{{ $modalId }}-message"
                    name="message"
                    maxlength="500"
                    required
                    aria-required="true"
                    aria-invalid="{{ $applicationErrors->has('message') ? 'true' : 'false' }}"
                    aria-describedby="{{ $modalId }}-message-error {{ $modalId }}-message-count"
                    placeholder="Giới thiệu ngắn về kinh nghiệm và lý do bạn phù hợp với yêu cầu này…"
                    data-request-application-message
                >{{ old('message') }}</textarea>
                <div class="request-application-message__meta">
                    <p
                        id="{{ $modalId }}-message-error"
                        class="request-application-error"
                        role="alert"
                        @if (! $applicationErrors->has('message')) hidden @endif
                        data-request-message-error
                    >{{ $applicationErrors->first('message') }}</p>
                    <span id="{{ $modalId }}-message-count" data-request-message-count>0 / 500</span>
                </div>
            </div>

            <div class="request-application-info">
                <span aria-hidden="true"><x-directory-icon name="shield" /></span>
                <p>
                    <strong>Người học sẽ xem hồ sơ gia sư của bạn cùng với đề xuất này.</strong>
                    <span>Hãy đảm bảo thông tin hồ sơ của bạn đã được cập nhật đầy đủ.</span>
                </p>
            </div>

            <footer class="request-application-dialog__actions">
                <button class="request-application-button request-application-button--secondary" type="button" data-request-application-close>
                    Hủy
                </button>
                <button class="request-application-button request-application-button--primary" type="submit" data-request-application-submit>
                    <x-directory-icon name="send" />
                    <span data-request-application-submit-label>Gửi ứng tuyển</span>
                </button>
            </footer>
        </form>
    </div>
</dialog>
