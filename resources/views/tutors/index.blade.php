<x-app-layout>
    <x-slot name="title">Tìm gia sư phù hợp | GiaSu</x-slot>

    <section class="tutors-directory tutors-directory-refined" aria-labelledby="tutors-results-heading">
        <div class="container tutors-layout">
            <details class="tutor-sidebar tutor-filter-panel" data-directory-filters open>
                <summary class="tutor-filter-panel-trigger"><x-directory-icon name="filter" /><span>Bộ lọc</span><x-directory-icon name="chevron" /></summary>
                <form class="tutor-filter-form" action="{{ route('tutors.index') }}" method="GET">
                    <div class="tutor-sidebar-heading">
                        <p class="eyebrow"><x-directory-icon name="filter" /> Bộ lọc</p>
                        <h2 id="filter-heading">Tìm gia sư phù hợp</h2>
                        <p>Chọn vài tiêu chí để tìm nhanh hơn.</p>
                    </div>

                    <div class="tutor-filter-fields">
                        <div class="tutor-filter-field tutor-combobox" data-combobox>
                            <label for="subject-search"><x-directory-icon name="education" /> Môn học</label>
                            <div class="combobox-control">
                                <input
                                    id="subject-search"
                                    type="text"
                                    value="{{ $subjectId ? $subjects->firstWhere('subject_id', $subjectId)?->subject_name : '' }}"
                                    placeholder="Tất cả môn học"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="subject-options"
                                    data-combobox-input
                                >
                                <button class="combobox-toggle" type="button" aria-label="Mở danh sách môn học" aria-expanded="false" aria-controls="subject-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                            </div>
                            <div class="combobox-options" id="subject-options" role="listbox" hidden>
                                <button type="button" role="option" data-value="" data-label="Tất cả môn học">Tất cả môn học</button>
                                @foreach ($subjects as $subject)
                                    <button
                                        type="button"
                                        role="option"
                                        data-value="{{ $subject->subject_id }}"
                                        data-label="{{ $subject->subject_name }}"
                                    >
                                        {{ $subject->subject_name }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" name="subject" value="{{ $subjectId }}" data-combobox-value>
                        </div>

                        <div class="tutor-filter-field tutor-combobox" data-combobox data-level-combobox>
                            <label for="level-search"><x-directory-icon name="document" /> Cấp độ / lớp</label>
                            <div class="combobox-control">
                                <input
                                    id="level-search"
                                    type="text"
                                    value="{{ $selectedLevel?->level_name }}"
                                    placeholder="Tất cả cấp độ"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="level-options"
                                    data-combobox-input
                                >
                                <button class="combobox-toggle" type="button" aria-label="Mở danh sách cấp độ" aria-expanded="false" aria-controls="level-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                            </div>
                            <div class="combobox-options" id="level-options" role="listbox" hidden>
                                <button type="button" role="option" data-value="" data-label="Tất cả cấp độ">Tất cả cấp độ</button>
                                @foreach ($levels as $level)
                                    <button
                                        type="button"
                                        role="option"
                                        data-value="{{ $level->subject_level_id }}"
                                        data-label="{{ $level->level_name }}"
                                        data-subject-id="{{ $level->subject_id }}"
                                    >
                                        {{ $level->level_name }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" name="subject_level" value="{{ $subjectLevelId }}" data-combobox-value>
                        </div>

                        <div class="tutor-filter-field tutor-combobox" data-combobox>
                            <label for="location-search"><x-directory-icon name="location" /> Tỉnh/thành</label>
                            <div class="combobox-control">
                                <input
                                    id="location-search"
                                    type="text"
                                    value="{{ $provinceId ? $locations->firstWhere('province_id', $provinceId)?->province_name : '' }}"
                                    placeholder="Tất cả tỉnh/thành"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="location-options"
                                    data-combobox-input
                                >
                                <button class="combobox-toggle" type="button" aria-label="Mở danh sách tỉnh thành" aria-expanded="false" aria-controls="location-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                            </div>
                            <div class="combobox-options" id="location-options" role="listbox" hidden>
                                <button type="button" role="option" data-value="" data-label="Tất cả tỉnh/thành">Tất cả tỉnh/thành</button>
                                @foreach ($locations as $location)
                                    <button
                                        type="button"
                                        role="option"
                                        data-value="{{ $location->province_id }}"
                                        data-label="{{ $location->province_name }}"
                                    >
                                        {{ $location->province_name }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" name="location" value="{{ $provinceId }}" data-combobox-value>
                        </div>

                        <fieldset class="tutor-filter-field tutor-mode-field">
                            <legend><x-directory-icon name="mode" /> Hình thức học</legend>
                            <div class="tutor-mode-options">
                                <label>
                                    <input type="radio" name="learning_mode" value="online" @checked($learningMode === 'online')>
                                    <span>Online</span>
                                </label>
                                <label>
                                    <input type="radio" name="learning_mode" value="offline" @checked($learningMode === 'offline')>
                                    <span>Tại nhà</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="tutor-price-fields">
                            <p class="tutor-field-label"><x-directory-icon name="wallet" /> Mức học phí / giờ</p>
                            <div>
                                <label for="min-price">Từ</label>
                                <input id="min-price" name="min_price" type="number" min="0" step="10000" value="{{ $minPrice }}" placeholder="150000">
                            </div>
                            <div>
                                <label for="max-price">Đến</label>
                                <input id="max-price" name="max_price" type="number" min="0" step="10000" value="{{ $maxPrice }}" placeholder="300000">
                            </div>
                        </div>
                    </div>

                    <div class="tutor-filter-actions">
                        <button class="button" type="submit"><x-directory-icon name="filter" /> Áp dụng bộ lọc</button>
                        @if ($subjectId || $subjectLevelId || $provinceId || $learningMode || $minPrice || $maxPrice)
                            <a class="tutor-clear-link" href="{{ route('tutors.index') }}">Xóa bộ lọc</a>
                        @endif
                    </div>
                </form>
            </details>

            <section class="tutor-results" aria-labelledby="tutors-results-heading">
                <div class="tutors-results-heading">
                    <div>
                        <p class="eyebrow">Danh sách gia sư</p>
                        <h1 id="tutors-results-heading">{{ $tutors->total() }} gia sư phù hợp</h1>
                        <p class="directory-results-description">Tìm theo môn học, khu vực và hình thức bạn mong muốn.</p>
                    </div>

                    <form class="tutor-sort-form" action="{{ route('tutors.index') }}" method="GET">
                        <label for="sort">Sắp xếp</label>
                        <div class="directory-sort-control">
                        <select id="sort" name="sort" onchange="this.form.requestSubmit()">
                            <option value="relevance" @selected($sort === 'relevance')>Phù hợp</option>
                            <option value="newest" @selected($sort === 'newest')>Mới nhất</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Giá thấp đến cao</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Giá cao đến thấp</option>
                        </select>
                        <x-directory-icon name="chevron" />
                        </div>
                        <noscript><button type="submit">Sắp xếp</button></noscript>
                        <input type="hidden" name="subject" value="{{ $subjectId }}">
                        <input type="hidden" name="subject_level" value="{{ $subjectLevelId }}">
                        <input type="hidden" name="location" value="{{ $provinceId }}">
                        <input type="hidden" name="learning_mode" value="{{ $learningMode }}">
                        <input type="hidden" name="min_price" value="{{ $minPrice }}">
                        <input type="hidden" name="max_price" value="{{ $maxPrice }}">
                    </form>
                </div>

                @if ($tutors->isNotEmpty())
                    <div class="tutor-grid">
                        @foreach ($tutors as $tutor)
                            <x-tutor-card :tutor="$tutor" variant="directory" />
                        @endforeach
                    </div>

                    @if ($tutors->hasPages())
                        <nav class="tutor-pagination" aria-label="Phân trang danh sách gia sư">
                            {{ $tutors->links() }}
                        </nav>
                    @endif
                @else
                    <div class="empty-state tutor-empty-state">
                        <h3>Chưa tìm thấy gia sư phù hợp</h3>
                        <p>Hãy thử bỏ bớt một bộ lọc hoặc tìm với lựa chọn khác.</p>
                        <a class="button button-small" href="{{ route('tutors.index') }}">Xem tất cả gia sư</a>
                    </div>
                @endif
            </section>
        </div>
    </section>
</x-app-layout>
