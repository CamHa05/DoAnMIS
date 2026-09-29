@php
    $oldSubjectIds = old('subject_ids');
    $formSubjectIds = is_array($oldSubjectIds)
        ? collect($oldSubjectIds)->filter(fn ($subjectId) => is_numeric($subjectId))->map(fn ($subjectId) => (int) $subjectId)->unique()->values()->all()
        : $selectedSubjectIds;
    $oldLevelIdsBySubject = old('subject_levels');
    $formLevelIdsBySubject = is_array($oldLevelIdsBySubject)
        ? collect($oldLevelIdsBySubject)
            ->filter(fn ($levelIds, $subjectId) => is_numeric($subjectId) && is_array($levelIds))
            ->mapWithKeys(fn ($levelIds, $subjectId) => [
                (int) $subjectId => collect($levelIds)
                    ->filter(fn ($levelId) => is_numeric($levelId))
                    ->map(fn ($levelId) => (int) $levelId)
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->all()
        : $selectedLevelIdsBySubject;
    $catalogSubjectIds = $subjects->pluck('subject_id')->map(fn ($subjectId) => (int) $subjectId);
    $initialSubjectId = collect($formSubjectIds)->first(fn ($subjectId) => $catalogSubjectIds->contains($subjectId));
@endphp

<x-app-layout title="Chuyên môn | Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
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
                    <li class="is-active" aria-current="step">
                        <span><x-directory-icon name="book-open" /></span>
                        <strong>Chuyên môn</strong>
                    </li>
                    <li>
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

            <section class="tutor-basic-card tutor-specialization-card" aria-labelledby="tutor-specialization-title">
                <header class="tutor-basic-card-heading tutor-specialization-heading">
                    <span class="tutor-specialization-heading-icon" aria-hidden="true">
                        <x-directory-icon name="education" />
                    </span>
                    <span>
                        <h2 id="tutor-specialization-title">Chuyên môn</h2>
                        <p>Chọn môn học và trình độ mà bạn có thể giảng dạy.</p>
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
                    action="{{ route('tutor-registration.specialization.update') }}"
                    class="tutor-specialization-form"
                    data-tutor-specialization-form
                    data-initial-subject-id="{{ $initialSubjectId }}"
                >
                    @csrf
                    @method('PUT')

                    @if ($subjects->isNotEmpty())
                        <div class="tutor-specialization-workspace">
                            <aside class="tutor-specialization-subjects" aria-labelledby="tutor-specialization-subjects-title">
                                <h3 id="tutor-specialization-subjects-title">Môn học</h3>

                                <div class="tutor-specialization-search">
                                    <label for="tutor-specialization-search">Tìm môn học</label>
                                    <span class="tutor-specialization-search-control">
                                        <x-directory-icon name="search" />
                                        <input
                                            id="tutor-specialization-search"
                                            type="search"
                                            placeholder="Nhập tên môn học"
                                            autocomplete="off"
                                            data-specialization-search
                                        >
                                    </span>
                                    <p class="tutor-specialization-search-status" aria-live="polite" data-specialization-search-status></p>
                                </div>

                                <div class="tutor-specialization-subject-list" data-specialization-subject-list>
                                    @foreach ($subjects as $subject)
                                        @php
                                            $subjectId = (int) $subject->subject_id;
                                            $isSelected = in_array($subjectId, $formSubjectIds, true);
                                        @endphp

                                        <div
                                            @class(['tutor-specialization-subject-option', 'is-selected' => $isSelected])
                                            data-specialization-subject-option
                                            data-subject-id="{{ $subjectId }}"
                                            data-search-label="{{ $subject->subject_name }} {{ $subject->category?->category_name }}"
                                        >
                                            <input
                                                id="tutor-subject-{{ $subjectId }}"
                                                class="tutor-specialization-subject-checkbox"
                                                type="checkbox"
                                                name="subject_ids[]"
                                                value="{{ $subjectId }}"
                                                @checked($isSelected)
                                                data-specialization-subject-checkbox
                                            >
                                            <label for="tutor-subject-{{ $subjectId }}">
                                                <strong>{{ $subject->subject_name }}</strong>
                                                @if ($subject->category)
                                                    <small>{{ $subject->category->category_name }}</small>
                                                @endif
                                            </label>
                                            <button
                                                type="button"
                                                aria-label="Mở danh sách trình độ của môn {{ $subject->subject_name }}"
                                                aria-controls="tutor-specialization-panel-{{ $subjectId }}"
                                                @disabled(! $isSelected)
                                                data-specialization-panel-trigger
                                            >
                                                <x-directory-icon name="chevron-right" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </aside>

                            <div class="tutor-specialization-levels" aria-live="polite">
                                <div class="tutor-specialization-empty" data-specialization-empty @if ($initialSubjectId) hidden @endif>
                                    <x-directory-icon name="education" />
                                    <h3>Chọn một môn học</h3>
                                    <p>Danh sách trình độ tương ứng sẽ xuất hiện tại đây.</p>
                                </div>

                                @foreach ($subjects as $subject)
                                    @php
                                        $subjectId = (int) $subject->subject_id;
                                        $selectedLevelIds = $formLevelIdsBySubject[$subjectId] ?? [];
                                    @endphp

                                    <fieldset
                                        id="tutor-specialization-panel-{{ $subjectId }}"
                                        class="tutor-specialization-level-panel"
                                        data-specialization-panel
                                        data-subject-id="{{ $subjectId }}"
                                    >
                                        <legend>
                                            <span class="tutor-specialization-level-icon" aria-hidden="true">
                                                <x-directory-icon name="education" />
                                            </span>
                                            <span>
                                                <strong>{{ $subject->subject_name }}</strong>
                                                <small>Chọn các trình độ bạn có thể giảng dạy cho môn này.</small>
                                            </span>
                                        </legend>

                                        <div class="tutor-specialization-level-grid">
                                            @foreach ($subject->subjectLevels as $subjectLevel)
                                                <label class="tutor-specialization-level-option">
                                                    <input
                                                        type="checkbox"
                                                        name="subject_levels[{{ $subjectId }}][]"
                                                        value="{{ $subjectLevel->subject_level_id }}"
                                                        @checked(in_array((int) $subjectLevel->subject_level_id, $selectedLevelIds, true))
                                                        data-specialization-level-checkbox
                                                    >
                                                    <span>{{ $subjectLevel->level_name }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                        @error("subject_levels.$subjectId")
                                            <p class="tutor-specialization-level-error" role="alert">{{ $message }}</p>
                                        @enderror
                                    </fieldset>
                                @endforeach

                                <aside class="tutor-specialization-note">
                                    <x-directory-icon name="verified" />
                                    <p>
                                        <strong>Bạn có thể chọn nhiều môn học và nhiều trình độ.</strong>
                                        Những thông tin này sẽ hiển thị trong hồ sơ sau khi được duyệt.
                                    </p>
                                </aside>
                            </div>
                        </div>
                    @else
                        <div class="tutor-specialization-catalog-empty" role="status">
                            <x-directory-icon name="education" />
                            <h3>Chưa có chuyên môn khả dụng</h3>
                            <p>Danh mục môn học và trình độ đang được cập nhật.</p>
                        </div>
                    @endif

                    <footer class="tutor-basic-actions tutor-specialization-actions">
                        <a class="tutor-basic-back" href="{{ route('tutor-registration.basic.edit') }}">
                            <x-directory-icon name="arrow-left" />
                            Quay lại
                        </a>
                        <button class="button tutor-basic-submit" type="submit" @disabled($subjects->isEmpty()) data-specialization-submit>
                            <span data-specialization-submit-label>Tiếp tục</span>
                            <x-directory-icon name="chevron-right" />
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </section>
</x-app-layout>
