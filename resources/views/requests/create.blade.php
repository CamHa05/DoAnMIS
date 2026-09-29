<x-app-layout>
    <x-slot name="title">Tạo yêu cầu học | GiaSu</x-slot>

    <section class="request-create-page" aria-labelledby="request-create-title">
        <div class="container request-create-container">
            <a class="request-create-back" href="{{ route('my-requests.index') }}">
                <x-directory-icon name="arrow" />
                Yêu cầu của tôi
            </a>

            <header class="request-create-header">
                <p class="eyebrow">Yêu cầu công khai</p>
                <h1 id="request-create-title">Tạo yêu cầu học</h1>
                <p>Chia sẻ nhu cầu học để các gia sư phù hợp có thể ứng tuyển.</p>
            </header>

            @php
                $selectedSubjectId = (string) old('subject_id', '');
                $selectedProvinceId = (string) old('province_id', '');
                $selectedMode = old('learning_mode');
                $selectedTutorGender = old('preferred_tutor_gender', 'ANY');
                $selectedFeeType = old('fee_type', 'HOURLY');
                $oldScheduleDays = old('schedule_day', ['']);
                $oldScheduleSlots = old('schedule_time_slot', ['']);
                $normalizeScheduleValues = static fn ($values) => is_array($values)
                    ? array_map(
                        static fn ($value) => is_scalar($value) ? (string) $value : '',
                        array_slice(array_values($values), 0, 20),
                    )
                    : [''];
                $oldScheduleDays = $normalizeScheduleValues($oldScheduleDays);
                $oldScheduleSlots = $normalizeScheduleValues($oldScheduleSlots);
                $scheduleRowCount = max(count($oldScheduleDays), count($oldScheduleSlots), 1);
            @endphp

            <form class="request-create-form" method="POST" action="{{ route('requests.store') }}" data-request-create-form>
                @csrf

                <section class="request-create-section" aria-labelledby="request-create-subject-title">
                    <header class="request-create-section-heading">
                        <span>01</span>
                        <div>
                            <h2 id="request-create-subject-title">Môn học và trình độ</h2>
                            <p>Chọn môn trước để xem các trình độ tương ứng.</p>
                        </div>
                    </header>

                    <div class="request-create-field-grid">
                        <label class="request-create-field">
                            <span>Môn học</span>
                            <select
                                name="subject_id"
                                data-request-create-subject
                                required
                                @error('subject_id') aria-invalid="true" aria-describedby="subject-id-error" @enderror
                            >
                                <option value="">Chọn môn học</option>
                                @forelse ($subjects as $subject)
                                    <option value="{{ $subject->subject_id }}" @selected($selectedSubjectId === (string) $subject->subject_id)>
                                        {{ $subject->subject_name }}
                                    </option>
                                @empty
                                    <option value="" disabled>Chưa có môn học khả dụng</option>
                                @endforelse
                            </select>
                            @error('subject_id')
                                <small class="request-create-error" id="subject-id-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>

                        <label class="request-create-field">
                            <span>Trình độ</span>
                            <select
                                name="subject_level_id"
                                data-request-create-level
                                required
                                @disabled($selectedSubjectId === '')
                                @error('subject_level_id') aria-invalid="true" aria-describedby="subject-level-id-error" @enderror
                            >
                                <option value="">Chọn môn học trước</option>
                                @foreach ($subjects as $subject)
                                    @foreach ($subject->subjectLevels as $subjectLevel)
                                        <option
                                            value="{{ $subjectLevel->subject_level_id }}"
                                            data-subject-id="{{ $subject->subject_id }}"
                                            @selected((string) old('subject_level_id', '') === (string) $subjectLevel->subject_level_id)
                                            @if ($selectedSubjectId !== (string) $subject->subject_id) hidden disabled @endif
                                        >
                                            {{ $subjectLevel->level_name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('subject_level_id')
                                <small class="request-create-error" id="subject-level-id-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>
                    </div>
                </section>

                <section class="request-create-section" aria-labelledby="request-create-mode-title">
                    <header class="request-create-section-heading">
                        <span>02</span>
                        <div>
                            <h2 id="request-create-mode-title">Hình thức học</h2>
                            <p>Chọn cách học phù hợp với nhu cầu của bạn.</p>
                        </div>
                    </header>

                    <fieldset class="request-create-mode-options" @error('learning_mode') aria-invalid="true" aria-describedby="learning-mode-error" @enderror>
                        <legend class="visually-hidden">Hình thức học</legend>
                        <label>
                            <input
                                type="radio"
                                name="learning_mode"
                                value="ONLINE"
                                aria-controls="request-create-location"
                                required
                                @checked($selectedMode === 'ONLINE')
                            >
                            <span>
                                <x-directory-icon name="mode" />
                                <strong>Trực tuyến</strong>
                                <small>Học từ xa qua nền tảng hai bên thống nhất.</small>
                            </span>
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="learning_mode"
                                value="OFFLINE"
                                aria-controls="request-create-location"
                                required
                                @checked($selectedMode === 'OFFLINE')
                            >
                            <span>
                                <x-directory-icon name="home" />
                                <strong>Tại nhà</strong>
                                <small>Gia sư đến dạy tại địa điểm bạn cung cấp.</small>
                            </span>
                        </label>
                    </fieldset>
                    @error('learning_mode')
                        <small class="request-create-error" id="learning-mode-error" role="alert">{{ $message }}</small>
                    @enderror

                    <fieldset
                        class="request-create-choice-field"
                        @error('preferred_tutor_gender') aria-invalid="true" aria-describedby="preferred-tutor-gender-error" @enderror
                    >
                        <legend>Ưu tiên gia sư</legend>
                        <div class="request-create-inline-options request-create-inline-options--three">
                            <label>
                                <input type="radio" name="preferred_tutor_gender" value="ANY" required @checked($selectedTutorGender === 'ANY')>
                                <span>Không yêu cầu</span>
                            </label>
                            <label>
                                <input type="radio" name="preferred_tutor_gender" value="MALE" required @checked($selectedTutorGender === 'MALE')>
                                <span>Nam</span>
                            </label>
                            <label>
                                <input type="radio" name="preferred_tutor_gender" value="FEMALE" required @checked($selectedTutorGender === 'FEMALE')>
                                <span>Nữ</span>
                            </label>
                        </div>
                        @error('preferred_tutor_gender')
                            <small class="request-create-error" id="preferred-tutor-gender-error" role="alert">{{ $message }}</small>
                        @enderror
                    </fieldset>
                </section>

                <section
                    class="request-create-section request-create-location"
                    id="request-create-location"
                    aria-labelledby="request-create-location-title"
                    data-request-create-location
                    @if ($selectedMode !== 'OFFLINE') hidden @endif
                >
                    <header class="request-create-section-heading">
                        <span>03</span>
                        <div>
                            <h2 id="request-create-location-title">Địa điểm học</h2>
                            <p>Địa chỉ chi tiết không được hiển thị trên danh sách yêu cầu công khai.</p>
                        </div>
                    </header>

                    <div class="request-create-field-grid">
                        <label class="request-create-field">
                            <span>Tỉnh / thành phố</span>
                            <select
                                name="province_id"
                                data-request-create-province
                                required
                                @disabled($selectedMode !== 'OFFLINE')
                                @error('province_id') aria-invalid="true" aria-describedby="province-id-error" @enderror
                            >
                                <option value="">Chọn tỉnh / thành phố</option>
                                @forelse ($provinces as $province)
                                    <option value="{{ $province->province_id }}" @selected($selectedProvinceId === (string) $province->province_id)>
                                        {{ $province->province_name }}
                                    </option>
                                @empty
                                    <option value="" disabled>Chưa có khu vực khả dụng</option>
                                @endforelse
                            </select>
                            @error('province_id')
                                <small class="request-create-error" id="province-id-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>

                        <label class="request-create-field">
                            <span>Phường / xã</span>
                            <select
                                name="ward_id"
                                data-request-create-ward
                                required
                                @disabled($selectedMode !== 'OFFLINE' || $selectedProvinceId === '')
                                @error('ward_id') aria-invalid="true" aria-describedby="ward-id-error" @enderror
                            >
                                <option value="">Chọn tỉnh / thành phố trước</option>
                                @foreach ($provinces as $province)
                                    @foreach ($province->wards as $ward)
                                        <option
                                            value="{{ $ward->ward_id }}"
                                            data-province-id="{{ $province->province_id }}"
                                            @selected((string) old('ward_id', '') === (string) $ward->ward_id)
                                            @if ($selectedMode !== 'OFFLINE' || $selectedProvinceId !== (string) $province->province_id) hidden disabled @endif
                                        >
                                            {{ $ward->ward_name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('ward_id')
                                <small class="request-create-error" id="ward-id-error" role="alert">{{ $message }}</small>
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
                                @error('address_detail') aria-invalid="true" aria-describedby="address-detail-error" @enderror
                            >
                            @error('address_detail')
                                <small class="request-create-error" id="address-detail-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>
                    </div>
                </section>

                <section class="request-create-section" aria-labelledby="request-create-schedule-title">
                    <header class="request-create-section-heading">
                        <span>04</span>
                        <div>
                            <h2 id="request-create-schedule-title">Lịch học mong muốn hàng tuần</h2>
                            <p>Mỗi lịch tương ứng với một buổi học cố định trong tuần.</p>
                        </div>
                    </header>

                    <div class="request-create-schedule-list" data-request-create-schedules>
                        @for ($scheduleIndex = 0; $scheduleIndex < $scheduleRowCount; $scheduleIndex++)
                            <div class="request-create-schedule-row" data-request-create-schedule-row>
                                <label class="request-create-field">
                                    <span>Ngày học</span>
                                    <select name="schedule_day[]">
                                        <option value="">Chọn ngày</option>
                                        @foreach ($dayLabels as $dayNumber => $dayLabel)
                                            <option value="{{ $dayNumber }}" @selected((string) ($oldScheduleDays[$scheduleIndex] ?? '') === (string) $dayNumber)>
                                                {{ $dayLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="request-create-field">
                                    <span>Khung giờ</span>
                                    <select name="schedule_time_slot[]" @disabled($timeSlots->isEmpty())>
                                        <option value="">{{ $timeSlots->isEmpty() ? 'Chưa có khung giờ khả dụng' : 'Chọn khung giờ' }}</option>
                                        @foreach ($timeSlots as $timeSlot)
                                            <option
                                                value="{{ $timeSlot->time_slot_id }}"
                                                @selected((string) ($oldScheduleSlots[$scheduleIndex] ?? '') === (string) $timeSlot->time_slot_id)
                                            >
                                                @if (filled($timeSlot->slot_name))
                                                    {{ $timeSlot->slot_name }} ·
                                                @endif
                                                {{ substr((string) $timeSlot->start_time, 0, 5) }}–{{ substr((string) $timeSlot->end_time, 0, 5) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <button class="request-create-remove-schedule" type="button" data-remove-request-schedule @disabled($scheduleRowCount === 1)>Xóa</button>
                            </div>
                        @endfor
                    </div>

                    <button class="request-create-add-schedule" type="button" data-add-request-schedule @disabled($timeSlots->isEmpty())>
                        <x-directory-icon name="plus" />
                        Thêm buổi học
                    </button>

                    @php
                        $scheduleError = $errors->first('schedules')
                            ?: $errors->first('schedules.*.day_of_week')
                            ?: $errors->first('schedules.*.time_slot_id');
                    @endphp
                    @if ($scheduleError)
                        <small class="request-create-error" id="schedules-error" role="alert">{{ $scheduleError }}</small>
                    @endif

                    <template data-request-create-schedule-template>
                        <div class="request-create-schedule-row" data-request-create-schedule-row>
                            <label class="request-create-field">
                                <span>Ngày học</span>
                                <select name="schedule_day[]">
                                    <option value="">Chọn ngày</option>
                                    @foreach ($dayLabels as $dayNumber => $dayLabel)
                                        <option value="{{ $dayNumber }}">{{ $dayLabel }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="request-create-field">
                                <span>Khung giờ</span>
                                <select name="schedule_time_slot[]" @disabled($timeSlots->isEmpty())>
                                    <option value="">{{ $timeSlots->isEmpty() ? 'Chưa có khung giờ khả dụng' : 'Chọn khung giờ' }}</option>
                                    @foreach ($timeSlots as $timeSlot)
                                        <option value="{{ $timeSlot->time_slot_id }}">
                                            @if (filled($timeSlot->slot_name))
                                                {{ $timeSlot->slot_name }} ·
                                            @endif
                                            {{ substr((string) $timeSlot->start_time, 0, 5) }}–{{ substr((string) $timeSlot->end_time, 0, 5) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <button class="request-create-remove-schedule" type="button" data-remove-request-schedule>Xóa</button>
                        </div>
                    </template>
                </section>

                <section class="request-create-section" aria-labelledby="request-create-fee-title">
                    <header class="request-create-section-heading">
                        <span>05</span>
                        <div>
                            <h2 id="request-create-fee-title">Học phí và thời hạn</h2>
                            <p>Nhập mức dự kiến theo loại học phí bạn chọn.</p>
                        </div>
                    </header>

                    <fieldset
                        class="request-create-choice-field request-create-fee-type"
                        @error('fee_type') aria-invalid="true" aria-describedby="fee-type-error" @enderror
                    >
                        <legend>Loại học phí</legend>
                        <div class="request-create-inline-options">
                            <label>
                                <input type="radio" name="fee_type" value="HOURLY" required @checked($selectedFeeType === 'HOURLY')>
                                <span>Theo giờ</span>
                            </label>
                            <label>
                                <input type="radio" name="fee_type" value="MONTHLY" required @checked($selectedFeeType === 'MONTHLY')>
                                <span>Theo tháng</span>
                            </label>
                        </div>
                        @error('fee_type')
                            <small class="request-create-error" id="fee-type-error" role="alert">{{ $message }}</small>
                        @enderror
                    </fieldset>

                    <div class="request-create-field-grid request-create-field-grid--fee">
                        <label class="request-create-field">
                            <span>Học phí dự kiến</span>
                            <span class="request-create-money-input">
                                <input
                                    name="expected_fee"
                                    type="number"
                                    value="{{ old('expected_fee') }}"
                                    min="1"
                                    max="10000000"
                                    inputmode="numeric"
                                    placeholder="Nhập mức học phí"
                                    required
                                    @error('expected_fee') aria-invalid="true" aria-describedby="expected-fee-error" @enderror
                                >
                                <small data-request-create-fee-suffix>{{ $selectedFeeType === 'MONTHLY' ? 'đ / tháng' : 'đ / giờ' }}</small>
                            </span>
                            @error('expected_fee')
                                <small class="request-create-error" id="expected-fee-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>

                        <div
                            class="request-create-expiry"
                            aria-label="Thời hạn yêu cầu"
                            @error('expires_at') aria-invalid="true" aria-describedby="expires-at-error" @enderror
                        >
                            <x-directory-icon name="clock" />
                            <span>
                                <small>Thời hạn</small>
                                <strong>7 ngày</strong>
                                <em>Tính từ thời điểm yêu cầu được đăng.</em>
                            </span>
                            <input name="expires_at" type="hidden" value="{{ old('expires_at', $defaultExpiresAt->toDateTimeString()) }}">
                        </div>
                        @error('expires_at')
                            <small class="request-create-error request-create-error--expiry" id="expires-at-error" role="alert">{{ $message }}</small>
                        @enderror
                    </div>
                </section>

                <section class="request-create-section" aria-labelledby="request-create-description-title">
                    <header class="request-create-section-heading">
                        <span>06</span>
                        <div>
                            <h2 id="request-create-description-title">Mô tả nhu cầu</h2>
                            <p>Chia sẻ mục tiêu học và mong muốn dành cho gia sư.</p>
                        </div>
                    </header>

                    <label class="request-create-field">
                        <span>Nội dung mô tả</span>
                        <textarea
                            name="description"
                            rows="6"
                            maxlength="3000"
                            placeholder="Ví dụ: mục tiêu học tập, kiến thức cần củng cố hoặc mong muốn về phương pháp dạy..."
                            required
                            @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <small class="request-create-error" id="description-error" role="alert">{{ $message }}</small>
                        @enderror
                    </label>
                </section>

                <footer class="request-create-actions">
                    <a class="request-create-cancel" href="{{ route('my-requests.index') }}">Hủy</a>
                    <div>
                        <button class="button request-create-submit" type="submit">Đăng yêu cầu</button>
                    </div>
                </footer>
            </form>
        </div>
    </section>
</x-app-layout>
