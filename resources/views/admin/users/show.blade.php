@php
    $isActive = $user->isActive();
    $isSelf = (int) auth()->id() === (int) $user->getKey();
    $tutorProfile = $user->tutorProfile;
    $verification = $user->identityVerification;
    $profileMeta = match ($tutorProfile?->approval_status) {
        \App\Models\TutorProfile::STATUS_PENDING => ['tone' => 'pending', 'label' => 'Chờ duyệt'],
        \App\Models\TutorProfile::STATUS_APPROVED => ['tone' => 'approved', 'label' => 'Đã duyệt'],
        \App\Models\TutorProfile::STATUS_REJECTED => ['tone' => 'rejected', 'label' => 'Từ chối'],
        default => ['tone' => 'neutral', 'label' => 'Không xác định'],
    };
    $verificationMeta = match ($verification?->status) {
        \App\Models\IdentityVerification::STATUS_PENDING => ['tone' => 'pending', 'label' => 'Chờ xác minh'],
        \App\Models\IdentityVerification::STATUS_VERIFIED => ['tone' => 'approved', 'label' => 'Đã xác minh'],
        \App\Models\IdentityVerification::STATUS_REJECTED => ['tone' => 'rejected', 'label' => 'Từ chối'],
        default => ['tone' => 'neutral', 'label' => 'Chưa xác minh'],
    };
    $supportModes = collect([
        $tutorProfile?->supports_online ? 'Online' : null,
        $tutorProfile?->supports_offline ? 'Offline' : null,
    ])->filter()->implode(', ');
@endphp

<x-admin-layout title="Chi tiết người dùng" active-section="users">
    <article class="admin-user-page admin-user-page--show">
        <nav class="admin-breadcrumb admin-user-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <a href="{{ route('admin.users.index') }}">Người dùng</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Chi tiết người dùng</span>
        </nav>

        <a class="admin-user-back" href="{{ route('admin.users.index') }}">
            <x-directory-icon name="arrow-left" />
            <span>Quay lại danh sách</span>
        </a>

        <h1 class="admin-visually-hidden">Chi tiết người dùng</h1>

        @unless ($isActive)
            <section class="admin-user-disabled-banner" role="status" aria-labelledby="disabled-account-title">
                <x-directory-icon name="x-circle" />
                <div>
                    <h2 id="disabled-account-title">Tài khoản đã bị vô hiệu hóa</h2>
                    <p>Người dùng này hiện không thể đăng nhập hoặc thực hiện các thao tác mới trên GiaSu.</p>
                </div>
            </section>
        @endunless

        <div class="admin-user-detail-grid">
            <div class="admin-user-detail-main">
                <section class="admin-user-section" aria-labelledby="account-information-title">
                    <h2 id="account-information-title">
                        <x-directory-icon name="user" />
                        <span>Thông tin tài khoản</span>
                    </h2>

                    <div class="admin-user-profile-summary">
                        <x-tutor-avatar :user="$user" size="large" />
                        <div class="admin-user-profile-summary__content">
                            <h3>{{ $user->full_name }}</h3>
                            <div class="admin-user-profile-summary__badges">
                                <span @class([
                                    'admin-user-badge',
                                    'admin-user-badge--admin' => $user->is_admin,
                                    'admin-user-badge--user' => ! $user->is_admin,
                                ])>
                                    {{ $user->is_admin ? 'Admin' : 'Người dùng' }}
                                </span>
                                <span @class([
                                    'admin-user-badge',
                                    'admin-user-badge--status',
                                    'admin-user-badge--approved' => $isActive,
                                    'admin-user-badge--rejected' => ! $isActive,
                                ])>
                                    <span class="admin-user-badge__dot" aria-hidden="true"></span>
                                    {{ $isActive ? 'Hoạt động' : 'Đã vô hiệu hóa' }}
                                </span>
                            </div>
                            <dl class="admin-user-account-facts">
                                <div>
                                    <dt><x-directory-icon name="mail" /><span class="admin-visually-hidden">Email</span></dt>
                                    <dd>{{ $user->email }}</dd>
                                </div>
                                <div>
                                    <dt><x-directory-icon name="phone" /><span class="admin-visually-hidden">Số điện thoại</span></dt>
                                    <dd>{{ $user->phone ?: 'Chưa cập nhật' }}</dd>
                                </div>
                                <div>
                                    <dt><x-directory-icon name="calendar" /><span>Ngày tham gia</span></dt>
                                    <dd>{{ $user->created_at?->format('d/m/Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt><x-directory-icon name="clock" /><span>Lần đăng nhập cuối</span></dt>
                                    <dd>{{ $user->last_login?->format('d/m/Y · H:i') ?? 'Chưa có' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                <section class="admin-user-section" aria-labelledby="user-activity-title">
                    <h2 id="user-activity-title">
                        <x-directory-icon name="list" />
                        <span>Hoạt động trên hệ thống</span>
                    </h2>

                    <div class="admin-user-activity-grid">
                        <article class="admin-user-activity-card">
                            <span class="admin-user-activity-card__icon"><x-directory-icon name="request" /></span>
                            <span>
                                <strong>{{ number_format($user->tutoring_requests_count, 0, ',', '.') }}</strong>
                                <small>Yêu cầu học<br>đã tạo</small>
                            </span>
                            @if (Route::has('admin.requests.index'))
                                <a href="{{ route('admin.requests.index', ['user' => $user->getKey()]) }}">Xem yêu cầu học →</a>
                            @endif
                        </article>
                        <article class="admin-user-activity-card">
                            <span class="admin-user-activity-card__icon"><x-directory-icon name="user" /></span>
                            <span>
                                <strong>{{ number_format($tutorProfile?->applications_count ?? 0, 0, ',', '.') }}</strong>
                                <small>Ứng tuyển<br>đã gửi</small>
                            </span>
                        </article>
                        <article class="admin-user-activity-card">
                            <span class="admin-user-activity-card__icon"><x-directory-icon name="book-open" /></span>
                            <span>
                                <strong>{{ number_format($relatedClassCount, 0, ',', '.') }}</strong>
                                <small>Lớp học<br>liên quan</small>
                            </span>
                            @if (Route::has('admin.classes.index'))
                                <a href="{{ route('admin.classes.index', ['user' => $user->getKey()]) }}">Xem lớp học →</a>
                            @endif
                        </article>
                    </div>
                </section>
            </div>

            <aside class="admin-user-detail-sidebar" aria-label="Trạng thái và hồ sơ liên quan">
                <section class="admin-user-section admin-user-account-status" aria-labelledby="account-status-title">
                    <h2 id="account-status-title">
                        <x-directory-icon name="shield" />
                        <span>Trạng thái tài khoản</span>
                    </h2>
                    <span @class([
                        'admin-user-badge',
                        'admin-user-badge--status',
                        'admin-user-badge--approved' => $isActive,
                        'admin-user-badge--rejected' => ! $isActive,
                    ])>
                        <span class="admin-user-badge__dot" aria-hidden="true"></span>
                        {{ $isActive ? 'Hoạt động' : 'Đã vô hiệu hóa' }}
                    </span>
                    <p>
                        {{ $isActive
                            ? 'Tài khoản hiện có thể sử dụng hệ thống GiaSu.'
                            : 'Tài khoản hiện không thể đăng nhập hoặc thực hiện các thao tác mới trên hệ thống.' }}
                    </p>
                    <button
                        @class([
                            'admin-user-status-action',
                            'admin-user-status-action--deactivate' => $isActive,
                            'admin-user-status-action--activate' => ! $isActive,
                        ])
                        type="button"
                        @if ($isActive && $isSelf)
                            disabled
                            aria-describedby="admin-user-self-disable-help"
                        @else
                            data-admin-user-status-open
                            aria-haspopup="dialog"
                        @endif
                    >
                        <x-directory-icon :name="$isActive ? 'x-circle' : 'circle-check'" />
                        <span>{{ $isActive ? 'Vô hiệu hóa tài khoản' : 'Kích hoạt lại tài khoản' }}</span>
                    </button>
                    @if ($isActive && $isSelf)
                        <p class="admin-user-status-help" id="admin-user-self-disable-help">
                            Bạn không thể vô hiệu hóa tài khoản quản trị của chính mình.
                        </p>
                    @endif
                </section>

                <section class="admin-user-section" aria-labelledby="user-tutor-profile-title">
                    <div class="admin-user-section__title-row">
                        <h2 id="user-tutor-profile-title">
                            <x-directory-icon name="education" />
                            <span>Hồ sơ gia sư</span>
                        </h2>
                        @if ($tutorProfile)
                            <span class="admin-user-badge admin-user-badge--{{ $profileMeta['tone'] }}">
                                {{ $profileMeta['label'] }}
                            </span>
                        @endif
                    </div>

                    @if ($tutorProfile)
                        <h3 class="admin-user-side-title">{{ $tutorProfile->headline ?: 'Chưa cập nhật tiêu đề hồ sơ' }}</h3>
                        <dl class="admin-user-side-facts">
                            <div>
                                <dt>Học phí mong muốn</dt>
                                <dd>
                                    {{ $tutorProfile->hourly_rate !== null
                                        ? number_format((float) $tutorProfile->hourly_rate, 0, ',', '.') . 'đ / giờ'
                                        : 'Chưa cập nhật' }}
                                </dd>
                            </div>
                            <div>
                                <dt>Hình thức hỗ trợ</dt>
                                <dd>{{ $supportModes !== '' ? $supportModes : 'Chưa cập nhật' }}</dd>
                            </div>
                            <div>
                                <dt>Ngày gửi hồ sơ</dt>
                                <dd>{{ $tutorProfile->submitted_at?->format('d/m/Y') ?? 'Chưa gửi' }}</dd>
                            </div>
                        </dl>
                        @if ($tutorProfile->submitted_at && Route::has('admin.tutors.show'))
                            <a class="admin-user-side-link" href="{{ route('admin.tutors.show', $tutorProfile) }}">
                                Xem hồ sơ gia sư
                            </a>
                        @endif
                    @else
                        <p class="admin-user-empty-state">Người dùng chưa đăng ký trở thành gia sư.</p>
                    @endif
                </section>

                <section class="admin-user-section" aria-labelledby="user-verification-title">
                    <div class="admin-user-section__title-row">
                        <h2 id="user-verification-title">
                            <x-directory-icon name="shield" />
                            <span>Xác minh danh tính</span>
                        </h2>
                        @if ($verification)
                            <span class="admin-user-badge admin-user-badge--{{ $verificationMeta['tone'] }}">
                                {{ $verificationMeta['label'] }}
                            </span>
                        @endif
                    </div>

                    @if ($verification)
                        <dl class="admin-user-side-facts">
                            <div>
                                <dt>Loại giấy tờ</dt>
                                <dd>{{ $verification->document_type }}</dd>
                            </div>
                            <div>
                                <dt>Ngày gửi</dt>
                                <dd>{{ $verification->submitted_at?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            @if ($verification->verified_at)
                                <div>
                                    <dt>Ngày xác minh</dt>
                                    <dd>{{ $verification->verified_at->format('d/m/Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (Route::has('admin.identity-verifications.show'))
                            <a class="admin-user-side-link" href="{{ route('admin.identity-verifications.show', $verification) }}">
                                Xem thông tin xác minh
                            </a>
                        @endif
                    @else
                        <p class="admin-user-empty-state">Người dùng chưa gửi yêu cầu xác minh danh tính.</p>
                    @endif
                </section>
            </aside>
        </div>
    </article>

    @unless ($isActive && $isSelf)
        <dialog
            class="admin-user-status-dialog"
            data-admin-user-status-dialog
            data-mode="{{ $isActive ? 'disable' : 'activate' }}"
            aria-labelledby="admin-user-status-dialog-title"
            aria-describedby="admin-user-status-dialog-description"
        >
            <form
                class="admin-user-status-dialog__form"
                method="POST"
                action="{{ $isActive ? route('admin.users.disable', $user) : route('admin.users.activate', $user) }}"
                data-admin-user-status-form
            >
                @csrf
                @method('PATCH')

                <button
                    class="admin-user-status-dialog__close"
                    type="button"
                    aria-label="Đóng hộp thoại"
                    data-admin-user-status-close
                >×</button>

                <div class="admin-user-status-dialog__icon" aria-hidden="true">
                    <x-directory-icon :name="$isActive ? 'x-circle' : 'circle-check'" />
                </div>

                <header class="admin-user-status-dialog__header">
                    <h2 id="admin-user-status-dialog-title">
                        {{ $isActive ? 'Vô hiệu hóa tài khoản' : 'Kích hoạt lại tài khoản' }}
                    </h2>
                    <p id="admin-user-status-dialog-description">
                        {{ $isActive
                            ? 'Bạn có chắc chắn muốn vô hiệu hóa tài khoản này?'
                            : 'Bạn có chắc chắn muốn kích hoạt lại tài khoản này?' }}
                    </p>
                </header>

                <section class="admin-user-status-dialog__identity" aria-label="Tài khoản sẽ thay đổi trạng thái">
                    <x-tutor-avatar :user="$user" />
                    <div>
                        <strong>{{ $user->full_name }}</strong>
                        <span><x-directory-icon name="mail" />{{ $user->email }}</span>
                    </div>
                    <span @class([
                        'admin-user-badge',
                        'admin-user-badge--status',
                        'admin-user-badge--approved' => $isActive,
                        'admin-user-badge--rejected' => ! $isActive,
                    ])>
                        <span class="admin-user-badge__dot" aria-hidden="true"></span>
                        {{ $isActive ? 'Hoạt động' : 'Đã vô hiệu hóa' }}
                    </span>
                </section>

                @if ($isActive)
                    <div class="admin-user-status-dialog__notice admin-user-status-dialog__notice--danger">
                        <x-directory-icon name="info" />
                        <p>Sau khi vô hiệu hóa, người dùng sẽ không thể đăng nhập hoặc thực hiện các thao tác mới trên hệ thống.</p>
                    </div>
                    <div class="admin-user-status-dialog__notice admin-user-status-dialog__notice--neutral">
                        <x-directory-icon name="info" />
                        <p>Việc vô hiệu hóa không xóa hồ sơ gia sư, yêu cầu học, ứng tuyển, hợp đồng hoặc lớp học đã tồn tại.</p>
                    </div>
                @else
                    <div class="admin-user-status-dialog__notice admin-user-status-dialog__notice--info">
                        <x-directory-icon name="info" />
                        <p>Sau khi kích hoạt, người dùng có thể đăng nhập và tiếp tục sử dụng các chức năng được phép trên GiaSu.</p>
                    </div>
                @endif

                <footer class="admin-user-status-dialog__actions">
                    <button
                        class="admin-user-status-dialog__cancel"
                        type="button"
                        data-admin-user-status-close
                        data-admin-user-status-initial
                    >Hủy</button>
                    <button
                        @class([
                            'admin-user-status-dialog__submit',
                            'admin-user-status-dialog__submit--danger' => $isActive,
                            'admin-user-status-dialog__submit--activate' => ! $isActive,
                        ])
                        type="submit"
                        data-admin-user-status-submit
                    >
                        <span data-admin-user-status-submit-label>
                            {{ $isActive ? 'Vô hiệu hóa tài khoản' : 'Kích hoạt lại' }}
                        </span>
                    </button>
                </footer>
            </form>
        </dialog>
    @endunless
</x-admin-layout>
