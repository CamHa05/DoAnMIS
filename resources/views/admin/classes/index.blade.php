@php
    $statusMeta = [
        'ACTIVE' => ['label' => 'Đang hoạt động', 'class' => 'active'],
        'COMPLETED' => ['label' => 'Hoàn thành', 'class' => 'completed'],
    ];
    $statusLabels = collect($statusMeta)->mapWithKeys(
        fn ($meta, $key) => [$key => $meta['label']]
    );
    $statCards = [
        ['key' => 'total', 'label' => 'Tổng lớp học', 'icon' => 'users'],
        ['key' => 'active', 'label' => 'Đang hoạt động', 'icon' => 'send'],
        ['key' => 'completed', 'label' => 'Hoàn thành', 'icon' => 'circle-check'],
    ];
@endphp

<x-admin-layout title="Lớp học" active-section="classes">
    <div class="admin-classes-page">
        <nav class="admin-breadcrumb admin-class-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Lớp học</span>
        </nav>

        <h1 class="admin-visually-hidden">Quản lý lớp học</h1>

        <section class="admin-class-stats" aria-label="Thống kê lớp học">
            @foreach ($statCards as $card)
                <article class="admin-class-stat admin-class-stat--{{ $card['key'] }}">
                    <span class="admin-class-stat__icon">
                        <x-directory-icon :name="$card['icon']" />
                    </span>
                    <span>
                        <span>{{ $card['label'] }}</span>
                        <strong>{{ number_format($statistics[$card['key']], 0, ',', '.') }}</strong>
                    </span>
                </article>
            @endforeach
        </section>

        <section class="admin-class-filter-panel" aria-labelledby="admin-class-filter-title">
            <h2 id="admin-class-filter-title">
                <x-directory-icon name="filter" />
                <span>Bộ lọc</span>
            </h2>

            <form class="admin-class-filters" method="GET" action="{{ route('admin.classes.index') }}">
                <label class="admin-class-search" for="admin-class-search">
                    <span class="admin-visually-hidden">Tìm theo mã lớp, tên lớp, người học hoặc gia sư</span>
                    <x-directory-icon name="search" />
                    <input
                        id="admin-class-search"
                        name="q"
                        type="search"
                        value="{{ $search }}"
                        placeholder="Tìm theo mã lớp, tên lớp, người học hoặc gia sư…"
                        maxlength="100"
                    >
                </label>

                <label class="admin-class-select">
                    <span class="admin-visually-hidden">Trạng thái</span>
                    <select name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach ($statusOptions as $statusOption)
                            <option value="{{ strtolower($statusOption) }}" @selected($status === $statusOption)>
                                {{ $statusLabels->get($statusOption, $statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-class-select">
                    <span class="admin-visually-hidden">Hình thức học</span>
                    <select name="mode">
                        <option value="">Tất cả hình thức</option>
                        <option value="online" @selected($learningMode === 'ONLINE')>Online</option>
                        <option value="offline" @selected($learningMode === 'OFFLINE')>Trực tiếp</option>
                    </select>
                </label>

                <label class="admin-class-select">
                    <span class="admin-visually-hidden">Môn học</span>
                    <select name="subject">
                        <option value="">Tất cả môn học</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->subject_id }}" @selected($subjectId === (int) $subject->subject_id)>
                                {{ $subject->subject_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                @if (isset($filters['user']))
                    <input type="hidden" name="user" value="{{ $filters['user'] }}">
                @endif

                <button class="admin-class-filters__submit" type="submit">
                    <x-directory-icon name="filter" />
                    <span>Lọc</span>
                </button>

                <a class="admin-class-filters__reset" href="{{ route('admin.classes.index') }}">
                    <x-directory-icon name="arrow-left" />
                    <span>Đặt lại</span>
                </a>
            </form>
        </section>

        <section class="admin-class-directory" aria-labelledby="admin-class-list-title">
            <h2 id="admin-class-list-title">
                <x-directory-icon name="document" />
                <span>Danh sách lớp học</span>
            </h2>

            @if ($classes->isNotEmpty())
                <div class="admin-class-table-wrap">
                    <table class="admin-class-table">
                        <thead>
                            <tr>
                                <th scope="col">Lớp học</th>
                                <th scope="col">Người học</th>
                                <th scope="col">Gia sư</th>
                                <th scope="col">Môn học / Cấp độ</th>
                                <th scope="col">Hình thức</th>
                                <th scope="col">Thời gian</th>
                                <th scope="col">Lịch học</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($classes as $class)
                                @php
                                    $contract = $class->contract;
                                    $learningRequest = $contract?->tutoringRequest;
                                    $learner = $learningRequest?->user;
                                    $tutor = $contract?->tutorProfile?->user;
                                    $subjectLevel = $learningRequest?->subjectLevel;
                                    $subjectName = $subjectLevel?->subject?->subject_name;
                                    $levelName = $subjectLevel?->level_name
                                        ?: $subjectLevel?->educationLevel?->level_name;
                                    $classStatus = strtoupper((string) $class->status);
                                    $statusDisplay = $statusMeta[$classStatus] ?? [
                                        'label' => $classStatus !== '' ? $classStatus : 'Chưa xác định',
                                        'class' => 'neutral',
                                    ];
                                    $mode = strtoupper((string) $contract?->learning_mode);
                                    $location = collect([
                                        $learningRequest?->ward?->ward_name,
                                        $learningRequest?->ward?->province?->province_name,
                                    ])->filter()->unique()->implode(', ');
                                @endphp
                                <tr>
                                    <td data-label="Lớp học">
                                        <span class="admin-class-code">
                                            <strong>#CLS-{{ str_pad((string) $class->class_id, 3, '0', STR_PAD_LEFT) }}</strong>
                                            <small>{{ $class->displayName($subjectLevel) }}</small>
                                        </span>
                                    </td>
                                    <td data-label="Người học">
                                        <span class="admin-class-person">
                                            <x-tutor-avatar :user="$learner" />
                                            <strong>{{ $learner?->full_name ?: 'Chưa cập nhật' }}</strong>
                                        </span>
                                    </td>
                                    <td data-label="Gia sư">
                                        <span class="admin-class-person">
                                            <x-tutor-avatar :user="$tutor" />
                                            <strong>{{ $tutor?->full_name ?: 'Chưa cập nhật' }}</strong>
                                        </span>
                                    </td>
                                    <td data-label="Môn học / Cấp độ">
                                        <span class="admin-class-subject">
                                            <strong>{{ $subjectName ?: 'Chưa cập nhật' }}</strong>
                                            <small>{{ $levelName ?: 'Chưa cập nhật' }}</small>
                                        </span>
                                    </td>
                                    <td data-label="Hình thức">
                                        <span class="admin-class-mode admin-class-mode--{{ strtolower($mode) }}">
                                            {{ $mode === 'ONLINE' ? 'Online' : ($mode === 'OFFLINE' ? 'Trực tiếp' : 'Chưa xác định') }}
                                        </span>
                                        @if ($mode === 'OFFLINE' && $location !== '')
                                            <small class="admin-class-location">
                                                <x-directory-icon name="map-pin" />
                                                {{ $location }}
                                            </small>
                                        @endif
                                    </td>
                                    <td data-label="Thời gian">
                                        <span class="admin-class-period">
                                            <time datetime="{{ $class->start_date?->format('Y-m-d') }}">
                                                {{ $class->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}
                                            </time>
                                            <span aria-hidden="true">–</span>
                                            @if ($class->end_date)
                                                <time datetime="{{ $class->end_date->format('Y-m-d') }}">{{ $class->end_date->format('d/m/Y') }}</time>
                                            @else
                                                <span>Chưa cập nhật</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td data-label="Lịch học">
                                        <span class="admin-class-schedule-count">{{ $class->schedules_count }} buổi / tuần</span>
                                    </td>
                                    <td data-label="Trạng thái">
                                        <span class="admin-class-status admin-class-status--{{ $statusDisplay['class'] }}">
                                            <span aria-hidden="true"></span>
                                            {{ $statusDisplay['label'] }}
                                        </span>
                                    </td>
                                    <td data-label="Thao tác">
                                        <a class="admin-class-view" href="{{ route('admin.classes.show', $class) }}">
                                            <x-directory-icon name="eye" />
                                            <span>Xem chi tiết</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="admin-class-empty">
                    <x-directory-icon name="search" />
                    <strong>Không có lớp học phù hợp</strong>
                    <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</p>
                </div>
            @endif

            <footer class="admin-class-pagination">
                <p>
                    Hiển thị <strong>{{ $classes->firstItem() ?? 0 }}–{{ $classes->lastItem() ?? 0 }}</strong>
                    trong <strong>{{ number_format($classes->total(), 0, ',', '.') }}</strong> lớp học
                </p>

                @if ($classes->hasPages())
                    {{ $classes->onEachSide(1)->links() }}
                @endif
            </footer>
        </section>
    </div>
</x-admin-layout>
