@props(['subject', 'href', 'accent' => 'teal'])
@php($accent = ['teal', 'coral', 'gold', 'ink'][$subject->subject_id % 4])
<a class="subject-item subject-{{ $accent }}" href="{{ $href }}">
    <span class="subject-icon">{{ mb_substr($subject->subject_name, 0, 1) }}</span>
    <span class="subject-copy">
        <strong>{{ $subject->subject_name }}</strong>
        <small>{{ $subject->approved_tutors_count > 0 ? $subject->approved_tutors_count . ' gia sư' : 'Chưa có gia sư' }}</small>
    </span>
    <x-directory-icon class="subject-arrow" name="arrow" />
</a>
