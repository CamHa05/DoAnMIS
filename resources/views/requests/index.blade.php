<x-app-layout>
    <x-slot name="title">Lớp đang tìm gia sư | GiaSu</x-slot>

    <section class="requests-page requests-refined" aria-label="Danh sách nhu cầu tìm gia sư">
        <div class="container">
            <div class="requests-layout">
                <details class="requests-filter-drawer" data-request-filters open>
                    <summary class="requests-filter-trigger">
                        <span>Bộ lọc</span>
                        @if ($activeFilterCount)
                            <span class="filter-count">{{ $activeFilterCount }}</span>
                        @endif
                        <x-directory-icon name="chevron" />
                    </summary>
                    <aside class="requests-sidebar" aria-labelledby="request-filter-heading">
                        <form class="requests-filter-form" action="{{ route('requests.index') }}" method="GET">
                            <div class="requests-filter-heading">
                                <p class="eyebrow"><x-directory-icon name="filter" /> Bộ lọc</p>
                                <h2 id="request-filter-heading">Tìm lớp phù hợp</h2>
                                <p>Lọc theo môn học, khu vực và lịch dạy.</p>
                            </div>

                            <div class="requests-filter-fields">
                                <div class="request-filter-field request-search-input">
                                    <label for="request-search"><x-directory-icon name="search" /> Tìm kiếm</label>
                                    <x-directory-icon name="search" />
                                    <input id="request-search" name="q" type="search" value="{{ $search }}" placeholder="Tìm theo tên môn học...">
                                </div>

                                <div class="request-filter-field request-combobox" data-combobox>
                                    <label for="request-subject"><x-directory-icon name="education" /> Môn học</label>
                                    <div class="combobox-control">
                                        <input id="request-subject" type="text" value="{{ $subjectId ? $subjects->firstWhere('subject_id', $subjectId)?->subject_name : '' }}" placeholder="Tất cả môn học" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="request-subject-options" data-combobox-input>
                                        <button class="combobox-toggle" type="button" aria-label="Mở danh sách môn học" aria-expanded="false" aria-controls="request-subject-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                                    </div>
                                    <div class="combobox-options" id="request-subject-options" role="listbox" hidden>
                                        <button type="button" role="option" data-value="" data-label="Tất cả môn học">Tất cả môn học</button>
                                        @foreach ($subjects as $subject)
                                            <button type="button" role="option" data-value="{{ $subject->subject_id }}" data-label="{{ $subject->subject_name }}">{{ $subject->subject_name }}</button>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="subject" value="{{ $subjectId }}" data-combobox-value>
                                </div>

                                <div class="request-filter-field request-select-control">
                                    <label for="request-education-level"><x-directory-icon name="document" /> Cấp học</label>
                                    <select id="request-education-level" name="education_level">
                                        <option value="">Tất cả cấp học</option>
                                        @foreach ($educationLevels as $educationLevel)
                                            <option value="{{ $educationLevel->education_level_id }}" @selected($educationLevelId === $educationLevel->education_level_id)>{{ $educationLevel->level_name }}</option>
                                        @endforeach
                                    </select>
                                    <x-directory-icon name="chevron" />
                                </div>

                                <div class="request-filter-field request-combobox" data-combobox data-level-combobox>
                                    <label for="request-level"><x-directory-icon name="list" /> Lớp / trình độ</label>
                                    <div class="combobox-control">
                                        <input id="request-level" type="text" value="{{ $selectedLevel ? $selectedLevel->subject?->subject_name . ' · ' . $selectedLevel->level_name : '' }}" placeholder="Tất cả lớp / trình độ" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="request-level-options" data-combobox-input>
                                        <button class="combobox-toggle" type="button" aria-label="Mở danh sách lớp hoặc trình độ" aria-expanded="false" aria-controls="request-level-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                                    </div>
                                    <div class="combobox-options" id="request-level-options" role="listbox" hidden>
                                        <button type="button" role="option" data-value="" data-label="Tất cả lớp / trình độ">Tất cả lớp / trình độ</button>
                                        @foreach ($levels as $level)
                                            <button type="button" role="option" data-value="{{ $level->subject_level_id }}" data-label="{{ $level->subject?->subject_name }} · {{ $level->level_name }}" data-subject-id="{{ $level->subject_id }}" data-education-level-id="{{ $level->education_level_id }}">{{ $level->subject?->subject_name }} · {{ $level->level_name }}</button>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="subject_level" value="{{ $subjectLevelId }}" data-combobox-value>
                                </div>

                                <fieldset class="request-filter-field request-mode-field">
                                    <legend><x-directory-icon name="mode" /> Hình thức học</legend>
                                    <div class="request-mode-options">
                                        <label>
                                            <input type="radio" name="mode" value="" @checked(! $learningMode)>
                                            <span>Tất cả</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="mode" value="ONLINE" @checked($learningMode === 'ONLINE')>
                                            <span>Online</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="mode" value="OFFLINE" @checked($learningMode === 'OFFLINE')>
                                            <span>Tại nhà</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div class="request-filter-field request-combobox" data-combobox>
                                    <label for="request-province"><x-directory-icon name="location" /> Khu vực</label>
                                    <div class="combobox-control">
                                        <input id="request-province" type="text" value="{{ $provinceId ? $locations->firstWhere('province_id', $provinceId)?->province_name : '' }}" placeholder="Tất cả tỉnh/thành" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="request-province-options" data-combobox-input>
                                        <button class="combobox-toggle" type="button" aria-label="Mở danh sách tỉnh thành" aria-expanded="false" aria-controls="request-province-options" data-combobox-toggle><x-directory-icon name="chevron" /></button>
                                    </div>
                                    <div class="combobox-options" id="request-province-options" role="listbox" hidden>
                                        <button type="button" role="option" data-value="" data-label="Tất cả tỉnh/thành">Tất cả tỉnh/thành</button>
                                        @foreach ($locations as $location)
                                            <button type="button" role="option" data-value="{{ $location->province_id }}" data-label="{{ $location->province_name }}">{{ $location->province_name }}</button>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="province" value="{{ $provinceId }}" data-combobox-value>
                                </div>

                                <div class="request-filter-field request-fee-fields">
                                    <p class="request-field-label"><x-directory-icon name="wallet" /> Mức học phí / giờ</p>
                                    <div><label for="request-min-fee">Từ</label><input id="request-min-fee" name="min_fee" type="number" min="0" step="10000" value="{{ $minFee }}" placeholder="150000"></div>
                                    <div><label for="request-max-fee">Đến</label><input id="request-max-fee" name="max_fee" type="number" min="0" step="10000" value="{{ $maxFee }}" placeholder="300000"></div>
                                </div>

                                <div class="request-filter-field request-select-control">
                                    <label for="request-time"><x-directory-icon name="clock" /> Thời gian</label>
                                    <select id="request-time" name="time">
                                        <option value="">Tất cả khung giờ</option>
                                        <option value="morning" @selected($timeOfDay === 'morning')>Buổi sáng</option>
                                        <option value="afternoon" @selected($timeOfDay === 'afternoon')>Buổi chiều</option>
                                        <option value="evening" @selected($timeOfDay === 'evening')>Buổi tối</option>
                                    </select>
                                    <x-directory-icon name="chevron" />
                                </div>
                            </div>

                            <div class="requests-filter-actions">
                                <button class="button" type="submit"><x-directory-icon name="filter" /> Áp dụng bộ lọc</button>
                                @if ($activeFilterCount)
                                    <a href="{{ route('requests.index') }}">Xóa bộ lọc</a>
                                @endif
                            </div>
                        </form>
                    </aside>
                </details>

                <section class="requests-results" aria-label="Danh sách nhu cầu tìm gia sư">
                    <div class="requests-results-toolbar">
                        <p class="requests-results-count"><x-directory-icon name="list" /><strong>{{ $publicRequests->total() }}</strong> kết quả</p>
                        <form class="request-sort-form" action="{{ route('requests.index') }}" method="GET">
                            <label for="request-sort"><x-directory-icon name="sort" />Sắp xếp</label>
                            <div class="request-sort-control">
                                <select id="request-sort" name="sort" onchange="this.form.requestSubmit()">
                                    <option value="newest" @selected($sort === 'newest')>Mới nhất</option>
                                    <option value="oldest" @selected($sort === 'oldest')>Cũ nhất</option>
                                    <option value="fee_desc" @selected($sort === 'fee_desc')>Học phí cao nhất</option>
                                </select>
                                <x-directory-icon name="chevron" />
                            </div>
                            <noscript><button type="submit">Sắp xếp</button></noscript>
                            @foreach (['q' => $search, 'subject' => $subjectId, 'education_level' => $educationLevelId, 'subject_level' => $subjectLevelId, 'province' => $provinceId, 'mode' => $learningMode, 'min_fee' => $minFee, 'max_fee' => $maxFee, 'time' => $timeOfDay] as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                        </form>
                    </div>

                    @if ($publicRequests->isNotEmpty())
                        <div class="requests-grid">
                            @foreach ($publicRequests as $publicRequest)
                                <x-learning-request-card :request="$publicRequest" variant="directory" />
                            @endforeach
                        </div>

                        @if ($publicRequests->hasPages())
                            <nav class="request-pagination" aria-label="Phân trang danh sách lớp">
                                {{ $publicRequests->links() }}
                            </nav>
                        @endif
                    @else
                        <div class="requests-empty-state">
                            <span class="empty-mark"><x-directory-icon name="search" /></span>
                            <h3>Không tìm thấy lớp phù hợp</h3>
                            <p>Thử thay đổi môn học, khu vực hoặc hình thức học.</p>
                            <a class="button button-small" href="{{ route('requests.index') }}">Xóa bộ lọc</a>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </section>
</x-app-layout>
