@php
    $tabs = [
        ['label' => 'Tất cả', 'value' => null],
        ['label' => 'Đang chờ', 'value' => \App\Models\TutorApplication::STATUS_PENDING],
        ['label' => 'Được chọn', 'value' => \App\Models\TutorApplication::STATUS_ACCEPTED],
        ['label' => 'Không được chọn', 'value' => \App\Models\TutorApplication::STATUS_REJECTED],
        ['label' => 'Hết hạn', 'value' => \App\Models\TutorApplication::STATUS_EXPIRED],
    ];
@endphp

<x-tutor-account-layout title="Ứng tuyển của tôi | GiaSu">
    <section class="tutor-applications-page" aria-labelledby="applications-title">
        <header class="tutor-applications-heading">
            <div>
                <h1 id="applications-title">Ứng tuyển của tôi</h1>
                <p>Theo dõi các yêu cầu học bạn đã ứng tuyển và trạng thái phản hồi từ người học.</p>
            </div>
            <span class="tutor-applications-count">{{ $totalApplicationCount }} ứng tuyển</span>
        </header>

        <nav class="tutor-application-tabs" aria-label="Lọc ứng tuyển">
            @foreach ($tabs as $tab)
                @php($isActive = $status === $tab['value'])
                @php($tabCount = $tab['value'] === null ? $totalApplicationCount : (int) ($statusCounts[$tab['value']] ?? 0))
                <a
                    @class(['is-active' => $isActive])
                    href="{{ $tab['value'] ? route('tutor-area.applications', ['status' => $tab['value']]) : route('tutor-area.applications') }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $tab['label'] }}
                    ({{ $tabCount }})
                </a>
            @endforeach
        </nav>

        @if ($applications->isEmpty())
            <div class="tutor-applications-empty">
                <x-directory-icon name="file-check" />
                <h2>Bạn chưa có ứng tuyển nào.</h2>
                <p>Các yêu cầu học bạn đã ứng tuyển sẽ xuất hiện tại đây.</p>
            </div>
        @else
            <div class="tutor-application-list">
                @foreach ($applications as $application)
                    @include('tutor-area.partials.application-card', ['application' => $application])
                @endforeach
            </div>

            @if ($applications->hasPages())
                <nav class="tutor-application-pagination" aria-label="Phân trang ứng tuyển">
                    {{ $applications->links() }}
                </nav>
            @endif
        @endif
    </section>
</x-tutor-account-layout>