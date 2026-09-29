@php
    $tabs = [
        'all' => 'Tất cả hồ sơ đã gửi',
        'pending' => 'Chờ xét duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'changes' => 'Minh chứng chờ duyệt',
    ];

    $statusMeta = [
        \App\Models\TutorProfile::STATUS_PENDING => [
            'label' => 'Chờ xét duyệt',
            'class' => 'pending',
            'icon' => 'clock',
        ],
        \App\Models\TutorProfile::STATUS_APPROVED => [
            'label' => 'Đã duyệt',
            'class' => 'approved',
            'icon' => 'circle-check',
        ],
        \App\Models\TutorProfile::STATUS_REJECTED => [
            'label' => 'Từ chối',
            'class' => 'rejected',
            'icon' => 'x-circle',
        ],
    ];
@endphp

<x-admin-layout title="Gia sư" active-section="tutors">
    <div class="admin-tutors">
        <nav class="admin-breadcrumb admin-tutor-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Gia sư</span>
        </nav>

        <h1 class="admin-visually-hidden">Gia sư</h1>

        <nav class="admin-tutor-tabs" aria-label="Lọc hồ sơ và thay đổi cần xét duyệt">
            @foreach ($tabs as $tabKey => $tabLabel)
                @php
                    $tabQuery = $filters;

                    if ($tabKey === 'all') {
                        unset($tabQuery['status']);
                    } else {
                        $tabQuery['status'] = $tabKey;
                    }
                @endphp
                <a
                    @class([
                        'admin-tutor-tabs__item',
                        'is-active' => $status === $tabKey,
                    ])
                    href="{{ route('admin.tutors.index', $tabQuery) }}"
                    @if ($status === $tabKey) aria-current="page" @endif
                >
                    <span>{{ $tabLabel }}</span>
                    <strong>{{ number_format($statusCounts[$tabKey], 0, ',', '.') }}</strong>
                </a>
            @endforeach
        </nav>

        <section class="admin-tutor-directory" aria-label="Danh sách hồ sơ gia sư">
            <form class="admin-tutor-filters" method="GET" action="{{ route('admin.tutors.index') }}">
                @if ($status !== 'all')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif

                <label class="admin-tutor-search" for="admin-tutor-search">
                    <span class="admin-visually-hidden">Tìm theo tên hoặc email</span>
                    <x-directory-icon name="search" />
                    <input
                        id="admin-tutor-search"
                        name="q"
                        type="search"
                        value="{{ $search }}"
                        placeholder="Tìm theo tên hoặc email…"
                        maxlength="100"
                    >
                </label>

                <label class="admin-tutor-select">
                    <span class="admin-visually-hidden">Chuyên môn</span>
                    <select name="subject">
                        <option value="">Chuyên môn</option>
                        @foreach ($subjects as $subject)
                            <option
                                value="{{ $subject->subject_id }}"
                                @selected($subjectId === (int) $subject->subject_id)
                            >
                                {{ $subject->subject_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-tutor-select">
                    <span class="admin-visually-hidden">Hình thức dạy</span>
                    <select name="mode">
                        <option value="">Hình thức dạy</option>
                        <option value="online" @selected($mode === 'online')>Online</option>
                        <option value="offline" @selected($mode === 'offline')>Tại nhà</option>
                        <option value="both" @selected($mode === 'both')>Online &amp; tại nhà</option>
                    </select>
                </label>

                <label class="admin-tutor-select admin-tutor-select--sort">
                    <span class="admin-visually-hidden">Sắp xếp</span>
                    <select name="sort">
                        <option value="newest" @selected($sort === 'newest')>Sắp xếp: Mới nhất</option>
                        <option value="oldest" @selected($sort === 'oldest')>Sắp xếp: Cũ nhất</option>
                    </select>
                </label>

                <button class="admin-tutor-filters__submit" type="submit">
                    <x-directory-icon name="filter" />
                    <span>Áp dụng</span>
                </button>

                <a class="admin-tutor-filters__reset" href="{{ route('admin.tutors.index') }}">
                    Đặt lại
                </a>
            </form>

            @if ($tutors->isNotEmpty())
                <div class="admin-table-wrap admin-tutor-table-wrap">
                    <table class="admin-table admin-tutors__table">
                        <thead>
                            <tr>
                                <th scope="col">Gia sư</th>
                                <th scope="col">Chuyên môn</th>
                                <th scope="col">Hình thức</th>
                                <th scope="col">{{ $status === 'changes' ? 'Minh chứng' : 'Kinh nghiệm' }}</th>
                                <th scope="col">{{ $status === 'changes' ? 'Gửi thay đổi' : 'Gửi xét duyệt' }}</th>
                                <th scope="col">{{ $status === 'changes' ? 'Yêu cầu chờ' : 'Trạng thái' }}</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tutors as $tutor)
                                @php
                                    $subjectsForTutor = $tutor->tutorSubjects
                                        ->pluck('subject.subject_name')
                                        ->filter()
                                        ->unique()
                                        ->values();
                                    $visibleSubjects = $subjectsForTutor->take(2);
                                    $hiddenSubjectCount = max(0, $subjectsForTutor->count() - 2);
                                    $modeLabel = match (true) {
                                        $tutor->supports_online && $tutor->supports_offline => 'Online & tại nhà',
                                        $tutor->supports_online => 'Online',
                                        $tutor->supports_offline => 'Tại nhà',
                                        default => 'Chưa cập nhật',
                                    };
                                    $profileStatus = $statusMeta[$tutor->approval_status] ?? [
                                        'label' => $tutor->approval_status,
                                        'class' => 'neutral',
                                        'icon' => 'info',
                                    ];
                                    $pendingChanges = $tutor->changeRequests;
                                    $latestPendingChange = $pendingChanges
                                        ->sortByDesc('submitted_at')
                                        ->first();
                                @endphp
                                <tr>
                                    <td>
                                        <span class="admin-tutor-person">
                                            <x-tutor-avatar :user="$tutor->user" />
                                            <span>
                                                <strong>{{ $tutor->user?->full_name ?: 'Chưa cập nhật' }}</strong>
                                                <small>{{ $tutor->user?->email ?: 'Chưa cập nhật' }}</small>
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        @if ($visibleSubjects->isNotEmpty())
                                            <span class="admin-tutor-subjects">
                                                @foreach ($visibleSubjects as $subjectName)
                                                    <span>{{ $subjectName }}</span>
                                                @endforeach
                                                @if ($hiddenSubjectCount > 0)
                                                    <small>+{{ $hiddenSubjectCount }}</small>
                                                @endif
                                            </span>
                                        @else
                                            <span class="admin-table__muted">Chưa cập nhật</span>
                                        @endif
                                    </td>
                                    <td><span class="admin-tutor-mode">{{ $modeLabel }}</span></td>
                                    <td>
                                        @if ($status === 'changes')
                                            <span class="admin-tutor-experience">
                                                {{ $pendingChanges->pluck('change_action')->map(fn ($action) => match ($action) {
                                                    \App\Models\TutorProfileChangeRequest::ACTION_ADD => 'Thêm mới',
                                                    \App\Models\TutorProfileChangeRequest::ACTION_DELETE => 'Yêu cầu xóa',
                                                    \App\Models\TutorProfileChangeRequest::ACTION_REPLACE => 'Thay thế',
                                                    default => 'Cập nhật',
                                                })->unique()->join(', ') }}
                                            </span>
                                        @else
                                            <span
                                                class="admin-tutor-experience"
                                                @if (filled($tutor->teaching_experience)) title="{{ $tutor->teaching_experience }}" @endif
                                            >
                                                {{ filled($tutor->teaching_experience) ? \Illuminate\Support\Str::limit($tutor->teaching_experience, 54) : 'Chưa cập nhật' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="admin-table__number">
                                        @php
                                            $submittedAt = $status === 'changes'
                                                ? $latestPendingChange?->submitted_at
                                                : $tutor->submitted_at;
                                        @endphp
                                        @if ($submittedAt)
                                            <time datetime="{{ $submittedAt->toIso8601String() }}">
                                                <strong>{{ $submittedAt->format('d/m/Y') }}</strong>
                                                <small>{{ $submittedAt->format('H:i') }}</small>
                                            </time>
                                        @else
                                            <span class="admin-table__muted">Chưa xác định</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($status === 'changes')
                                            <span class="admin-status-badge admin-status-badge--pending">
                                                <x-directory-icon name="clock" data-status-icon="clock" />
                                                <span>{{ $pendingChanges->count() }} thay đổi</span>
                                            </span>
                                        @else
                                            <span class="admin-status-badge admin-status-badge--{{ $profileStatus['class'] }}">
                                                <x-directory-icon
                                                    :name="$profileStatus['icon']"
                                                    data-status-icon="{{ $profileStatus['icon'] }}"
                                                />
                                                <span>{{ $profileStatus['label'] }}</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <a
                                            class="admin-tutor-view"
                                            href="{{ route('admin.tutors.show', $tutor) }}"
                                        >
                                            {{ $status === 'changes' ? 'Xét duyệt' : 'Xem hồ sơ' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="admin-tutor-empty">
                    <strong>{{ $status === 'changes' ? 'Không có minh chứng chờ duyệt' : 'Không có hồ sơ phù hợp' }}</strong>
                    <p>{{ $status === 'changes' ? 'Các thay đổi minh chứng mới sẽ xuất hiện tại đây.' : 'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.' }}</p>
                </div>
            @endif

            <footer class="admin-tutor-pagination">
                <p>
                    Hiển thị
                    <strong>{{ $tutors->firstItem() ?? 0 }}–{{ $tutors->lastItem() ?? 0 }}</strong>
                    trong <strong>{{ number_format($tutors->total(), 0, ',', '.') }}</strong> hồ sơ
                </p>

                @if ($tutors->hasPages())
                    @php
                        $pageStart = max(1, $tutors->currentPage() - 1);
                        $pageEnd = min($tutors->lastPage(), $tutors->currentPage() + 1);
                    @endphp
                    <nav class="admin-tutor-pagination__links" aria-label="Phân trang hồ sơ gia sư">
                        @if ($tutors->onFirstPage())
                            <span aria-disabled="true" aria-label="Trang trước">
                                <x-directory-icon name="arrow-left" />
                            </span>
                        @else
                            <a href="{{ $tutors->previousPageUrl() }}" rel="prev" aria-label="Trang trước">
                                <x-directory-icon name="arrow-left" />
                            </a>
                        @endif

                        @foreach ($tutors->getUrlRange($pageStart, $pageEnd) as $page => $url)
                            <a
                                @class(['is-active' => $page === $tutors->currentPage()])
                                href="{{ $url }}"
                                @if ($page === $tutors->currentPage()) aria-current="page" @endif
                            >
                                {{ $page }}
                            </a>
                        @endforeach

                        @if ($tutors->hasMorePages())
                            <a href="{{ $tutors->nextPageUrl() }}" rel="next" aria-label="Trang sau">
                                <x-directory-icon name="chevron-right" />
                            </a>
                        @else
                            <span aria-disabled="true" aria-label="Trang sau">
                                <x-directory-icon name="chevron-right" />
                            </span>
                        @endif
                    </nav>
                @endif
            </footer>
        </section>
    </div>
</x-admin-layout>
