@php
    $chartWidth = 720;
    $chartHeight = 240;
    $plotLeft = 44;
    $plotRight = 704;
    $plotTop = 20;
    $plotBottom = 188;
    $plotHeight = $plotBottom - $plotTop;
    $xStep = ($plotRight - $plotLeft) / 6;

    $chartPoints = function (string $series) use (
        $activityRows,
        $activityChartMax,
        $plotLeft,
        $plotBottom,
        $plotHeight,
        $xStep
    ): array {
        return $activityRows
            ->values()
            ->map(function (array $row, int $index) use (
                $series,
                $activityChartMax,
                $plotLeft,
                $plotBottom,
                $plotHeight,
                $xStep
            ): array {
                $x = $plotLeft + ($index * $xStep);
                $y = $plotBottom - (($row[$series] / $activityChartMax) * $plotHeight);

                return [
                    'x' => number_format($x, 2, '.', ''),
                    'y' => number_format($y, 2, '.', ''),
                    'value' => $row[$series],
                    'label' => $row['label'],
                ];
            })
            ->all();
    };

    $requestPoints = $chartPoints('requests');
    $classPoints = $chartPoints('classes');
    $requestPolyline = collect($requestPoints)->map(fn (array $point) => $point['x'].','.$point['y'])->join(' ');
    $classPolyline = collect($classPoints)->map(fn (array $point) => $point['x'].','.$point['y'])->join(' ');
    $chartIsEmpty = $activityRows->sum('requests') === 0 && $activityRows->sum('classes') === 0;
    $statisticRoutes = [
        'users' => route('admin.users.index'),
        'tutors' => route('admin.tutors.index', ['status' => 'approved']),
        'requests' => route('admin.requests.index'),
        'classes' => route('admin.classes.index'),
    ];
@endphp

<x-admin-layout title="Tổng quan" active-section="overview">
    <div class="admin-dashboard">
        <nav class="admin-breadcrumb admin-dashboard__breadcrumb" aria-label="Đường dẫn">
            <span aria-current="page">Tổng quan</span>
        </nav>

        <h1 class="admin-visually-hidden">Tổng quan</h1>

        <section class="admin-stats" aria-label="Số liệu tổng quan">
            @foreach ($statistics as $statistic)
                <a
                    class="admin-stat-card admin-stat-card--{{ $statistic['tone'] }}"
                    href="{{ $statisticRoutes[$statistic['key']] }}"
                    aria-label="Xem {{ $statistic['label'] }}"
                >
                    <span class="admin-stat-card__icon">
                        <x-directory-icon :name="$statistic['icon']" />
                    </span>
                    <div>
                        <p>{{ $statistic['label'] }}</p>
                        <strong data-admin-stat="{{ $statistic['key'] }}">
                            {{ number_format($statistic['value'], 0, ',', '.') }}
                        </strong>
                    </div>
                </a>
            @endforeach
        </section>

        <section class="admin-dashboard__analytics" aria-label="Biểu đồ hoạt động">
            <article class="admin-panel admin-panel--activity">
                <header class="admin-panel__header">
                    <div>
                        <h2>Hoạt động hệ thống</h2>
                        <p>Yêu cầu học và lớp học trong 7 ngày gần nhất</p>
                    </div>
                    <span class="admin-period-label">7 ngày gần nhất</span>
                </header>

                <figure class="admin-activity-chart" data-admin-activity-chart>
                    <div class="admin-chart__viewport">
                        <svg
                            viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                            role="img"
                            aria-label="Biểu đồ số yêu cầu học và lớp học được tạo trong 7 ngày gần nhất"
                        >
                        @for ($tick = 0; $tick <= 4; $tick++)
                            @php
                                $y = $plotTop + (($plotHeight / 4) * $tick);
                                $value = $activityChartMax - (($activityChartMax / 4) * $tick);
                            @endphp
                            <line
                                class="admin-chart__grid-line"
                                x1="{{ $plotLeft }}"
                                y1="{{ $y }}"
                                x2="{{ $plotRight }}"
                                y2="{{ $y }}"
                            />
                            <text class="admin-chart__axis-label" x="34" y="{{ $y + 4 }}" text-anchor="end">
                                {{ (int) $value }}
                            </text>
                        @endfor

                        @foreach ($activityRows as $index => $row)
                            @php
                                $x = $plotLeft + ($index * $xStep);
                            @endphp
                            <text class="admin-chart__date-label" x="{{ $x }}" y="218" text-anchor="middle">
                                {{ $row['label'] }}
                            </text>
                        @endforeach

                        <polyline class="admin-chart__line admin-chart__line--requests" points="{{ $requestPolyline }}" />
                        <polyline class="admin-chart__line admin-chart__line--classes" points="{{ $classPolyline }}" />

                        @foreach ($requestPoints as $point)
                            <g
                                class="admin-chart__point-target"
                                tabindex="0"
                                role="img"
                                aria-label="Ngày {{ $point['label'] }}: {{ $point['value'] }} yêu cầu học"
                                data-admin-chart-point
                                data-tooltip="{{ $point['label'] }} · {{ $point['value'] }} yêu cầu học"
                            >
                                <circle class="admin-chart__point-hit" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="26" />
                                <circle
                                    class="admin-chart__point admin-chart__point--requests"
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="4.5"
                                />
                            </g>
                        @endforeach

                        @foreach ($classPoints as $point)
                            <g
                                class="admin-chart__point-target"
                                tabindex="0"
                                role="img"
                                aria-label="Ngày {{ $point['label'] }}: {{ $point['value'] }} lớp học"
                                data-admin-chart-point
                                data-tooltip="{{ $point['label'] }} · {{ $point['value'] }} lớp học"
                            >
                                <circle class="admin-chart__point-hit" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="26" />
                                <circle
                                    class="admin-chart__point admin-chart__point--classes"
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="4"
                                />
                            </g>
                        @endforeach
                        </svg>
                    </div>

                    @if ($chartIsEmpty)
                        <p class="admin-chart__empty">Chưa có hoạt động mới trong 7 ngày gần nhất.</p>
                    @endif

                    <div class="admin-chart__tooltip" id="admin-chart-tooltip" role="tooltip" hidden></div>

                    <figcaption class="admin-chart__legend">
                        <span><i class="admin-chart__legend-mark admin-chart__legend-mark--requests"></i>Yêu cầu học</span>
                        <span><i class="admin-chart__legend-mark admin-chart__legend-mark--classes"></i>Lớp học</span>
                    </figcaption>
                </figure>
            </article>

            <article class="admin-panel admin-panel--status">
                <header class="admin-panel__header">
                    <div>
                        <h2>Trạng thái hồ sơ gia sư</h2>
                    </div>
                </header>

                @if ($tutorStatusTotal > 0)
                    <div class="admin-status-chart">
                        <div class="admin-donut">
                            <svg viewBox="0 0 120 120" role="img" aria-label="Phân bổ trạng thái của {{ $tutorStatusTotal }} hồ sơ gia sư">
                                <circle class="admin-donut__track" cx="60" cy="60" r="46" pathLength="100" />
                                @php
                                    $donutOffset = 0;
                                @endphp
                                @foreach ($tutorStatusBreakdown as $statusItem)
                                    @php
                                        $segment = ($statusItem['count'] / $tutorStatusTotal) * 100;
                                    @endphp
                                    <circle
                                        class="admin-donut__segment admin-donut__segment--{{ $statusItem['tone'] }}"
                                        cx="60"
                                        cy="60"
                                        r="46"
                                        pathLength="100"
                                        stroke-dasharray="{{ number_format($segment, 4, '.', '') }} {{ number_format(100 - $segment, 4, '.', '') }}"
                                        stroke-dashoffset="{{ number_format(-$donutOffset, 4, '.', '') }}"
                                    />
                                    @php
                                        $donutOffset += $segment;
                                    @endphp
                                @endforeach
                            </svg>
                            <span>
                                <strong>{{ number_format($tutorStatusTotal, 0, ',', '.') }}</strong>
                                <small>Hồ sơ</small>
                            </span>
                        </div>

                        <ul class="admin-status-legend">
                            @foreach ($tutorStatusBreakdown as $statusItem)
                                <li>
                                    <span class="admin-status-legend__label">
                                        <i class="admin-status-dot admin-status-dot--{{ $statusItem['tone'] }}"></i>
                                        {{ $statusItem['label'] }}
                                    </span>
                                    <strong>{{ number_format($statusItem['count'], 0, ',', '.') }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="admin-panel__empty admin-panel__empty--chart">Chưa có dữ liệu hồ sơ gia sư.</div>
                @endif
            </article>
        </section>

        <section class="admin-panel" aria-labelledby="pending-tutors-title">
            <header class="admin-panel__header admin-panel__header--table">
                <div>
                    <h2 id="pending-tutors-title">Gia sư chờ xét duyệt</h2>
                </div>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table admin-table--pending">
                    <thead>
                        <tr>
                            <th scope="col">Họ và tên</th>
                            <th scope="col">Chuyên môn</th>
                            <th scope="col">Kinh nghiệm</th>
                            <th scope="col">Ngày gửi</th>
                            <th scope="col">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingTutors as $tutor)
                            @php
                                $subjects = $tutor->tutorSubjects
                                    ->pluck('subject.subject_name')
                                    ->filter()
                                    ->unique()
                                    ->join(', ');
                            @endphp
                            <tr>
                                <td>
                                    <a
                                        class="admin-table__person admin-dashboard__record-link"
                                        href="{{ route('admin.tutors.show', $tutor) }}"
                                        aria-label="Xem hồ sơ gia sư {{ $tutor->user?->full_name ?: 'chưa cập nhật' }}"
                                    >
                                        <x-tutor-avatar :user="$tutor->user" />
                                        <strong>{{ $tutor->user?->full_name ?: 'Chưa cập nhật' }}</strong>
                                    </a>
                                </td>
                                <td>{{ $subjects ?: 'Chưa cập nhật' }}</td>
                                <td>{{ filled($tutor->teaching_experience) ? \Illuminate\Support\Str::limit($tutor->teaching_experience, 64) : 'Chưa cung cấp' }}</td>
                                <td class="admin-table__number">{{ $tutor->submitted_at->format('d/m/Y') }}</td>
                                <td>
                                    <span class="admin-status-badge admin-status-badge--pending">
                                        <x-directory-icon name="clock" data-status-icon="clock" />
                                        <span>Chờ duyệt</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty" colspan="5">Chưa có hồ sơ đang chờ xét duyệt.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-dashboard__recent" aria-label="Dữ liệu gần đây">
            <article class="admin-panel">
                <header class="admin-panel__header admin-panel__header--table">
                    <div>
                        <h2>Yêu cầu học gần đây</h2>
                    </div>
                </header>

                <div class="admin-table-wrap">
                    <table class="admin-table admin-table--compact">
                        <thead>
                            <tr>
                                <th scope="col">Người học</th>
                                <th scope="col">Môn học</th>
                                <th scope="col">Hình thức</th>
                                <th scope="col">Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentRequests as $learningRequest)
                                <tr>
                                    <td>
                                        @if ($learningRequest->user)
                                            <a
                                                class="admin-table__person admin-dashboard__record-link"
                                                href="{{ route('admin.users.show', $learningRequest->user) }}"
                                                aria-label="Xem người dùng {{ $learningRequest->user->full_name ?: 'chưa cập nhật' }}"
                                            >
                                                <x-tutor-avatar :user="$learningRequest->user" />
                                                <strong>{{ $learningRequest->user->full_name ?: 'Chưa cập nhật' }}</strong>
                                            </a>
                                        @else
                                            <span class="admin-table__person">
                                                <x-tutor-avatar :user="null" />
                                                <strong>Chưa cập nhật</strong>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $learningRequest->subjectLevel?->subject?->subject_name ?: 'Chưa cập nhật' }}</td>
                                    <td>{{ $learningRequest->learningModeLabel() }}</td>
                                    <td class="admin-table__number">{{ $learningRequest->created_at?->format('d/m/Y') ?: 'Chưa cập nhật' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="admin-table__empty" colspan="4">Chưa có yêu cầu học.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="admin-panel">
                <header class="admin-panel__header admin-panel__header--table">
                    <div>
                        <h2>Lớp học gần đây</h2>
                    </div>
                </header>

                <div class="admin-table-wrap">
                    <table class="admin-table admin-table--compact">
                        <thead>
                            <tr>
                                <th scope="col">Gia sư</th>
                                <th scope="col">Học viên</th>
                                <th scope="col">Môn học</th>
                                <th scope="col">Ngày bắt đầu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentClasses as $class)
                                @php
                                    $contract = $class->contract;
                                    $learningRequest = $contract?->tutoringRequest;
                                    $tutorUser = $contract?->tutorProfile?->user;
                                    $learnerUser = $learningRequest?->user;
                                @endphp
                                <tr>
                                    <td>
                                        @if ($tutorUser)
                                            <a
                                                class="admin-table__person admin-dashboard__record-link"
                                                href="{{ route('admin.users.show', $tutorUser) }}"
                                                aria-label="Xem người dùng {{ $tutorUser->full_name ?: 'chưa cập nhật' }}"
                                            >
                                                <x-tutor-avatar :user="$tutorUser" />
                                                <strong>{{ $tutorUser->full_name ?: 'Chưa cập nhật' }}</strong>
                                            </a>
                                        @else
                                            <span class="admin-table__person">
                                                <x-tutor-avatar :user="null" />
                                                <strong>Chưa cập nhật</strong>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($learnerUser)
                                            <a
                                                class="admin-dashboard__name-link"
                                                href="{{ route('admin.users.show', $learnerUser) }}"
                                            >{{ $learnerUser->full_name ?: 'Chưa cập nhật' }}</a>
                                        @else
                                            Chưa cập nhật
                                        @endif
                                    </td>
                                    <td>{{ $learningRequest?->subjectLevel?->subject?->subject_name ?: 'Chưa cập nhật' }}</td>
                                    <td class="admin-table__number">{{ $class->start_date?->format('d/m/Y') ?: 'Chưa cập nhật' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="admin-table__empty" colspan="4">Chưa có lớp học.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-admin-layout>
