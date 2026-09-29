<x-admin-layout title="Quản lý người dùng" active-section="users">
    <article class="admin-user-page admin-user-page--index">
        <nav class="admin-breadcrumb admin-user-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Người dùng</span>
        </nav>

        <h1 class="admin-visually-hidden">Quản lý người dùng</h1>

        <section class="admin-user-stats" aria-label="Thống kê người dùng">
            <article class="admin-user-stat">
                <span class="admin-user-stat__icon"><x-directory-icon name="users" /></span>
                <span>
                    <small>Tổng tài khoản</small>
                    <strong>{{ number_format($statistics['total'], 0, ',', '.') }}</strong>
                </span>
            </article>
            <article class="admin-user-stat">
                <span class="admin-user-stat__icon"><x-directory-icon name="circle-check" /></span>
                <span>
                    <small>Tài khoản đang hoạt động</small>
                    <strong>{{ number_format($statistics['active'], 0, ',', '.') }}</strong>
                </span>
            </article>
            <article class="admin-user-stat">
                <span class="admin-user-stat__icon"><x-directory-icon name="document" /></span>
                <span>
                    <small>Có hồ sơ gia sư</small>
                    <strong>{{ number_format($statistics['with_tutor_profile'], 0, ',', '.') }}</strong>
                </span>
            </article>
            <article class="admin-user-stat">
                <span class="admin-user-stat__icon"><x-directory-icon name="shield" /></span>
                <span>
                    <small>Đã xác minh danh tính</small>
                    <strong>{{ number_format($statistics['identity_verified'], 0, ',', '.') }}</strong>
                </span>
            </article>
        </section>

        <form class="admin-user-filters" method="GET" action="{{ route('admin.users.index') }}" role="search">
            <label class="admin-user-search" for="user-search">
                <span class="admin-visually-hidden">Tìm kiếm người dùng</span>
                <x-directory-icon name="search" />
                <input
                    id="user-search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Tìm theo tên, email hoặc số điện thoại..."
                >
            </label>

            <label class="admin-user-filter-field">
                <span>Loại tài khoản</span>
                <select name="account_type" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="user" @selected($accountType === 'user')>Người dùng</option>
                    <option value="admin" @selected($accountType === 'admin')>Admin</option>
                </select>
            </label>

            <label class="admin-user-filter-field">
                <span>Trạng thái tài khoản</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="active" @selected($status === 'active')>Hoạt động</option>
                    <option value="inactive" @selected($status === 'inactive')>Đã vô hiệu hóa</option>
                </select>
            </label>

            <label class="admin-user-filter-field">
                <span>Hồ sơ gia sư</span>
                <select name="tutor_status" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="none" @selected($tutorStatus === 'none')>Không có</option>
                    <option value="pending" @selected($tutorStatus === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected($tutorStatus === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected($tutorStatus === 'rejected')>Từ chối</option>
                </select>
            </label>

            <a class="admin-user-reset" href="{{ route('admin.users.index') }}">
                <x-directory-icon name="filter" />
                <span>Đặt lại bộ lọc</span>
            </a>
        </form>

        <section class="admin-user-table-card" aria-labelledby="user-list-title">
            <header class="admin-user-table-card__header">
                <h2 id="user-list-title">Danh sách người dùng</h2>
            </header>

            <div class="admin-user-table-wrap">
                <table class="admin-user-table">
                    <thead>
                        <tr>
                            <th scope="col">Người dùng</th>
                            <th scope="col">Email</th>
                            <th scope="col">Số điện thoại</th>
                            <th scope="col">Loại tài khoản</th>
                            <th scope="col">Hồ sơ gia sư</th>
                            <th scope="col">Xác minh danh tính</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Ngày tham gia</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $listedUser)
                            @php
                                $profileStatus = $listedUser->tutorProfile?->approval_status;
                                $profileMeta = match ($profileStatus) {
                                    \App\Models\TutorProfile::STATUS_PENDING => ['tone' => 'pending', 'label' => 'Chờ duyệt'],
                                    \App\Models\TutorProfile::STATUS_APPROVED => ['tone' => 'approved', 'label' => 'Đã duyệt'],
                                    \App\Models\TutorProfile::STATUS_REJECTED => ['tone' => 'rejected', 'label' => 'Từ chối'],
                                    default => ['tone' => 'neutral', 'label' => 'Không có'],
                                };
                                $verificationStatus = $listedUser->identityVerification?->status;
                                $verificationMeta = match ($verificationStatus) {
                                    \App\Models\IdentityVerification::STATUS_PENDING => ['tone' => 'pending', 'label' => 'Chờ xác minh'],
                                    \App\Models\IdentityVerification::STATUS_VERIFIED => ['tone' => 'approved', 'label' => 'Đã xác minh'],
                                    \App\Models\IdentityVerification::STATUS_REJECTED => ['tone' => 'rejected', 'label' => 'Từ chối'],
                                    default => ['tone' => 'neutral', 'label' => 'Chưa xác minh'],
                                };
                                $isActive = strtoupper((string) $listedUser->status) === 'ACTIVE';
                            @endphp
                            <tr>
                                <td>
                                    <div class="admin-user-person">
                                        <x-tutor-avatar :user="$listedUser" />
                                        <strong>{{ $listedUser->full_name }}</strong>
                                    </div>
                                </td>
                                <td class="admin-user-table__email">{{ $listedUser->email }}</td>
                                <td>{{ $listedUser->phone ?: 'Chưa cập nhật' }}</td>
                                <td>
                                    <span @class([
                                        'admin-user-badge',
                                        'admin-user-badge--admin' => $listedUser->is_admin,
                                        'admin-user-badge--user' => ! $listedUser->is_admin,
                                    ])>
                                        {{ $listedUser->is_admin ? 'Admin' : 'Người dùng' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-user-badge admin-user-badge--{{ $profileMeta['tone'] }}">
                                        {{ $profileMeta['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-user-badge admin-user-badge--{{ $verificationMeta['tone'] }}">
                                        {{ $verificationMeta['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span @class([
                                        'admin-user-badge',
                                        'admin-user-badge--status',
                                        'admin-user-badge--approved' => $isActive,
                                        'admin-user-badge--rejected' => ! $isActive,
                                    ])>
                                        <span class="admin-user-badge__dot" aria-hidden="true"></span>
                                        {{ $isActive ? 'Hoạt động' : 'Đã vô hiệu hóa' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($listedUser->created_at)
                                        <time datetime="{{ $listedUser->created_at->toDateString() }}">
                                            {{ $listedUser->created_at->format('d/m/Y') }}
                                        </time>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <a class="admin-user-detail-link" href="{{ route('admin.users.show', $listedUser) }}">
                                        Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-user-empty" colspan="9">
                                    <x-directory-icon name="users" />
                                    <strong>Không tìm thấy người dùng phù hợp</strong>
                                    <p>Thử thay đổi từ khóa hoặc đặt lại bộ lọc.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="admin-user-pagination">
                <p>
                    Hiển thị {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}
                    trong tổng số {{ $users->total() }} người dùng
                </p>

                @if ($users->hasPages())
                    @php
                        $currentPage = $users->currentPage();
                        $lastPage = $users->lastPage();
                        $visiblePages = collect(range(1, $lastPage))->filter(
                            fn (int $page): bool => $page === 1
                                || $page === $lastPage
                                || abs($page - $currentPage) <= 1
                        );
                        $previousVisiblePage = null;
                    @endphp
                    <nav class="admin-user-pagination__nav" aria-label="Phân trang người dùng">
                        <span class="admin-user-page-size">10 / trang</span>
                        @if ($users->onFirstPage())
                            <span class="admin-user-page-link is-disabled" aria-disabled="true">←</span>
                        @else
                            <a class="admin-user-page-link" href="{{ $users->previousPageUrl() }}" rel="prev" aria-label="Trang trước">←</a>
                        @endif

                        @foreach ($visiblePages as $page)
                            @if ($previousVisiblePage !== null && $page - $previousVisiblePage > 1)
                                <span class="admin-user-page-ellipsis" aria-hidden="true">…</span>
                            @endif
                            @if ($page === $currentPage)
                                <span class="admin-user-page-link is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="admin-user-page-link" href="{{ $users->url($page) }}" aria-label="Đến trang {{ $page }}">{{ $page }}</a>
                            @endif
                            @php($previousVisiblePage = $page)
                        @endforeach

                        @if ($users->hasMorePages())
                            <a class="admin-user-page-link" href="{{ $users->nextPageUrl() }}" rel="next" aria-label="Trang sau">→</a>
                        @else
                            <span class="admin-user-page-link is-disabled" aria-disabled="true">→</span>
                        @endif
                    </nav>
                @endif
            </footer>
        </section>
    </article>
</x-admin-layout>
