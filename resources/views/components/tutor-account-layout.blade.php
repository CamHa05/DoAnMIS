<x-app-layout :title="$title ?? 'Khu vực gia sư | GiaSu'" body-class="tutor-area-shell">
    <div class="tutor-account-layout container">
        @if (auth()->user()?->tutorProfile()->exists())
            <x-tutor-account-sidebar />
        @else
            <x-learner-account-sidebar />
        @endif

        <div class="tutor-account-content">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
