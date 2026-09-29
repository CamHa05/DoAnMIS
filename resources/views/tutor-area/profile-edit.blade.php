@php
    $selectedSubjectIds = collect(old('subject_ids', array_keys($selectedSpecializations)))
        ->filter(fn ($id) => is_numeric($id))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedLevelMap = collect(old('subject_levels', $selectedSpecializations))
        ->filter(fn ($levelIds, $subjectId) => is_numeric($subjectId) && is_array($levelIds))
        ->mapWithKeys(fn ($levelIds, $subjectId) => [
            (int) $subjectId => collect($levelIds)
                ->filter(fn ($levelId) => is_numeric($levelId))
                ->map(fn ($levelId) => (int) $levelId)
                ->unique()
                ->values()
                ->all(),
        ])
        ->all();
    $selectedSpecialtyItems = collect($specialtyCatalog)
        ->filter(fn ($subject) => in_array($subject['id'], $selectedSubjectIds, true))
        ->map(function ($subject) use ($selectedLevelMap) {
            $selectedLevelIds = $selectedLevelMap[$subject['id']] ?? [];

            return [
                ...$subject,
                'levels' => collect($subject['levels'])
                    ->filter(fn ($level) => in_array($level['id'], $selectedLevelIds, true))
                    ->values()
                    ->all(),
            ];
        })
        ->values();
    $selectedAreaIds = collect(old('ward_ids', $selectedWardIds))
        ->filter(fn ($id) => is_numeric($id))
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();
    $selectedAreaItems = collect($teachingAreaCatalog)
        ->flatMap(function ($province) use ($selectedAreaIds) {
            return collect($province['wards'])
                ->filter(fn ($ward) => in_array($ward['id'], $selectedAreaIds, true))
                ->map(fn ($ward) => [
                    ...$ward,
                    'province_id' => $province['id'],
                    'province_name' => $province['name'],
                ]);
        })
        ->values();
    $supportsOffline = in_array(
        old('supports_offline', $profile->supports_offline),
        [true, 1, '1', 'true', 'on', 'yes'],
        true
    );
@endphp

<x-tutor-account-layout title="Chỉnh sửa hồ sơ gia sư | GiaSu">
    <section class="tutor-profile-editor" aria-labelledby="tutor-profile-editor-title">
        <form id="tutor-profile-main-form" method="POST" action="{{ route('tutor-area.profile.update') }}">
            @csrf
            @method('PUT')
        </form>

        <header class="tutor-profile-editor__header">
            <div>
                <a class="tutor-profile-editor__back" href="{{ route('tutor-area.profile') }}">
                    <x-directory-icon name="arrow-left" />
                    Quay lại hồ sơ
                </a>
                <h1 id="tutor-profile-editor-title">Chỉnh sửa hồ sơ gia sư</h1>
                <p>Cập nhật thông tin hồ sơ và quản lý nội dung hiển thị với người học.</p>
            </div>
            <div class="tutor-profile-editor__header-actions">
                <a class="tutor-profile-editor__button is-secondary" href="{{ route('tutor-area.profile') }}">Hủy</a>
                <button class="tutor-profile-editor__button is-primary" type="submit" form="tutor-profile-main-form">
                    <x-directory-icon name="save" />
                    Lưu thay đổi
                </button>
            </div>
        </header>

        @if (session('success'))
            <div class="tutor-profile-editor__alert is-success" role="status">
                <x-directory-icon name="circle-check" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="tutor-profile-editor__alert is-error" role="alert">
                <x-directory-icon name="info" />
                <div>
                    <strong>Chưa thể lưu thay đổi.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="tutor-profile-editor__grid">
            <div class="tutor-profile-editor__column">
                <article class="tutor-profile-editor__panel">
                    <div class="tutor-profile-editor__section-title">
                        <span><x-directory-icon name="briefcase" /></span>
                        <div>
                            <h2>Thông tin cơ bản</h2>
                            <p>Các nội dung này được cập nhật ngay sau khi lưu.</p>
                        </div>
                    </div>

                    <div class="tutor-profile-editor__identity-form">
                        <x-tutor-avatar :user="$profile->user" size="large" />
                        <div class="tutor-profile-editor__fields">
                            <label>
                                <span>Headline <b aria-hidden="true">*</b></span>
                                <input
                                    form="tutor-profile-main-form"
                                    type="text"
                                    name="headline"
                                    value="{{ old('headline', $profile->headline) }}"
                                    maxlength="150"
                                    required
                                >
                            </label>
                            <label>
                                <span>Giới thiệu bản thân <b aria-hidden="true">*</b></span>
                                <textarea form="tutor-profile-main-form" name="bio" rows="5" required>{{ old('bio', $profile->bio) }}</textarea>
                            </label>
                            <label class="tutor-profile-editor__fee-field">
                                <span>Học phí mong muốn <b aria-hidden="true">*</b></span>
                                <span>
                                    <input
                                        form="tutor-profile-main-form"
                                        type="number"
                                        name="hourly_rate"
                                        value="{{ old('hourly_rate', $profile->hourly_rate) }}"
                                        min="0"
                                        step="1000"
                                        required
                                    >
                                    <em>đ/giờ</em>
                                </span>
                            </label>
                        </div>
                    </div>
                </article>

                @foreach ([
                    ['title' => 'Học vấn', 'icon' => 'education', 'value' => $profile->education_summary, 'field' => 'education_summary'],
                    ['title' => 'Kinh nghiệm giảng dạy', 'icon' => 'briefcase', 'value' => $profile->teaching_experience, 'field' => 'teaching_experience'],
                ] as $profileSection)
                    <article class="tutor-profile-editor__panel">
                        <div class="tutor-profile-editor__section-title">
                            <span><x-directory-icon name="{{ $profileSection['icon'] }}" /></span>
                            <div>
                                <h2>{{ $profileSection['title'] }}</h2>
                                <p>Thông tin này được cập nhật ngay sau khi bạn lưu hồ sơ.</p>
                            </div>
                        </div>
                        <textarea
                            form="tutor-profile-main-form"
                            name="{{ $profileSection['field'] }}"
                            rows="5"
                            required
                        >{{ old($profileSection['field'], $profileSection['value']) }}</textarea>
                    </article>
                @endforeach

                <article class="tutor-profile-editor__panel">
                    <div class="tutor-profile-editor__section-title">
                        <span><x-directory-icon name="book-open" /></span>
                        <div>
                            <h2>Chuyên môn</h2>
                            <p>Chuyên môn được cập nhật ngay sau khi bạn lưu hồ sơ.</p>
                        </div>
                    </div>

                    <section class="tutor-profile-editor__specialty-manager" data-specialty-manager>
                        <header class="tutor-profile-editor__specialty-heading">
                            <div>
                                <h3>Chuyên môn đang hiển thị</h3>
                                <p>Thêm từng môn, sau đó chọn các cấp độ bạn có thể dạy.</p>
                            </div>
                            <button
                                class="tutor-profile-editor__button is-secondary"
                                type="button"
                                data-specialty-add
                                aria-controls="tutor-profile-specialty-editor"
                                @disabled(empty($specialtyCatalog))
                            >
                                <x-directory-icon name="plus" />
                                Thêm chuyên môn
                            </button>
                        </header>

                        <div class="tutor-profile-editor__specialty-list" data-specialty-list>
                            @foreach ($selectedSpecialtyItems as $specialty)
                                <article class="tutor-profile-editor__specialty-item" data-specialty-item data-subject-id="{{ $specialty['id'] }}">
                                    <div>
                                        <strong>{{ $specialty['name'] }}</strong>
                                        <span>{{ collect($specialty['levels'])->pluck('name')->join(', ') ?: 'Chưa chọn cấp độ' }}</span>
                                    </div>
                                    <div class="tutor-profile-editor__specialty-actions">
                                        <button type="button" data-specialty-edit aria-label="Sửa chuyên môn {{ $specialty['name'] }}">
                                            <x-directory-icon name="edit" />
                                            Sửa
                                        </button>
                                        <button type="button" class="is-danger" data-specialty-remove aria-label="Xóa chuyên môn {{ $specialty['name'] }}">
                                            <x-directory-icon name="x-circle" />
                                            Xóa
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <template data-specialty-item-template>
                            <article class="tutor-profile-editor__specialty-item" data-specialty-item>
                                <div>
                                    <strong data-specialty-item-name></strong>
                                    <span data-specialty-item-levels></span>
                                </div>
                                <div class="tutor-profile-editor__specialty-actions">
                                    <button type="button" data-specialty-edit>
                                        <x-directory-icon name="edit" />
                                        Sửa
                                    </button>
                                    <button type="button" class="is-danger" data-specialty-remove>
                                        <x-directory-icon name="x-circle" />
                                        Xóa
                                    </button>
                                </div>
                            </article>
                        </template>

                        <div class="tutor-profile-editor__specialty-empty" data-specialty-empty @if ($selectedSpecialtyItems->isNotEmpty()) hidden @endif>
                            <x-directory-icon name="book-open" />
                            <div>
                                <strong>Chưa có chuyên môn</strong>
                                <p>Chọn “Thêm chuyên môn” để bắt đầu.</p>
                            </div>
                        </div>

                        <div id="tutor-profile-specialty-editor" class="tutor-profile-editor__specialty-editor" data-specialty-editor hidden>
                            <div class="tutor-profile-editor__specialty-fields">
                                <label>
                                    <span>Môn học <b aria-hidden="true">*</b></span>
                                    <select data-specialty-subject aria-required="true" aria-describedby="tutor-profile-specialty-error">
                                        <option value="">Chọn môn học</option>
                                        @foreach ($specialtyCatalog as $subject)
                                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <fieldset aria-describedby="tutor-profile-specialty-error">
                                    <legend>Cấp độ có thể dạy <b aria-hidden="true">*</b></legend>
                                    <div class="tutor-profile-editor__specialty-levels" data-specialty-levels>
                                        <p>Chọn môn học để xem cấp độ tương ứng.</p>
                                    </div>
                                </fieldset>
                            </div>

                            <p id="tutor-profile-specialty-error" class="tutor-profile-editor__specialty-error" role="alert" data-specialty-error hidden></p>
                            <div class="tutor-profile-editor__specialty-editor-actions">
                                <button class="tutor-profile-editor__button is-secondary" type="button" data-specialty-cancel>Hủy</button>
                                <button class="tutor-profile-editor__button is-primary" type="button" data-specialty-save>
                                    <span data-specialty-save-label>Thêm chuyên môn</span>
                                </button>
                            </div>
                        </div>

                        @php
                            $specialtyValidationError = $errors->first('subject_ids')
                                ?: $errors->first('subject_levels')
                                ?: $errors->first('subject_levels.*');
                        @endphp
                        @if ($specialtyValidationError)
                            <p class="tutor-profile-editor__specialty-server-error" role="alert">{{ $specialtyValidationError }}</p>
                        @endif
                        <p class="tutor-profile-editor__specialty-server-error" role="status" data-specialty-feedback hidden></p>

                        <div data-specialty-hidden-inputs>
                            @foreach ($selectedSpecialtyItems as $specialty)
                                <input form="tutor-profile-main-form" type="hidden" name="subject_ids[]" value="{{ $specialty['id'] }}" data-specialty-hidden-subject data-subject-id="{{ $specialty['id'] }}">
                                @foreach ($specialty['levels'] as $level)
                                    <input form="tutor-profile-main-form" type="hidden" name="subject_levels[{{ $specialty['id'] }}][]" value="{{ $level['id'] }}" data-specialty-hidden-level data-subject-id="{{ $specialty['id'] }}">
                                @endforeach
                            @endforeach
                        </div>

                        <script type="application/json" data-specialty-catalog>@json($specialtyCatalog)</script>
                    </section>
                </article>
            </div>

            <div class="tutor-profile-editor__column">
                <article class="tutor-profile-editor__panel">
                    <div class="tutor-profile-editor__section-title">
                        <span><x-directory-icon name="map-pin" /></span>
                        <div>
                            <h2>Hình thức &amp; khu vực giảng dạy</h2>
                            <p>Thông tin này được cập nhật ngay sau khi lưu.</p>
                        </div>
                    </div>

                    <fieldset class="tutor-profile-editor__teaching-modes" aria-describedby="tutor-profile-teaching-mode-help tutor-profile-teaching-mode-error">
                        <legend>Hình thức dạy <b aria-hidden="true">*</b></legend>
                        <p id="tutor-profile-teaching-mode-help">Bạn có thể chọn một hoặc cả hai hình thức.</p>
                        <div class="tutor-profile-editor__mode-options">
                            <label class="tutor-profile-editor__mode-option">
                                <input
                                    form="tutor-profile-main-form"
                                    type="checkbox"
                                    name="supports_online"
                                    value="1"
                                    data-teaching-online
                                    @checked(old('supports_online', $profile->supports_online))
                                >
                                <span class="tutor-profile-editor__mode-icon"><x-directory-icon name="mode" /></span>
                                <span class="tutor-profile-editor__mode-copy">
                                    <strong>Trực tuyến</strong>
                                    <small>Dạy học qua nền tảng trực tuyến.</small>
                                </span>
                                <span class="tutor-profile-editor__mode-check" aria-hidden="true"><x-directory-icon name="check" /></span>
                            </label>
                            <label class="tutor-profile-editor__mode-option">
                                <input
                                    form="tutor-profile-main-form"
                                    type="checkbox"
                                    name="supports_offline"
                                    value="1"
                                    data-teaching-offline
                                    @checked($supportsOffline)
                                >
                                <span class="tutor-profile-editor__mode-icon"><x-directory-icon name="location" /></span>
                                <span class="tutor-profile-editor__mode-copy">
                                    <strong>Trực tiếp</strong>
                                    <small>Dạy tại khu vực bạn đã đăng ký.</small>
                                </span>
                                <span class="tutor-profile-editor__mode-check" aria-hidden="true"><x-directory-icon name="check" /></span>
                            </label>
                        </div>
                        <p
                            id="tutor-profile-teaching-mode-error"
                            class="tutor-profile-editor__teaching-mode-error"
                            role="alert"
                            data-teaching-mode-error
                            @if (! $errors->first('supports_online')) hidden @endif
                        >{{ $errors->first('supports_online') }}</p>
                    </fieldset>

                    <section
                        class="tutor-profile-editor__teaching-area-manager"
                        data-teaching-area-manager
                        @if (! $supportsOffline) hidden @endif
                    >
                        <header class="tutor-profile-editor__teaching-area-heading">
                            <div>
                                <h3>Khu vực dạy trực tiếp</h3>
                                <p>Thêm từng phường/xã theo tỉnh hoặc thành phố.</p>
                            </div>
                            <button
                                class="tutor-profile-editor__button is-secondary"
                                type="button"
                                data-teaching-area-add
                                aria-controls="tutor-profile-teaching-area-editor"
                                aria-expanded="false"
                                @disabled(empty($teachingAreaCatalog))
                            >
                                <x-directory-icon name="plus" />
                                Thêm khu vực
                            </button>
                        </header>

                        <div class="tutor-profile-editor__teaching-area-list" data-teaching-area-list aria-live="polite">
                            @foreach ($selectedAreaItems as $area)
                                <article class="tutor-profile-editor__teaching-area-item" data-teaching-area-item data-ward-id="{{ $area['id'] }}">
                                    <span class="tutor-profile-editor__teaching-area-pin"><x-directory-icon name="map-pin" /></span>
                                    <div>
                                        <strong>{{ $area['name'] }}</strong>
                                        <span>{{ $area['province_name'] }}</span>
                                    </div>
                                    <div class="tutor-profile-editor__teaching-area-actions">
                                        <button type="button" data-teaching-area-edit aria-label="Sửa khu vực {{ $area['name'] }}, {{ $area['province_name'] }}">
                                            <x-directory-icon name="edit" />
                                            Sửa
                                        </button>
                                        <button type="button" class="is-danger" data-teaching-area-remove aria-label="Xóa khu vực {{ $area['name'] }}, {{ $area['province_name'] }}">
                                            <x-directory-icon name="x-circle" />
                                            Xóa
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <template data-teaching-area-item-template>
                            <article class="tutor-profile-editor__teaching-area-item" data-teaching-area-item>
                                <span class="tutor-profile-editor__teaching-area-pin"><x-directory-icon name="map-pin" /></span>
                                <div>
                                    <strong data-teaching-area-item-name></strong>
                                    <span data-teaching-area-item-province></span>
                                </div>
                                <div class="tutor-profile-editor__teaching-area-actions">
                                    <button type="button" data-teaching-area-edit>
                                        <x-directory-icon name="edit" />
                                        Sửa
                                    </button>
                                    <button type="button" class="is-danger" data-teaching-area-remove>
                                        <x-directory-icon name="x-circle" />
                                        Xóa
                                    </button>
                                </div>
                            </article>
                        </template>

                        <div class="tutor-profile-editor__teaching-area-empty" data-teaching-area-empty @if ($selectedAreaItems->isNotEmpty()) hidden @endif>
                            <x-directory-icon name="map-pin" />
                            <div>
                                <strong>Chưa có khu vực dạy trực tiếp</strong>
                                <p>Chọn “Thêm khu vực” để thêm phường/xã bạn có thể nhận lớp.</p>
                            </div>
                        </div>

                        <div id="tutor-profile-teaching-area-editor" class="tutor-profile-editor__teaching-area-editor" data-teaching-area-editor hidden>
                            <div class="tutor-profile-editor__teaching-area-fields">
                                <label>
                                    <span>Tỉnh/Thành phố <b aria-hidden="true">*</b></span>
                                    <select data-teaching-area-province aria-required="true" aria-describedby="tutor-profile-teaching-area-error">
                                        <option value="">Chọn tỉnh/thành phố</option>
                                        @foreach ($teachingAreaCatalog as $province)
                                            <option value="{{ $province['id'] }}">{{ $province['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Phường/Xã <b aria-hidden="true">*</b></span>
                                    <select data-teaching-area-ward aria-required="true" aria-describedby="tutor-profile-teaching-area-error" disabled>
                                        <option value="">Chọn tỉnh/thành phố trước</option>
                                    </select>
                                </label>
                            </div>

                            <p id="tutor-profile-teaching-area-error" class="tutor-profile-editor__teaching-area-error" role="alert" data-teaching-area-error hidden></p>
                            <div class="tutor-profile-editor__teaching-area-editor-actions">
                                <button class="tutor-profile-editor__button is-secondary" type="button" data-teaching-area-cancel>Hủy</button>
                                <button class="tutor-profile-editor__button is-primary" type="button" data-teaching-area-save>
                                    <span data-teaching-area-save-label>Thêm khu vực</span>
                                </button>
                            </div>
                        </div>

                        @php
                            $teachingAreaValidationError = $errors->first('ward_ids')
                                ?: $errors->first('ward_ids.*');
                        @endphp
                        @if ($teachingAreaValidationError)
                            <p class="tutor-profile-editor__teaching-area-server-error" role="alert" data-teaching-area-server-error>{{ $teachingAreaValidationError }}</p>
                        @endif
                        <p class="tutor-profile-editor__teaching-area-feedback" role="status" data-teaching-area-feedback hidden></p>

                        <div data-teaching-area-hidden-inputs>
                            @foreach ($selectedAreaItems as $area)
                                <input form="tutor-profile-main-form" type="hidden" name="ward_ids[]" value="{{ $area['id'] }}" data-teaching-area-hidden data-ward-id="{{ $area['id'] }}">
                            @endforeach
                        </div>

                        <script type="application/json" data-teaching-area-catalog>@json($teachingAreaCatalog)</script>
                    </section>
                </article>

                <article class="tutor-profile-editor__panel" id="tutor-profile-availability">
                    <div class="tutor-profile-editor__section-title">
                        <span><x-directory-icon name="calendar-days" /></span>
                        <div>
                            <h2>Lịch rảnh</h2>
                            <p>Thêm, sửa hoặc xóa các khung giờ bạn có thể nhận lớp.</p>
                        </div>
                    </div>

                    <details class="tutor-profile-editor__disclosure">
                        <summary><x-directory-icon name="plus" /> Thêm khung giờ</summary>
                        <form class="tutor-profile-editor__inline-form" method="POST" action="{{ route('tutor-area.profile.availability.store') }}">
                            @csrf
                            <label>
                                <span>Ngày</span>
                                <select name="day_of_week" required>
                                    @foreach ($dayLabels as $day => $label)
                                        <option value="{{ $day }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Khung giờ</span>
                                <select name="time_slot_id" required>
                                    @foreach ($timeSlots as $timeSlot)
                                        <option value="{{ $timeSlot->time_slot_id }}">
                                            {{ substr((string) $timeSlot->start_time, 0, 5) }} – {{ substr((string) $timeSlot->end_time, 0, 5) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <button class="tutor-profile-editor__button is-primary" type="submit">Thêm</button>
                        </form>
                    </details>

                    <div class="tutor-profile-editor__schedule-list">
                        @forelse ($profile->availabilities as $availability)
                            <article>
                                <div>
                                    <strong>{{ $dayLabels[$availability->day_of_week] ?? 'Ngày trong tuần' }}</strong>
                                    <span>
                                        {{ substr((string) $availability->timeSlot?->start_time, 0, 5) }} –
                                        {{ substr((string) $availability->timeSlot?->end_time, 0, 5) }}
                                    </span>
                                </div>
                                <details>
                                    <summary><x-directory-icon name="edit" /> Sửa</summary>
                                    <form class="tutor-profile-editor__inline-form" method="POST" action="{{ route('tutor-area.profile.availability.update', $availability) }}">
                                        @csrf
                                        @method('PATCH')
                                        <label>
                                            <span>Ngày</span>
                                            <select name="day_of_week" required>
                                                @foreach ($dayLabels as $day => $label)
                                                    <option value="{{ $day }}" @selected((int) $availability->day_of_week === $day)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>
                                            <span>Khung giờ</span>
                                            <select name="time_slot_id" required>
                                                @foreach ($timeSlots as $timeSlot)
                                                    <option value="{{ $timeSlot->time_slot_id }}" @selected((int) $availability->time_slot_id === (int) $timeSlot->time_slot_id)>
                                                        {{ substr((string) $timeSlot->start_time, 0, 5) }} – {{ substr((string) $timeSlot->end_time, 0, 5) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <button class="tutor-profile-editor__button is-primary" type="submit">Lưu</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('tutor-area.profile.availability.destroy', $availability) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="tutor-profile-editor__text-action is-danger" type="submit">
                                        <x-directory-icon name="x-circle" /> Xóa
                                    </button>
                                </form>
                            </article>
                        @empty
                            <p class="tutor-profile-editor__empty">Bạn chưa thiết lập lịch rảnh.</p>
                        @endforelse
                    </div>
                </article>

                <article class="tutor-profile-editor__panel tutor-profile-editor__review-panel" id="tutor-profile-documents">
                    <div class="tutor-profile-editor__section-title">
                        <span><x-directory-icon name="file-check" /></span>
                        <div>
                            <h2>Minh chứng</h2>
                            <p>Quản lý bằng cấp, chứng chỉ và tài liệu xác minh hồ sơ.</p>
                        </div>
                        <span class="tutor-profile-editor__review-badge">
                            <x-directory-icon name="clock" /> Cần Admin xét duyệt
                        </span>
                    </div>

                    <p class="tutor-profile-editor__notice">
                        <x-directory-icon name="info" />
                        Minh chứng đã duyệt chỉ được thay thế hoặc xóa sau khi Admin duyệt yêu cầu.
                    </p>

                    <details class="tutor-profile-editor__disclosure">
                        <summary><x-directory-icon name="plus" /> Thêm minh chứng</summary>
                        <form class="tutor-profile-editor__document-form" method="POST" action="{{ route('tutor-area.profile.documents.store') }}" enctype="multipart/form-data">
                            @csrf
                            <label>
                                <span>Loại tài liệu</span>
                                <select name="document_type" required>
                                    @foreach ($documentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Tên minh chứng</span>
                                <input type="text" name="document_name" maxlength="255" required>
                            </label>
                            <label>
                                <span>Tệp PDF, JPG hoặc PNG</span>
                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                            </label>
                            <button class="tutor-profile-editor__button is-primary" type="submit">Gửi xét duyệt</button>
                        </form>
                    </details>

                    <div class="tutor-profile-editor__document-list">
                        @forelse ($profile->documents as $document)
                            @php
                                $documentChange = $documentChanges->get((int) $document->document_id);
                                $isApprovedDocument = $document->verification_status === \App\Models\TutorDocument::STATUS_APPROVED;
                                $currentStatusTone = $document->verificationStatusTone() === 'unknown' ? 'pending' : $document->verificationStatusTone();
                            @endphp
                            <article>
                                <div class="tutor-profile-editor__document-icon"><x-directory-icon name="file-check" /></div>
                                <div class="tutor-profile-editor__document-copy">
                                    <strong>{{ $document->document_name ?: $document->typeLabel() }}</strong>
                                    <span>{{ $document->typeLabel() }}</span>
                                    @if ($documentChange?->rejection_reason)
                                        <small><strong>Lý do:</strong> {{ $documentChange->rejection_reason }}</small>
                                    @endif
                                </div>
                                <span class="tutor-profile-editor__status is-{{ $documentChange?->statusTone() ?? $currentStatusTone }}">
                                    {{ $documentChange?->statusLabel() ?? $document->verificationStatusLabel() }}
                                </span>
                                <div class="tutor-profile-editor__document-actions">
                                    <a href="{{ route('tutor-registration.documents.download', ['document' => $document, 'disposition' => 'inline']) }}" target="_blank" rel="noopener">Xem</a>
                                    @if ($documentChange && data_get($documentChange->payload, 'file_url') !== $document->file_url)
                                        <a href="{{ route('tutor-area.profile.changes.document', ['changeRequest' => $documentChange, 'disposition' => 'inline']) }}" target="_blank" rel="noopener">Xem bản mới</a>
                                    @endif
                                    <details>
                                        <summary>{{ $document->verification_status === \App\Models\TutorDocument::STATUS_REJECTED ? 'Gửi lại' : 'Chỉnh sửa' }}</summary>
                                        <form class="tutor-profile-editor__document-form" method="POST" action="{{ route('tutor-area.profile.documents.update', $document) }}" enctype="multipart/form-data">
                                            @csrf
                                            @method('PUT')
                                            <label>
                                                <span>Loại tài liệu</span>
                                                <select name="document_type" required>
                                                    @foreach ($documentTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected($value === data_get($documentChange?->payload, 'document_type', $document->document_type))>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>
                                                <span>Tên minh chứng</span>
                                                <input type="text" name="document_name" value="{{ data_get($documentChange?->payload, 'document_name', $document->document_name) }}" maxlength="255" required>
                                            </label>
                                            <label>
                                                <span>{{ $isApprovedDocument ? 'Tệp thay thế (không bắt buộc)' : 'Tệp mới (không bắt buộc)' }}</span>
                                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
                                            </label>
                                            <button class="tutor-profile-editor__button is-primary" type="submit">Gửi xét duyệt</button>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('tutor-area.profile.documents.destroy', $document) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tutor-profile-editor__text-action is-danger" type="submit">
                                            {{ $isApprovedDocument ? 'Yêu cầu xóa' : 'Xóa' }}
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <p class="tutor-profile-editor__empty">Bạn chưa có minh chứng nào.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>

        <footer class="tutor-profile-editor__footer">
            <p><x-directory-icon name="info" /> Học vấn và kinh nghiệm được cập nhật ngay; minh chứng vẫn cần Admin xét duyệt.</p>
            <div>
                <a class="tutor-profile-editor__button is-secondary" href="{{ route('tutor-area.profile') }}">Hủy</a>
                <button class="tutor-profile-editor__button is-primary" type="submit" form="tutor-profile-main-form">
                    <x-directory-icon name="save" /> Lưu thay đổi
                </button>
            </div>
        </footer>
    </section>
</x-tutor-account-layout>
