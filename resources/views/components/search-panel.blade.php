@props([
    'subjects' => collect(),
    'locations' => collect(),
])

<form
    class="search-panel"
    id="search-form"
    action="{{ route('home') }}#featured-tutors"
    method="GET"
    aria-label="Tìm kiếm gia sư"
>
    <div class="search-field">
        <label for="homepage-subject-search">Bạn muốn học môn gì?</label>

        <div class="select-look homepage-combobox" data-combobox>
            <x-directory-icon class="field-icon" name="search" />
            <div class="combobox-control">
                <input
                    id="homepage-subject-search"
                    type="text"
                    value="{{ request('subject') ? $subjects->firstWhere('subject_id', request('subject'))?->subject_name : '' }}"
                    placeholder="Tất cả môn học"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="homepage-subject-options"
                    data-combobox-input
                >
                <button
                    class="combobox-toggle"
                    type="button"
                    aria-label="Mở danh sách môn học"
                    aria-expanded="false"
                    data-combobox-toggle
                ><x-directory-icon name="chevron" /></button>
            </div>
            <div
                class="combobox-options"
                id="homepage-subject-options"
                role="listbox"
                hidden
            >
                <button type="button" role="option" data-value="" data-label="Tất cả môn học">
                    Tất cả môn học
                </button>
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
            <input
                type="hidden"
                name="subject"
                value="{{ request('subject') }}"
                data-combobox-value
            >
        </div>
    </div>

    <div class="search-field">
        <label for="homepage-location-search">Bạn muốn học ở đâu?</label>

        <div class="select-look homepage-combobox" data-combobox>
            <x-directory-icon class="field-icon" name="location" />
            <div class="combobox-control">
                <input
                    id="homepage-location-search"
                    type="text"
                    value="{{ request('location') === 'online' ? 'Học Online' : ($locations->firstWhere('province_id', request('location'))?->province_name ?? '') }}"
                    placeholder="Online hoặc tại nhà"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="homepage-location-options"
                    data-combobox-input
                >
                <button
                    class="combobox-toggle"
                    type="button"
                    aria-label="Mở danh sách khu vực"
                    aria-expanded="false"
                    data-combobox-toggle
                ><x-directory-icon name="chevron" /></button>
            </div>
            <div
                class="combobox-options"
                id="homepage-location-options"
                role="listbox"
                hidden
            >
                <button type="button" role="option" data-value="" data-label="Online hoặc tại nhà">
                    Online hoặc tại nhà
                </button>
                <button type="button" role="option" data-value="online" data-label="Học Online">
                    Học Online
                </button>
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
            <input
                type="hidden"
                name="location"
                value="{{ request('location') }}"
                data-combobox-value
            >
        </div>
    </div>

    <button class="button search-button" type="submit">
        Tìm gia sư
        <x-directory-icon name="arrow" />
    </button>
</form>
