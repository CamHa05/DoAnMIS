<x-app-layout>
    <x-slot name="title">Gửi yêu cầu học | GiaSu</x-slot>

    @php
        $selectedSubjectId = (string) old('subject_id', $subjects->first()?->subject_id);
        $selectedLevelId = (string) old('subject_level_id', '');
        $selectedMode = old('learning_mode', 'ONLINE');
        $selectedProvinceId = (string) old('province_id', '');
        $selectedFeeType = old('fee_type', 'HOURLY');
        $oldSelections = array_values(array_filter((array) old('schedule_selection', []), 'is_string'));
        $termsAccepted = old('terms_accepted') !== null;
        $levelOptions = $subjects->flatMap(fn ($tutorSubject) => $tutorSubject->tutorSubjectLevels->map(fn ($link) => [
            'subject_id' => $tutorSubject->subject_id,
            'level_id' => $link->subjectLevel?->subject_level_id,
            'label' => $link->subjectLevel?->level_name,
        ]))->filter(fn ($level) => $level['level_id'] && $level['label']);
        $selectedSubjectName = $subjects
            ->first(fn ($tutorSubject) => (string) $tutorSubject->subject_id === $selectedSubjectId)
            ?->subject?->subject_name ?: 'Chưa chọn';
        $hourlyRate = $tutor->hourly_rate !== null
            ? number_format((float) $tutor->hourly_rate, 0, ',', '.') . 'đ / giờ'
            : 'Chưa cập nhật học phí';
        $scheduleError = $errors->first('schedules')
            ?: $errors->first('schedules.*.day_of_week')
            ?: $errors->first('schedules.*.time_slot_id');
    @endphp

    <section class="request-create-page direct-request-page" aria-labelledby="direct-request-title">
        <div class="container direct-request-container">
            <a class="request-create-back" href="{{ route('tutors.show', $tutor) }}">
                <x-directory-icon name="arrow-left" />
                Quay lại hồ sơ gia sư
            </a>

            <header class="request-create-header direct-request-header">
                <p class="eyebrow">Yêu cầu trực tiếp</p>
                <h1 id="direct-request-title">Gửi đề nghị học đến gia sư</h1>
                <p>Chọn nội dung và lịch phù hợp. Gia sư có 24 giờ để xem xét trước khi yêu cầu hết hạn.</p>
            </header>

            <section class="direct-request-recipient" aria-label="Gia sư nhận yêu cầu">
                <div class="direct-request-recipient__avatar">
                    <x-tutor-avatar :user="$tutor->user" size="large" />
                    <span aria-hidden="true"><x-directory-icon name="verified" /></span>
                </div>
                <div class="direct-request-recipient__identity">
                    <span>Gửi đến</span>
                    <h2>{{ $tutor->user?->full_name ?: 'Gia sư' }}</h2>
                    <p>{{ $tutor->headline ?: 'Gia sư trên GiaSu' }}</p>
                </div>
                <dl class="direct-request-recipient__facts">
                    <div>
                        <dt>Học phí hồ sơ</dt>
                        <dd>{{ $hourlyRate }}</dd>
                    </div>
                    <div>
                        <dt>Trạng thái</dt>
                        <dd><x-directory-icon name="circle-check" /> Đã duyệt</dd>
                    </div>
                </dl>
            </section>

            @if ($errors->any())
                <section class="direct-request-alert" role="alert" aria-labelledby="direct-request-error-title">
                    <x-directory-icon name="info" />
                    <div>
                        <strong id="direct-request-error-title">Yêu cầu chưa thể gửi</strong>
                        <p>Vui lòng kiểm tra các trường được đánh dấu bên dưới.</p>
                    </div>
                </section>
            @endif

            <form
                class="direct-request-form"
                method="POST"
                action="{{ route('requests.direct.store', $tutor) }}"
                data-direct-request-form
                data-has-availability="{{ $availableAvailabilities->isNotEmpty() ? 'true' : 'false' }}"
            >
                @csrf
                <input type="hidden" name="expires_at" value="{{ old('expires_at', now()->addDay()->toDateTimeString()) }}">

                <div class="direct-request-form__main">
                    <section class="request-create-section" aria-labelledby="direct-request-subject-title">
                        <header class="request-create-section-heading">
                            <span>01</span>
                            <div>
                                <h2 id="direct-request-subject-title">Môn học và trình độ</h2>
                                <p>Chỉ hiển thị các nội dung gia sư đang nhận dạy.</p>
                            </div>
                        </header>

                        <div class="request-create-field-grid">
                            <label class="request-create-field">
                                <span>Môn học</span>
                                <select
                                    name="subject_id"
                                    required
                                    data-direct-subject
                                    @error('subject_id') aria-invalid="true" aria-describedby="direct-subject-error" @enderror
                                >
                                    <option value="">Chọn môn học</option>
                                    @forelse ($subjects as $tutorSubject)
                                        <option value="{{ $tutorSubject->subject_id }}" @selected($selectedSubjectId === (string) $tutorSubject->subject_id)>
                                            {{ $tutorSubject->subject?->subject_name }}
                                        </option>
                                    @empty
                                        <option value="" disabled>Gia sư chưa cập nhật môn dạy</option>
                                    @endforelse
                                </select>
                                @error('subject_id')
                                    <small class="request-create-error" id="direct-subject-error">{{ $message }}</small>
                                @enderror
                            </label>

                            <label class="request-create-field">
                                <span>Trình độ</span>
                                <select
                                    name="subject_level_id"
                                    required
                                    data-direct-level
                                    @disabled($selectedSubjectId === '')
                                    @error('subject_level_id') aria-invalid="true" aria-describedby="direct-level-error" @enderror
                                >
                                    <option value="">{{ $selectedSubjectId === '' ? 'Chọn môn học trước' : 'Chọn trình độ' }}</option>
                                    @foreach ($levelOptions as $level)
                                        <option
                                            value="{{ $level['level_id'] }}"
                                            data-subject-id="{{ $level['subject_id'] }}"
                                            @selected($selectedLevelId === (string) $level['level_id'])
                                            @if ($selectedSubjectId !== (string) $level['subject_id']) hidden disabled @endif
                                        >
                                            {{ $level['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_level_id')
                                    <small class="request-create-error" id="direct-level-error">{{ $message }}</small>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <section class="request-create-section" aria-labelledby="direct-request-mode-title">
                        <header class="request-create-section-heading">
                            <span>02</span>
                            <div>
                                <h2 id="direct-request-mode-title">Hình thức và học phí</h2>
                                <p>Chọn cách học, sau đó đề xuất mức học phí phù hợp.</p>
                            </div>
                        </header>

                        <fieldset class="request-create-mode-options" @error('learning_mode') aria-invalid="true" aria-describedby="direct-mode-error" @enderror>
                            <legend class="visually-hidden">Hình thức học</legend>
                            <label>
                                <input type="radio" name="learning_mode" value="ONLINE" required @checked($selectedMode === 'ONLINE')>
                                <span>
                                    <x-directory-icon name="mode" />
                                    <strong>Trực tuyến</strong>
                                    <small>Học từ xa qua nền tảng hai bên thống nhất.</small>
                                </span>
                            </label>
                            <label>
                                <input type="radio" name="learning_mode" value="OFFLINE" required @checked($selectedMode === 'OFFLINE')>
                                <span>
                                    <x-directory-icon name="home" />
                                    <strong>Tại nhà</strong>
                                    <small>Gia sư đến địa điểm bạn cung cấp.</small>
                                </span>
                            </label>
                        </fieldset>
                        @error('learning_mode')
                            <small class="request-create-error" id="direct-mode-error">{{ $message }}</small>
                        @enderror

                        <div class="direct-request-location" data-direct-location @if ($selectedMode !== 'OFFLINE') hidden @endif>
                            <div class="direct-request-location__heading">
                                <x-directory-icon name="map-pin" />
                                <div>
                                    <strong>Địa điểm học</strong>
                                    <small>Địa chỉ chi tiết chỉ được dùng để xử lý yêu cầu này.</small>
                                </div>
                            </div>
                            <div class="request-create-field-grid">
                                <label class="request-create-field">
                                    <span>Tỉnh / thành phố</span>
                                    <select
                                        name="province_id"
                                        data-direct-province
                                        required
                                        @disabled($selectedMode !== 'OFFLINE')
                                        @error('province_id') aria-invalid="true" aria-describedby="direct-province-error" @enderror
                                    >
                                        <option value="">Chọn tỉnh / thành phố</option>
                                        @foreach ($provinces as $province)
                                            <option value="{{ $province->province_id }}" @selected($selectedProvinceId === (string) $province->province_id)>
                                                {{ $province->province_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('province_id')
                                        <small class="request-create-error" id="direct-province-error">{{ $message }}</small>
                                    @enderror
                                </label>

                                <label class="request-create-field">
                                    <span>Phường / xã</span>
                                    <select
                                        name="ward_id"
                                        data-direct-ward
                                        required
                                        @disabled($selectedMode !== 'OFFLINE' || $selectedProvinceId === '')
                                        @error('ward_id') aria-invalid="true" aria-describedby="direct-ward-error" @enderror
                                    >
                                        <option value="">{{ $selectedProvinceId === '' ? 'Chọn tỉnh / thành phố trước' : 'Chọn phường / xã' }}</option>
                                        @foreach ($provinces as $province)
                                            @foreach ($province->wards as $ward)
                                                <option
                                                    value="{{ $ward->ward_id }}"
                                                    data-province-id="{{ $province->province_id }}"
                                                    @selected((string) old('ward_id') === (string) $ward->ward_id)
                                                    @if ($selectedMode !== 'OFFLINE' || $selectedProvinceId !== (string) $province->province_id) hidden disabled @endif
                                                >
                                                    {{ $ward->ward_name }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    @error('ward_id')
                                        <small class="request-create-error" id="direct-ward-error">{{ $message }}</small>
                                    @enderror
                                </label>

                                <label class="request-create-field request-create-field--wide">
                                    <span>Địa chỉ chi tiết</span>
                                    <input
                                        name="address_detail"
                                        type="text"
                                        value="{{ old('address_detail') }}"
                                        maxlength="255"
                                        placeholder="Số nhà, tên đường hoặc thông tin nhận biết"
                                        required
                                        @disabled($selectedMode !== 'OFFLINE')
                                        @error('address_detail') aria-invalid="true" aria-describedby="direct-address-error" @enderror
                                    >
                                    @error('address_detail')
                                        <small class="request-create-error" id="direct-address-error">{{ $message }}</small>
                                    @enderror
                                </label>
                            </div>
                        </div>

                        <div class="direct-request-fee-grid">
                            <fieldset class="request-create-choice-field" @error('fee_type') aria-invalid="true" aria-describedby="direct-fee-type-error" @enderror>
                                <legend>Loại học phí</legend>
                                <div class="request-create-inline-options">
                                    <label><input type="radio" name="fee_type" value="HOURLY" required @checked($selectedFeeType === 'HOURLY')><span>Theo giờ</span></label>
                                    <label><input type="radio" name="fee_type" value="MONTHLY" required @checked($selectedFeeType === 'MONTHLY')><span>Theo tháng</span></label>
                                </div>
                                @error('fee_type')
                                    <small class="request-create-error" id="direct-fee-type-error">{{ $message }}</small>
                                @enderror
                            </fieldset>

                            <label class="request-create-field direct-request-fee-field">
                                <span>Học phí đề xuất</span>
                                <span class="request-create-money-input">
                                    <input
                                        type="number"
                                        name="expected_fee"
                                        value="{{ old('expected_fee') }}"
                                        min="1"
                                        max="10000000"
                                        inputmode="numeric"
                                        placeholder="Nhập mức học phí"
                                        required
                                        data-direct-fee
                                        @error('expected_fee') aria-invalid="true" aria-describedby="direct-fee-error" @enderror
                                    >
                                    <small data-direct-fee-suffix>{{ $selectedFeeType === 'MONTHLY' ? 'đ / tháng' : 'đ / giờ' }}</small>
                                </span>
                                @error('expected_fee')
                                    <small class="request-create-error" id="direct-fee-error">{{ $message }}</small>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <section class="request-create-section" aria-labelledby="direct-request-schedule-title">
                        <header class="request-create-section-heading">
                            <span>03</span>
                            <div>
                                <h2 id="direct-request-schedule-title">Lịch học mong muốn</h2>
                                <p>Chọn ít nhất một khung giờ còn trống trong lịch của gia sư.</p>
                            </div>
                        </header>

                        @if ($availableAvailabilities->isNotEmpty())
                            <fieldset class="direct-request-schedule" @if ($scheduleError) aria-invalid="true" aria-describedby="direct-schedule-error" @endif>
                                <legend class="visually-hidden">Các khung giờ còn trống</legend>
                                <div class="direct-request-schedule-grid">
                                    @foreach ($availableAvailabilities as $availability)
                                        @php($key = $availability->day_of_week . ':' . $availability->time_slot_id)
                                        <label>
                                            <input type="checkbox" name="schedule_selection[]" value="{{ $key }}" @checked(in_array($key, $oldSelections, true))>
                                            <span class="direct-request-schedule-card">
                                                <span class="direct-request-schedule-card__check"><x-directory-icon name="check" /></span>
                                                <strong>{{ $dayLabels[$availability->day_of_week] ?? $availability->dayLabel() }}</strong>
                                                <small>{{ substr((string) $availability->timeSlot->start_time, 0, 5) }}–{{ substr((string) $availability->timeSlot->end_time, 0, 5) }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <p class="request-create-note"><x-directory-icon name="info" /> Lịch được kiểm tra lại một lần nữa khi gia sư đồng ý nhận lớp.</p>
                            @if ($scheduleError)
                                <small class="request-create-error" id="direct-schedule-error">{{ $scheduleError }}</small>
                            @endif
                        @else
                            <div class="direct-request-empty-schedule" role="status">
                                <span><x-directory-icon name="calendar" /></span>
                                <div>
                                    <strong>Gia sư chưa có lịch trống</strong>
                                    <p>Bạn chưa thể gửi yêu cầu trực tiếp đến gia sư này ở thời điểm hiện tại.</p>
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="request-create-section" aria-labelledby="direct-request-description-title">
                        <header class="request-create-section-heading">
                            <span>04</span>
                            <div>
                                <h2 id="direct-request-description-title">Mục tiêu học tập</h2>
                                <p>Thông tin cụ thể giúp gia sư phản hồi chính xác hơn.</p>
                            </div>
                        </header>

                        <label class="request-create-field">
                            <span>Nội dung trao đổi ban đầu</span>
                            <textarea
                                name="description"
                                rows="6"
                                maxlength="3000"
                                placeholder="Ví dụ: mục tiêu cần đạt, kiến thức cần củng cố và mong muốn về phương pháp dạy..."
                                required
                                data-direct-description
                                @error('description') aria-invalid="true" aria-describedby="direct-description-error" @enderror
                            >{{ old('description') }}</textarea>
                            <span class="direct-request-character-count" data-direct-description-count>{{ mb_strlen((string) old('description')) }} / 3000</span>
                            @error('description')
                                <small class="request-create-error" id="direct-description-error">{{ $message }}</small>
                            @enderror
                        </label>
                    </section>
                </div>

                <aside class="direct-request-rail" aria-label="Tóm tắt yêu cầu">
                    <div class="direct-request-rail__sticky">
                        <section class="direct-request-summary" aria-labelledby="direct-request-summary-title" aria-live="polite">
                            <header>
                                <span><x-directory-icon name="document" /></span>
                                <div>
                                    <p>Kiểm tra trước khi gửi</p>
                                    <h2 id="direct-request-summary-title">Tóm tắt yêu cầu</h2>
                                </div>
                            </header>
                            <dl>
                                <div><dt>Môn học</dt><dd data-direct-summary-subject>{{ $selectedSubjectName }}</dd></div>
                                <div><dt>Hình thức</dt><dd data-direct-summary-mode>{{ $selectedMode === 'OFFLINE' ? 'Tại nhà' : 'Trực tuyến' }}</dd></div>
                                <div><dt>Lịch đã chọn</dt><dd data-direct-summary-schedule>{{ count($oldSelections) }} khung giờ</dd></div>
                                <div><dt>Học phí</dt><dd data-direct-summary-fee>{{ old('expected_fee') ? number_format((float) old('expected_fee'), 0, ',', '.') . ($selectedFeeType === 'MONTHLY' ? 'đ / tháng' : 'đ / giờ') : 'Chưa nhập' }}</dd></div>
                            </dl>
                            <div class="direct-request-expiry">
                                <x-directory-icon name="clock" />
                                <div><strong>Hiệu lực trong 24 giờ</strong><small>Yêu cầu tự hết hạn nếu gia sư chưa phản hồi.</small></div>
                            </div>
                        </section>

                        <section class="direct-request-terms" aria-labelledby="direct-request-terms-title">
                            <div class="direct-request-terms__heading">
                                <x-directory-icon name="shield" />
                                <div>
                                    <h2 id="direct-request-terms-title">Trước khi gửi</h2>
                                    <p>Luồng tiếp theo chỉ bắt đầu khi gia sư đồng ý.</p>
                                </div>
                            </div>
                            <ul>
                                <li>Hai bên sẽ xác nhận thỏa thuận trước khi lớp học được tạo.</li>
                                <li>Thông tin liên hệ và địa chỉ chi tiết chỉ hiển thị ở giai đoạn phù hợp.</li>
                            </ul>
                            <label class="direct-request-consent">
                                <input
                                    type="checkbox"
                                    name="terms_accepted"
                                    value="1"
                                    required
                                    data-direct-terms
                                    @checked($termsAccepted)
                                    @error('terms_accepted') aria-invalid="true" aria-describedby="direct-terms-error" @enderror
                                >
                                <span>Tôi đã đọc và đồng ý với quy trình nhận lớp.</span>
                            </label>
                            @error('terms_accepted')
                                <small class="request-create-error" id="direct-terms-error">{{ $message }}</small>
                            @enderror
                        </section>

                        @error('expires_at')
                            <small class="request-create-error" id="direct-expiry-error">{{ $message }}</small>
                        @enderror

                        <div class="direct-request-actions">
                            <button
                                class="button request-create-submit direct-request-submit"
                                type="submit"
                                data-direct-submit
                                @disabled($availableAvailabilities->isEmpty() || ! $termsAccepted || count($oldSelections) === 0)
                            >
                                <span data-direct-submit-label>Gửi yêu cầu</span>
                                <x-directory-icon name="send" />
                            </button>
                            <a class="request-create-cancel" href="{{ route('tutors.show', $tutor) }}">Hủy và quay lại hồ sơ</a>
                            <p><x-directory-icon name="shield" /> Dữ liệu được kiểm tra và lưu theo quy trình của GiaSu.</p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</x-app-layout>
