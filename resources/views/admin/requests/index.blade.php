@php
    $statusMeta = [
        'PENDING' => ['label' => 'Chờ phản hồi', 'class' => 'pending'],
        'OPEN' => ['label' => 'Đang mở', 'class' => 'open'],
        'MATCHED' => ['label' => 'Đã ghép gia sư', 'class' => 'matched'],
        'EXPIRED' => ['label' => 'Đã hết hạn', 'class' => 'expired'],
        'REJECTED' => ['label' => 'Đã từ chối', 'class' => 'rejected'],
    ];

    $statCards = [
        ['key' => 'total', 'label' => 'Tổng yêu cầu', 'note' => 'Tất cả yêu cầu học', 'icon' => 'document'],
        ['key' => 'pending', 'label' => 'Chờ phản hồi', 'note' => 'Yêu cầu gửi trực tiếp', 'icon' => 'clock'],
        ['key' => 'open', 'label' => 'Đang mở', 'note' => 'Yêu cầu công khai', 'icon' => 'send'],
        ['key' => 'matched', 'label' => 'Đã ghép gia sư', 'note' => 'Đã lựa chọn được gia sư', 'icon' => 'users'],
    ];
@endphp

<x-admin-layout title="Yêu cầu học" active-section="requests">
    <div class="admin-requests-page">
        <nav class="admin-breadcrumb admin-request-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Yêu cầu học</span>
        </nav>

        <h1 class="admin-visually-hidden">Quản lý yêu cầu học</h1>

        <section class="admin-request-stats" aria-label="Thống kê yêu cầu học">
            @foreach ($statCards as $card)
                <article class="admin-request-stat admin-request-stat--{{ $card['key'] }}">
                    <span class="admin-request-stat__icon">
                        <x-directory-icon :name="$card['icon']" />
                    </span>
                    <span>
                        <span class="admin-request-stat__label">{{ $card['label'] }}</span>
                        <small class="admin-request-stat__note">{{ $card['note'] }}</small>
                        <strong>{{ number_format($statistics[$card['key']], 0, ',', '.') }}</strong>
                    </span>
                </article>
            @endforeach
        </section>

        <section class="admin-request-directory" aria-labelledby="admin-request-list-title">
            <form class="admin-request-filters" method="GET" action="{{ route('admin.requests.index') }}">
                <label class="admin-request-search" for="admin-request-search">
                    <span class="admin-visually-hidden">Tìm theo mã yêu cầu hoặc người học</span>
                    <x-directory-icon name="search" />
                    <input
                        id="admin-request-search"
                        name="q"
                        type="search"
                        value="{{ $search }}"
                        placeholder="Tìm theo mã yêu cầu hoặc người học…"
                        maxlength="100"
                    >
                </label>

                <label class="admin-request-select">
                    <span class="admin-visually-hidden">Loại yêu cầu</span>
                    <select name="type">
                        <option value="">Tất cả loại yêu cầu</option>
                        <option value="public" @selected($requestType === 'public')>Công khai</option>
                        <option value="direct" @selected($requestType === 'direct')>Gửi trực tiếp</option>
                    </select>
                </label>

                <label class="admin-request-select">
                    <span class="admin-visually-hidden">Trạng thái</span>
                    <select name="status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending" @selected($status === 'pending')>Chờ phản hồi</option>
                        <option value="open" @selected($status === 'open')>Đang mở</option>
                        <option value="matched" @selected($status === 'matched')>Đã ghép gia sư</option>
                        <option value="expired" @selected($status === 'expired')>Đã hết hạn</option>
                    </select>
                </label>

                <label class="admin-request-select">
                    <span class="admin-visually-hidden">Hình thức học</span>
                    <select name="mode">
                        <option value="">Tất cả hình thức</option>
                        <option value="online" @selected($learningMode === 'online')>Online</option>
                        <option value="offline" @selected($learningMode === 'offline')>Trực tiếp</option>
                    </select>
                </label>

                <button class="admin-request-filters__submit" type="submit">
                    <x-directory-icon name="filter" />
                    <span>Lọc</span>
                </button>

                <a class="admin-request-filters__reset" href="{{ route('admin.requests.index') }}">
                    <x-directory-icon name="arrow-left" />
                    <span>Đặt lại</span>
                </a>
            </form>

            <header class="admin-request-directory__heading">
                <h2 id="admin-request-list-title">Danh sách yêu cầu học</h2>
            </header>

            @if ($requests->isNotEmpty())
                <div class="admin-table-wrap admin-request-table-wrap">
                    <table class="admin-table admin-requests-table">
                        <thead>
                            <tr>
                                <th scope="col">Mã yêu cầu</th>
                                <th scope="col">Người học</th>
                                <th scope="col">Môn học / Cấp độ</th>
                                <th scope="col">Hình thức</th>
                                <th scope="col">Học phí mong muốn</th>
                                <th scope="col">Ứng tuyển</th>
                                <th scope="col">Ngày tạo</th>
                                <th scope="col">Thời hạn</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $requestItem)
                                @php
                                    $requestStatus = strtoupper((string) $requestItem->status);
                                    $requestTypeValue = strtoupper((string) $requestItem->request_type);
                                    $learningModeValue = strtoupper((string) $requestItem->learning_mode);
                                    $statusDisplay = $statusMeta[$requestStatus] ?? [
                                        'label' => $requestStatus !== '' ? $requestStatus : 'Chưa xác định',
                                        'class' => 'neutral',
                                    ];
                                    $feeUnit = match (strtoupper((string) $requestItem->fee_type)) {
                                        'MONTHLY' => '/ tháng',
                                        'HOURLY' => '/ giờ',
                                        default => '',
                                    };
                                    $deadlineLabel = match ($requestStatus) {
                                        'MATCHED' => 'Đã kết thúc',
                                        'EXPIRED' => 'Đã hết hạn',
                                        default => null,
                                    };
                                    $deadlineClass = match ($requestStatus) {
                                        'MATCHED' => 'finished',
                                        'EXPIRED' => 'expired',
                                        default => 'active',
                                    };

                                    if ($deadlineLabel === null) {
                                        if ($requestItem->expires_at === null) {
                                            $deadlineLabel = 'Chưa xác định';
                                            $deadlineClass = 'neutral';
                                        } elseif ($requestItem->expires_at->isFuture()) {
                                            $daysRemaining = max(1, (int) ceil(now()->diffInDays($requestItem->expires_at, false)));
                                            $deadlineLabel = 'Còn '.$daysRemaining.' ngày';
                                        } else {
                                            $deadlineLabel = 'Chờ cập nhật';
                                            $deadlineClass = 'neutral';
                                        }
                                    }

                                    $subjectName = $requestItem->subjectLevel?->subject?->subject_name;
                                    $levelName = $requestItem->subjectLevel?->level_name
                                        ?: $requestItem->subjectLevel?->educationLevel?->level_name;
                                    $locationParts = collect([
                                        $requestItem->ward?->ward_name,
                                        $requestItem->ward?->province?->province_name,
                                    ])->filter()->unique()->implode(', ');
                                @endphp
                                <tr>
                                    <td data-label="Mã yêu cầu">
                                        <span class="admin-request-code">
                                            <strong>#REQ-{{ str_pad((string) $requestItem->request_id, 3, '0', STR_PAD_LEFT) }}</strong>
                                            <small class="admin-request-type admin-request-type--{{ strtolower($requestTypeValue) }}">
                                                {{ $requestTypeValue === 'PUBLIC' ? 'Công khai' : ($requestTypeValue === 'DIRECT' ? 'Gửi trực tiếp' : 'Chưa xác định') }}
                                            </small>
                                        </span>
                                    </td>
                                    <td data-label="Người học">
                                        <span class="admin-request-person">
                                            <x-tutor-avatar :user="$requestItem->user" />
                                            <strong>{{ $requestItem->user?->full_name ?: 'Chưa cập nhật' }}</strong>
                                        </span>
                                    </td>
                                    <td data-label="Môn học / Cấp độ">
                                        <span class="admin-request-subject">
                                            <strong>{{ $subjectName ?: 'Chưa cập nhật' }}</strong>
                                            <small>{{ $levelName ?: 'Chưa cập nhật' }}</small>
                                        </span>
                                    </td>
                                    <td data-label="Hình thức">
                                        <span class="admin-request-mode admin-request-mode--{{ strtolower($learningModeValue) }}">
                                            {{ $learningModeValue === 'ONLINE' ? 'Online' : ($learningModeValue === 'OFFLINE' ? 'Trực tiếp' : 'Chưa xác định') }}
                                        </span>
                                        @if ($learningModeValue === 'OFFLINE' && $locationParts !== '')
                                            <small class="admin-request-location">
                                                <x-directory-icon name="map-pin" />
                                                {{ $locationParts }}
                                            </small>
                                        @endif
                                    </td>
                                    <td data-label="Học phí mong muốn" class="admin-table__number">
                                        @if ($requestItem->expected_fee !== null)
                                            <span class="admin-request-fee">
                                                <strong>{{ number_format((float) $requestItem->expected_fee, 0, ',', '.') }} VNĐ</strong>
                                                <small>{{ $feeUnit }}</small>
                                            </span>
                                        @else
                                            <span class="admin-table__muted">Chưa cập nhật</span>
                                        @endif
                                    </td>
                                    <td data-label="Ứng tuyển" class="admin-table__number">
                                        @if ($requestTypeValue === 'PUBLIC')
                                            <span class="admin-request-applications">{{ $requestItem->applications_count }} gia sư</span>
                                        @else
                                            <span class="admin-table__muted">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Ngày tạo" class="admin-table__number">
                                        <time datetime="{{ $requestItem->created_at->toIso8601String() }}">
                                            <strong>{{ $requestItem->created_at->format('d/m/Y') }}</strong>
                                            <small>{{ $requestItem->created_at->format('H:i') }}</small>
                                        </time>
                                    </td>
                                    <td data-label="Thời hạn">
                                        <span class="admin-request-deadline admin-request-deadline--{{ $deadlineClass }}">
                                            {{ $deadlineLabel }}
                                        </span>
                                    </td>
                                    <td data-label="Trạng thái">
                                        <span class="admin-request-status admin-request-status--{{ $statusDisplay['class'] }}">
                                            <span aria-hidden="true"></span>
                                            {{ $statusDisplay['label'] }}
                                        </span>
                                    </td>
                                    <td data-label="Thao tác">
                                        @if (Route::has('admin.requests.show'))
                                            <a class="admin-request-view" href="{{ route('admin.requests.show', $requestItem) }}">
                                                <x-directory-icon name="eye" />
                                                <span>Xem chi tiết</span>
                                            </a>
                                        @else
                                            <span class="admin-request-view is-disabled" aria-disabled="true" title="Trang chi tiết quản trị chưa được triển khai">
                                                <x-directory-icon name="eye" />
                                                <span>Xem chi tiết</span>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="admin-request-empty">
                    <x-directory-icon name="search" />
                    <strong>Không có yêu cầu phù hợp</strong>
                    <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</p>
                </div>
            @endif

            <footer class="admin-request-pagination">
                <p>
                    Hiển thị <strong>{{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }}</strong>
                    trong <strong>{{ number_format($requests->total(), 0, ',', '.') }}</strong> yêu cầu
                </p>

                @if ($requests->hasPages())
                    @php
                        $visiblePages = collect([
                            1,
                            $requests->currentPage() - 1,
                            $requests->currentPage(),
                            $requests->currentPage() + 1,
                            $requests->lastPage(),
                        ])->filter(fn ($page) => $page >= 1 && $page <= $requests->lastPage())
                            ->unique()
                            ->sort()
                            ->values();
                        $previousVisiblePage = null;
                    @endphp
                    <nav class="admin-request-pagination__links" aria-label="Phân trang yêu cầu học">
                        @if ($requests->onFirstPage())
                            <span class="admin-request-pagination__step" aria-disabled="true">
                                <x-directory-icon name="arrow-left" /> Trước
                            </span>
                        @else
                            <a class="admin-request-pagination__step" href="{{ $requests->previousPageUrl() }}" rel="prev">
                                <x-directory-icon name="arrow-left" /> Trước
                            </a>
                        @endif

                        @foreach ($visiblePages as $page)
                            @if ($previousVisiblePage !== null && $page - $previousVisiblePage > 1)
                                <span class="admin-request-pagination__ellipsis" aria-hidden="true">…</span>
                            @endif
                            <a
                                @class(['is-active' => $page === $requests->currentPage()])
                                href="{{ $requests->url($page) }}"
                                @if ($page === $requests->currentPage()) aria-current="page" @endif
                            >{{ $page }}</a>
                            @php($previousVisiblePage = $page)
                        @endforeach

                        @if ($requests->hasMorePages())
                            <a class="admin-request-pagination__step" href="{{ $requests->nextPageUrl() }}" rel="next">
                                Sau <x-directory-icon name="chevron-right" />
                            </a>
                        @else
                            <span class="admin-request-pagination__step" aria-disabled="true">
                                Sau <x-directory-icon name="chevron-right" />
                            </span>
                        @endif
                    </nav>
                @endif
            </footer>
        </section>
    </div>
</x-admin-layout>
