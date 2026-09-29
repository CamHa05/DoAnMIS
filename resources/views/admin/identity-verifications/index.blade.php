<x-admin-layout title="Xác minh danh tính" active-section="identity-verifications">
    <article class="admin-identity-page admin-identity-page--index">
        <nav class="admin-breadcrumb admin-identity-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Xác minh danh tính</span>
        </nav>

        <h1 class="admin-visually-hidden">Xác minh danh tính</h1>

        <nav class="admin-identity-tabs" aria-label="Lọc hồ sơ theo trạng thái">
            <a
                href="{{ route('admin.identity-verifications.index') }}"
                @class(['is-active' => ! $status])
                @if (! $status) aria-current="page" @endif
            >Tất cả</a>
            <a
                href="{{ route('admin.identity-verifications.index', ['status' => \App\Models\IdentityVerification::STATUS_PENDING]) }}"
                @class(['is-active' => $status === \App\Models\IdentityVerification::STATUS_PENDING])
                @if ($status === \App\Models\IdentityVerification::STATUS_PENDING) aria-current="page" @endif
            >Chờ duyệt</a>
            <a
                href="{{ route('admin.identity-verifications.index', ['status' => \App\Models\IdentityVerification::STATUS_VERIFIED]) }}"
                @class(['is-active' => $status === \App\Models\IdentityVerification::STATUS_VERIFIED])
                @if ($status === \App\Models\IdentityVerification::STATUS_VERIFIED) aria-current="page" @endif
            >Đã xác minh</a>
            <a
                href="{{ route('admin.identity-verifications.index', ['status' => \App\Models\IdentityVerification::STATUS_REJECTED]) }}"
                @class(['is-active' => $status === \App\Models\IdentityVerification::STATUS_REJECTED])
                @if ($status === \App\Models\IdentityVerification::STATUS_REJECTED) aria-current="page" @endif
            >Từ chối</a>
        </nav>

        <section class="admin-identity-panel" aria-labelledby="identity-list-title">
            <h2 class="admin-visually-hidden" id="identity-list-title">Danh sách hồ sơ xác minh danh tính</h2>

            <div class="admin-table-wrap admin-identity-table-wrap">
                <table class="admin-table admin-identity-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Người dùng</th>
                            <th scope="col">Loại giấy tờ</th>
                            <th scope="col">Số giấy tờ</th>
                            <th scope="col">Ngày gửi</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Hành động</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($verifications as $verification)
                            @php
                                $statusMeta = match ($verification->status) {
                                    \App\Models\IdentityVerification::STATUS_PENDING => [
                                        'class' => 'pending',
                                        'icon' => 'clock',
                                        'label' => 'Chờ duyệt',
                                    ],
                                    \App\Models\IdentityVerification::STATUS_VERIFIED => [
                                        'class' => 'verified',
                                        'icon' => 'circle-check',
                                        'label' => 'Đã xác minh',
                                    ],
                                    default => [
                                        'class' => 'rejected',
                                        'icon' => 'x-circle',
                                        'label' => 'Từ chối',
                                    ],
                                };
                                $documentNumber = (string) $verification->document_number;
                                $maskedDocumentNumber = str_repeat('•', max(strlen($documentNumber) - 4, 0))
                                    . substr($documentNumber, -4);
                            @endphp
                            <tr>
                                <td class="admin-identity-table__index">
                                    {{ ($verifications->firstItem() ?? 1) + $loop->index }}
                                </td>
                                <td>
                                    <div class="admin-identity-person">
                                        <x-tutor-avatar :user="$verification->user" />
                                        <span>
                                            <strong>{{ $verification->user?->full_name ?? 'Người dùng' }}</strong>
                                            <small>{{ $verification->user?->email ?? '—' }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $verification->document_type }}</td>
                                <td class="admin-identity-table__number">{{ $maskedDocumentNumber }}</td>
                                <td class="admin-identity-table__date">
                                    @if ($verification->submitted_at)
                                        <time datetime="{{ $verification->submitted_at->toIso8601String() }}">
                                            <strong>{{ $verification->submitted_at->format('d/m/Y') }}</strong>
                                            <small>{{ $verification->submitted_at->format('H:i') }}</small>
                                        </time>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-identity-status admin-identity-status--{{ $statusMeta['class'] }}">
                                        <x-directory-icon :name="$statusMeta['icon']" />
                                        <span>{{ $statusMeta['label'] }}</span>
                                    </span>
                                </td>
                                <td>
                                    <a
                                        class="admin-identity-view"
                                        href="{{ route('admin.identity-verifications.show', $verification) }}"
                                    >
                                        <span>Xem chi tiết</span>
                                        <x-directory-icon name="arrow" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table__empty" colspan="7">
                                    <div class="admin-identity-empty">
                                        <x-directory-icon name="shield" />
                                        <strong>Chưa có hồ sơ xác minh danh tính</strong>
                                        <p>Hồ sơ người dùng gửi sẽ xuất hiện tại đây.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($verifications->hasPages())
                <div class="admin-identity-pagination">
                    {{ $verifications->onEachSide(1)->links() }}
                </div>
            @endif
        </section>
    </article>
</x-admin-layout>
