@php
    $hasOldInput = session()->hasOldInput();
    $formSupportsOnline = $hasOldInput
        ? filter_var(old('supports_online', false), FILTER_VALIDATE_BOOLEAN)
        : (bool) $tutorProfile->supports_online;
    $formSupportsOffline = $hasOldInput
        ? filter_var(old('supports_offline', false), FILTER_VALIDATE_BOOLEAN)
        : (bool) $tutorProfile->supports_offline;
    $wardLookup = $provinces
        ->flatMap(fn ($province) => $province->wards->map(fn ($ward) => [
            'ward_id' => (int) $ward->ward_id,
            'ward_name' => $ward->ward_name,
            'province_id' => (int) $province->province_id,
            'province_name' => $province->province_name,
        ]))
        ->keyBy('ward_id');
    $oldWardIds = old('ward_ids');
    $formWardIds = collect(is_array($oldWardIds) ? $oldWardIds : $selectedWardIds)
        ->filter(fn ($wardId) => is_numeric($wardId))
        ->map(fn ($wardId) => (int) $wardId)
        ->unique()
        ->filter(fn ($wardId) => $wardLookup->has($wardId))
        ->values()
        ->all();
    $defaultProvinceId = $formWardIds === []
        ? ''
        : (string) ($wardLookup->get($formWardIds[0])['province_id'] ?? '');
    $oldProvinceId = old('province_id');
    $formProvinceId = $hasOldInput && is_scalar($oldProvinceId)
        ? (string) $oldProvinceId
        : $defaultProvinceId;
    $visibleWardCount = $formProvinceId === ''
        ? 0
        : $provinces->firstWhere('province_id', (int) $formProvinceId)?->wards->count() ?? 0;
    $hasModeErrors = $errors->has('supports_online') || $errors->has('supports_offline');
    $hasWardErrors = $errors->has('ward_ids') || $errors->has('ward_ids.*');
@endphp

<x-app-layout title="Hình thức & khu vực | Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
    <section
        class="tutor-registration-page"
        aria-labelledby="tutor-registration-title"
        style="--tutor-registration-background: url('{{ asset('images/nen.webp') }}')"
    >
        <div class="container tutor-registration-container">
            <header class="tutor-registration-heading">
                <h1 id="tutor-registration-title">Đăng ký trở thành <span>gia sư</span></h1>
                <p>Chia sẻ kiến thức – Truyền cảm hứng – Cùng nhau phát triển</p>
            </header>

            <nav class="tutor-registration-progress" aria-label="Tiến trình đăng ký gia sư">
                <ol>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Thông tin cơ bản</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Chuyên môn</strong>
                    </li>
                    <li class="is-active" aria-current="step">
                        <span><x-directory-icon name="map-pin" /></span>
                        <strong>Hình thức &amp; khu vực</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="calendar-days" /></span>
                        <strong>Lịch rảnh</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="file-check" /></span>
                        <strong>Minh chứng</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="circle-check" /></span>
                        <strong>Xác nhận</strong>
                    </li>
                </ol>
            </nav>

            <section class="tutor-basic-card tutor-teaching-card" aria-labelledby="tutor-teaching-title">
                <header class="tutor-basic-card-heading tutor-specialization-heading">
                    <span class="tutor-specialization-heading-icon" aria-hidden="true">
                        <x-directory-icon name="map-pin" />
                    </span>
                    <span>
                        <h2 id="tutor-teaching-title">Hình thức &amp; khu vực</h2>
                        <p>Chọn hình thức giảng dạy và những khu vực bạn có thể dạy trực tiếp.</p>
                    </span>
                </header>

                @if (session('success'))
                    <div class="tutor-basic-alert" role="status">
                        <x-directory-icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="tutor-basic-alert tutor-specialization-alert-error" role="alert">
                        <x-directory-icon name="flag" />
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('tutor-registration.teaching-preferences.update') }}"
                    class="tutor-teaching-form"
                    data-tutor-teaching-form
                >
                    @csrf
                    @method('PUT')

                    <fieldset
                        class="tutor-teaching-section tutor-teaching-mode-section"
                        @if ($hasModeErrors) aria-invalid="true" aria-describedby="teaching-mode-error" @endif
                    >
                        <legend class="tutor-teaching-section-heading">
                            <strong>Hình thức giảng dạy</strong>
                            <small>Bạn có thể chọn một hoặc cả hai hình thức.</small>
                        </legend>

                        <div class="tutor-teaching-mode-grid">
                            <label class="tutor-teaching-mode-card">
                                <input
                                    type="checkbox"
                                    name="supports_online"
                                    value="1"
                                    @checked($formSupportsOnline)
                                    data-teaching-online
                                >
                                <span class="tutor-teaching-mode-surface">
                                    <span class="tutor-teaching-mode-icon" aria-hidden="true">
                                        <x-directory-icon name="mode" />
                                    </span>
                                    <span class="tutor-teaching-mode-copy">
                                        <strong>Dạy online</strong>
                                        <small>Có thể giảng dạy qua nền tảng trực tuyến.</small>
                                    </span>
                                    <span class="tutor-teaching-mode-check" aria-hidden="true">
                                        <x-directory-icon name="check" />
                                    </span>
                                </span>
                            </label>

                            <label class="tutor-teaching-mode-card">
                                <input
                                    type="checkbox"
                                    name="supports_offline"
                                    value="1"
                                    aria-controls="tutor-teaching-areas"
                                    @checked($formSupportsOffline)
                                    data-teaching-offline
                                >
                                <span class="tutor-teaching-mode-surface">
                                    <span class="tutor-teaching-mode-icon" aria-hidden="true">
                                        <x-directory-icon name="users" />
                                    </span>
                                    <span class="tutor-teaching-mode-copy">
                                        <strong>Dạy trực tiếp</strong>
                                        <small>Có thể đến khu vực của học viên.</small>
                                    </span>
                                    <span class="tutor-teaching-mode-check" aria-hidden="true">
                                        <x-directory-icon name="check" />
                                    </span>
                                </span>
                            </label>
                        </div>

                        @if ($hasModeErrors)
                            <p class="tutor-basic-error tutor-teaching-field-error" id="teaching-mode-error" role="alert">
                                {{ $errors->first('supports_online') ?: $errors->first('supports_offline') }}
                            </p>
                        @endif
                    </fieldset>

                    <section
                        class="tutor-teaching-section tutor-teaching-area-section"
                        id="tutor-teaching-areas"
                        aria-labelledby="tutor-teaching-area-title"
                        data-teaching-areas
                        @if (! $formSupportsOffline) hidden aria-hidden="true" @endif
                    >
                        <header class="tutor-teaching-section-heading">
                            <h3 id="tutor-teaching-area-title">Khu vực dạy trực tiếp</h3>
                            <p>Chọn tỉnh/thành để lọc, sau đó chọn một hoặc nhiều phường/xã.</p>
                        </header>

                        <div class="tutor-teaching-location-controls">
                            <label class="tutor-teaching-field">
                                <span>Tỉnh/Thành phố</span>
                                <select
                                    name="province_id"
                                    data-teaching-province
                                    @disabled(! $formSupportsOffline)
                                    @error('province_id') aria-invalid="true" aria-describedby="province-id-error" @enderror
                                >
                                    <option value="">Chọn tỉnh/thành phố</option>
                                    @forelse ($provinces as $province)
                                        <option value="{{ $province->province_id }}" @selected($formProvinceId === (string) $province->province_id)>
                                            {{ $province->province_name }}
                                        </option>
                                    @empty
                                        <option value="" disabled>Chưa có tỉnh/thành phố khả dụng</option>
                                    @endforelse
                                </select>
                                @error('province_id')
                                    <small class="tutor-basic-error" id="province-id-error" role="alert">{{ $message }}</small>
                                @enderror
                            </label>

                            <label class="tutor-teaching-field">
                                <span>Tìm Phường/Xã</span>
                                <span class="tutor-teaching-search-control">
                                    <x-directory-icon name="search" />
                                    <input
                                        type="search"
                                        placeholder="Nhập tên phường/xã"
                                        autocomplete="off"
                                        aria-controls="tutor-teaching-ward-list"
                                        @disabled(! $formSupportsOffline || $formProvinceId === '')
                                        data-teaching-ward-search
                                    >
                                </span>
                            </label>
                        </div>

                        <div
                            class="tutor-teaching-ward-picker"
                            @if ($hasWardErrors) aria-invalid="true" aria-describedby="ward-ids-error" @endif
                        >
                            <div class="tutor-teaching-ward-toolbar">
                                <strong>Phường/Xã</strong>
                                <span aria-live="polite" data-teaching-ward-status>
                                    @if ($formProvinceId === '')
                                        Chọn tỉnh/thành phố để xem danh sách
                                    @else
                                        {{ $visibleWardCount }} phường/xã
                                    @endif
                                </span>
                            </div>

                            <div class="tutor-teaching-ward-list" id="tutor-teaching-ward-list" data-teaching-ward-list>
                                @foreach ($provinces as $province)
                                    @foreach ($province->wards as $ward)
                                        @php
                                            $wardId = (int) $ward->ward_id;
                                            $isSelected = in_array($wardId, $formWardIds, true);
                                        @endphp
                                        <label
                                            class="tutor-teaching-ward-option"
                                            data-teaching-ward-option
                                            data-ward-id="{{ $wardId }}"
                                            data-province-id="{{ $province->province_id }}"
                                            data-ward-name="{{ $ward->ward_name }}"
                                            data-province-name="{{ $province->province_name }}"
                                            data-search-label="{{ $ward->ward_name }}"
                                            @if ($formProvinceId === '' || $formProvinceId !== (string) $province->province_id) hidden @endif
                                        >
                                            <input
                                                type="checkbox"
                                                name="ward_ids[]"
                                                value="{{ $wardId }}"
                                                @checked($isSelected)
                                                @disabled(! $formSupportsOffline)
                                                data-teaching-ward-checkbox
                                            >
                                            <span aria-hidden="true"><x-directory-icon name="check" /></span>
                                            <strong>{{ $ward->ward_name }}</strong>
                                        </label>
                                    @endforeach
                                @endforeach

                                <p class="tutor-teaching-ward-empty" data-teaching-ward-empty @if ($visibleWardCount > 0) hidden @endif>
                                    {{ $formProvinceId === '' ? 'Chọn tỉnh/thành phố để xem danh sách phường/xã.' : 'Không có phường/xã phù hợp.' }}
                                </p>
                            </div>
                        </div>

                        @if ($hasWardErrors)
                            <p class="tutor-basic-error tutor-teaching-field-error" id="ward-ids-error" role="alert">
                                {{ $errors->first('ward_ids') ?: $errors->first('ward_ids.*') }}
                            </p>
                        @endif

                        <section
                            class="tutor-teaching-selected"
                            aria-labelledby="tutor-teaching-selected-title"
                            data-teaching-selected
                            @if ($formWardIds === []) hidden @endif
                        >
                            <header>
                                <strong id="tutor-teaching-selected-title">
                                    Khu vực đã chọn (<span data-teaching-selected-count>{{ count($formWardIds) }}</span>)
                                </strong>
                                <button type="button" data-teaching-clear-all>Xóa tất cả</button>
                            </header>
                            <div class="tutor-teaching-selected-list" role="list" aria-live="polite" data-teaching-selected-list>
                                @foreach ($formWardIds as $wardId)
                                    @php($selectedWard = $wardLookup->get($wardId))
                                    <span class="tutor-teaching-chip" role="listitem" data-teaching-chip data-ward-id="{{ $wardId }}">
                                        <span>
                                            <strong>{{ $selectedWard['ward_name'] }}</strong>
                                            <small>{{ $selectedWard['province_name'] }}</small>
                                        </span>
                                        <button type="button" aria-label="Xóa {{ $selectedWard['ward_name'] }}, {{ $selectedWard['province_name'] }}" data-teaching-remove-ward>
                                            <x-directory-icon name="plus" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        </section>

                        <template data-teaching-chip-template>
                            <span class="tutor-teaching-chip" role="listitem" data-teaching-chip>
                                <span>
                                    <strong data-teaching-chip-ward></strong>
                                    <small data-teaching-chip-province></small>
                                </span>
                                <button type="button" data-teaching-remove-ward>
                                    <x-directory-icon name="plus" />
                                </button>
                            </span>
                        </template>

                        <aside class="tutor-specialization-note tutor-teaching-note">
                            <x-directory-icon name="verified" />
                            <p>
                                <strong>Bạn có thể chọn khu vực ở nhiều tỉnh/thành phố.</strong>
                                Các khu vực đã chọn vẫn được giữ khi bạn đổi bộ lọc tỉnh/thành.
                            </p>
                        </aside>
                    </section>

                    <footer class="tutor-basic-actions tutor-specialization-actions">
                        <a class="tutor-basic-back" href="{{ route('tutor-registration.specialization.edit') }}">
                            <x-directory-icon name="arrow-left" />
                            Quay lại
                        </a>
                        <button class="button tutor-basic-submit" type="submit" data-teaching-submit>
                            <span data-teaching-submit-label>Tiếp tục</span>
                            <x-directory-icon name="chevron-right" />
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </section>
</x-app-layout>
