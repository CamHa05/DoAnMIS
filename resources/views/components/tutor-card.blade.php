@props(['tutor', 'variant' => 'default'])

@php
    $isDirectory = $variant === 'directory';
    $name = trim((string) ($tutor->user?->full_name ?: 'Gia sư'));
    $headline = trim((string) $tutor->headline);
    $subjects = $tutor->tutorSubjects
        ->pluck('subject.subject_name')
        ->filter()
        ->unique()
        ->take(2)
        ->join(' · ');
    $levels = $tutor->tutorSubjects
        ->flatMap(fn ($tutorSubject) => $tutorSubject->tutorSubjectLevels->pluck('subjectLevel.level_name'))
        ->filter()
        ->unique()
        ->take(3)
        ->join(', ');
    $areas = $tutor->teachingAreas
        ->map(fn ($area) => $area->ward?->province?->province_name)
        ->filter()
        ->unique()
        ->take(2)
        ->join(' · ');
    $modes = collect([
        $tutor->supports_online ? 'Online' : null,
        $tutor->supports_offline ? 'Tại nhà' : null,
    ])->filter()->join(' · ');
@endphp

<a
    class="tutor-card-link"
    href="{{ route('tutors.show', $tutor) }}"
    aria-label="Xem hồ sơ gia sư {{ $name }}"
>
    <article @class(['tutor-card', 'tutor-card-directory' => $isDirectory])>
        <div class="tutor-card-top">
            <x-tutor-avatar :user="$tutor->user" />

            @unless ($isDirectory)
            <span
                class="verified-badge"
                title="Hồ sơ đã được duyệt"
                aria-label="Hồ sơ đã được duyệt"
            >
                <x-directory-icon name="check" />
            </span>
            @endunless
        </div>

        <div class="tutor-card-body">
            <div class="tutor-name-row">
                <h3>{{ $name }}</h3>
                @if ($isDirectory)
                    <span class="directory-verified" role="img" title="Đã kiểm duyệt" aria-label="Đã kiểm duyệt"><x-directory-icon name="verified" /></span>
                @endif
            </div>

            @if ($headline)
                <p class="tutor-subject">{{ $headline }}</p>
            @endif

            @if ($subjects)
                <p class="tutor-subject">{{ $subjects }}</p>
            @endif

            <div class="tutor-details">
                @if ($levels)
                    <span>@if ($isDirectory)<x-directory-icon name="education" />@endif{{ $levels }}</span>
                @endif
                @if ($modes)
                    <span>@if ($isDirectory)<x-directory-icon name="mode" />@endif{{ $modes }}</span>
                @endif
                @if ($areas)
                    <span>@if ($isDirectory)<x-directory-icon name="location" />@endif{{ $areas }}</span>
                @endif
            </div>
        </div>

        @if ($isDirectory)
            <div class="tutor-card-bottom">
                @if ($tutor->hourly_rate !== null)
                    <strong>{{ number_format((float) $tutor->hourly_rate, 0, ',', '.') }}đ <small>/ giờ</small></strong>
                @else
                    <span>Chưa cập nhật học phí</span>
                @endif
                <span class="directory-card-cta">Xem hồ sơ <x-directory-icon name="arrow" /></span>
            </div>
        @elseif ($tutor->hourly_rate !== null)
            <div class="tutor-card-bottom">
                <strong>{{ number_format((float) $tutor->hourly_rate, 0, ',', '.') }}đ <small>/ giờ</small></strong>
                <span>Đã kiểm duyệt</span>
            </div>
        @endif
    </article>
</a>
