@php
    $hasOldInput = session()->hasOldInput();
    $rawAvailability = $hasOldInput
        ? old('availability', [])
        : $selectedAvailability;
    $activeTimeSlotIds = $timeSlots
        ->pluck('time_slot_id')
        ->map(fn ($timeSlotId) => (int) $timeSlotId);
    $formAvailability = collect(is_array($rawAvailability) ? $rawAvailability : [])
        ->filter(fn ($timeSlotIds, $dayOfWeek) => is_numeric($dayOfWeek)
            && (int) $dayOfWeek >= 1
            && (int) $dayOfWeek <= 7
            && is_array($timeSlotIds))
        ->mapWithKeys(fn ($timeSlotIds, $dayOfWeek) => [
            (int) $dayOfWeek => collect($timeSlotIds)
                ->filter(fn ($timeSlotId) => is_numeric($timeSlotId))
                ->map(fn ($timeSlotId) => (int) $timeSlotId)
                ->unique()
                ->filter(fn ($timeSlotId) => $activeTimeSlotIds->contains($timeSlotId))
                ->values()
                ->all(),
        ])
        ->all();
    $slotPresentation = $timeSlots->mapWithKeys(function ($timeSlot) {
        $startTime = substr((string) $timeSlot->start_time, 0, 5);
        $endTime = substr((string) $timeSlot->end_time, 0, 5);
        $timeRange = "$startTime – $endTime";
        $slotName = trim((string) $timeSlot->slot_name);
        $label = $slotName !== '' ? $slotName : $timeRange;
        $showsTimeRange = $slotName !== ''
            && (! str_contains($slotName, $startTime) || ! str_contains($slotName, $endTime));

        return [
            (int) $timeSlot->time_slot_id => [
                'label' => $label,
                'time_range' => $timeRange,
                'shows_time_range' => $showsTimeRange,
                'summary_label' => $showsTimeRange ? "$label ($timeRange)" : $label,
            ],
        ];
    });
    $selectedCount = collect($formAvailability)->sum(fn ($timeSlotIds) => count($timeSlotIds));
    $hasAvailabilityErrors = $errors->has('availability') || $errors->has('availability.*');
    $availabilityGridColumns = '112px repeat('.$timeSlots->count().', minmax(104px, 1fr))';
    $availabilityMinWidth = 112 + ($timeSlots->count() * 104);
@endphp

<x-app-layout title="Lịch rảnh | Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
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
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Hình thức &amp; khu vực</strong>
                    </li>
                    <li class="is-active" aria-current="step">
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

            <section class="tutor-basic-card tutor-availability-card" aria-labelledby="tutor-availability-title">
                <header class="tutor-basic-card-heading tutor-specialization-heading">
                    <span class="tutor-specialization-heading-icon" aria-hidden="true">
                        <x-directory-icon name="calendar-days" />
                    </span>
                    <span>
                        <h2 id="tutor-availability-title">Lịch rảnh</h2>
                        <p>Chọn những khung giờ bạn có thể nhận lớp.</p>
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
                    action="{{ route('tutor-registration.availability.update') }}"
                    class="tutor-availability-form"
                    data-tutor-availability-form
                >
                    @csrf
                    @method('PUT')

                    <aside class="tutor-specialization-note tutor-availability-note">
                        <x-directory-icon name="calendar-days" />
                        <p>
                            <strong>Bạn có thể chọn nhiều khung giờ trong cùng một ngày.</strong>
                            Lịch rảnh giúp người học tìm được thời gian phù hợp với bạn.
                        </p>
                    </aside>

                    @if ($timeSlots->isNotEmpty())
                        <div
                            class="tutor-availability-matrix-scroll"
                            @if ($hasAvailabilityErrors) aria-invalid="true" aria-describedby="tutor-availability-error" @endif
                        >
                            <div
                                class="tutor-availability-matrix"
                                style="--availability-grid-columns: {{ $availabilityGridColumns }}; --availability-min-width: {{ $availabilityMinWidth }}px"
                            >
                                <div class="tutor-availability-matrix-header" aria-hidden="true">
                                    <strong>Ngày</strong>
                                    @foreach ($timeSlots as $timeSlot)
                                        @php
                                            $presentation = $slotPresentation->get((int) $timeSlot->time_slot_id);
                                        @endphp
                                        <span class="tutor-availability-slot-heading">
                                            <strong>{{ $presentation['label'] }}</strong>
                                            @if ($presentation['shows_time_range'])
                                                <small>{{ $presentation['time_range'] }}</small>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>

                                @foreach ($dayLabels as $dayOfWeek => $dayLabel)
                                    <fieldset class="tutor-availability-day-row">
                                        <legend class="visually-hidden">Lịch rảnh {{ $dayLabel }}</legend>
                                        <strong class="tutor-availability-day-label" aria-hidden="true">{{ $dayLabel }}</strong>

                                        @foreach ($timeSlots as $timeSlot)
                                            @php
                                                $timeSlotId = (int) $timeSlot->time_slot_id;
                                                $presentation = $slotPresentation->get($timeSlotId);
                                                $isSelected = in_array($timeSlotId, $formAvailability[$dayOfWeek] ?? [], true);
                                            @endphp

                                            <label class="tutor-availability-option" for="availability-{{ $dayOfWeek }}-{{ $timeSlotId }}">
                                                <input
                                                    class="visually-hidden"
                                                    id="availability-{{ $dayOfWeek }}-{{ $timeSlotId }}"
                                                    type="checkbox"
                                                    name="availability[{{ $dayOfWeek }}][]"
                                                    value="{{ $timeSlotId }}"
                                                    aria-label="{{ $dayLabel }}, {{ $presentation['time_range'] }}"
                                                    @checked($isSelected)
                                                    data-availability-checkbox
                                                    data-day="{{ $dayOfWeek }}"
                                                    data-day-label="{{ $dayLabel }}"
                                                    data-slot-label="{{ $presentation['summary_label'] }}"
                                                >
                                                <span class="tutor-availability-option-surface">
                                                    <span class="tutor-availability-option-check" aria-hidden="true">
                                                        <x-directory-icon name="check" />
                                                    </span>
                                                    <span class="tutor-availability-option-copy">
                                                        <span>{{ $presentation['label'] }}</span>
                                                        @if ($presentation['shows_time_range'])
                                                            <small>{{ $presentation['time_range'] }}</small>
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </fieldset>
                                @endforeach
                            </div>
                        </div>

                        @if ($hasAvailabilityErrors)
                            <p class="tutor-basic-error tutor-availability-error" id="tutor-availability-error" role="alert">
                                {{ $errors->first('availability') ?: $errors->first('availability.*') }}
                            </p>
                        @endif
                    @else
                        <div class="tutor-specialization-catalog-empty tutor-availability-catalog-empty" role="status">
                            <x-directory-icon name="calendar-days" />
                            <h3>Chưa có khung giờ khả dụng</h3>
                            <p>Danh mục khung giờ đang được cập nhật. Bạn có thể quay lại sau.</p>
                        </div>
                    @endif

                    <section class="tutor-availability-summary" aria-labelledby="tutor-availability-summary-title">
                        <header>
                            <span class="tutor-availability-summary-icon" aria-hidden="true">
                                <x-directory-icon name="calendar-days" />
                            </span>
                            <span>
                                <strong id="tutor-availability-summary-title">
                                    Đã chọn <span aria-live="polite" data-availability-selected-count>{{ $selectedCount }}</span> khung giờ
                                </strong>
                                <small>Các lựa chọn được nhóm theo ngày trong tuần.</small>
                            </span>
                            <button type="button" @disabled($selectedCount === 0) data-availability-clear-all>
                                Xóa tất cả
                            </button>
                        </header>

                        <ul class="tutor-availability-summary-list" data-availability-summary-list>
                            @foreach ($dayLabels as $dayOfWeek => $dayLabel)
                                @php
                                    $selectedLabels = collect($formAvailability[$dayOfWeek] ?? [])
                                        ->map(fn ($timeSlotId) => $slotPresentation->get($timeSlotId)['summary_label'] ?? null)
                                        ->filter()
                                        ->values();
                                @endphp
                                <li data-availability-summary-day data-day="{{ $dayOfWeek }}" @if ($selectedLabels->isEmpty()) hidden @endif>
                                    <strong>{{ $dayLabel }}</strong>
                                    <span data-availability-summary-values>{{ $selectedLabels->implode(', ') }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="tutor-availability-summary-empty" data-availability-summary-empty @if ($selectedCount > 0) hidden @endif>
                            Chưa chọn khung giờ nào.
                        </p>
                    </section>

                    <footer class="tutor-basic-actions tutor-specialization-actions">
                        <a class="tutor-basic-back" href="{{ route('tutor-registration.teaching-preferences.edit') }}">
                            <x-directory-icon name="arrow-left" />
                            Quay lại
                        </a>
                        <button class="button tutor-basic-submit" type="submit" data-availability-submit>
                            <span data-availability-submit-label>Tiếp tục</span>
                            <x-directory-icon name="chevron-right" />
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </section>
</x-app-layout>
