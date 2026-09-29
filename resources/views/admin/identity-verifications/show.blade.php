@php
    $statusMeta = match ($verification->status) {
        \App\Models\IdentityVerification::STATUS_PENDING => [
            'class' => 'pending',
            'icon' => 'clock',
            'label' => 'Chờ duyệt',
            'description' => 'Hồ sơ đang chờ được kiểm duyệt.',
        ],
        \App\Models\IdentityVerification::STATUS_VERIFIED => [
            'class' => 'verified',
            'icon' => 'circle-check',
            'label' => 'Đã xác minh',
            'description' => 'Hồ sơ đã được xác minh.',
        ],
        default => [
            'class' => 'rejected',
            'icon' => 'x-circle',
            'label' => 'Từ chối',
            'description' => 'Hồ sơ chưa đáp ứng yêu cầu xác minh.',
        ],
    };
    $documentNumber = (string) $verification->document_number;
    $maskedDocumentNumber = str_repeat('•', max(strlen($documentNumber) - 4, 0))
        . substr($documentNumber, -4);
@endphp

<x-admin-layout title="Chi tiết xác minh danh tính" active-section="identity-verifications">
    <article class="admin-identity-page admin-identity-page--show">
        <nav class="admin-breadcrumb admin-identity-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <x-directory-icon name="chevron-right" />
            <a href="{{ route('admin.identity-verifications.index') }}">Xác minh danh tính</a>
            <x-directory-icon name="chevron-right" />
            <span aria-current="page">Chi tiết hồ sơ</span>
        </nav>

        <a class="admin-identity-back" href="{{ route('admin.identity-verifications.index') }}">
            <x-directory-icon name="arrow-left" />
            <span>Quay lại danh sách</span>
        </a>

        <header class="admin-identity-heading">
            <h1>Chi tiết xác minh danh tính</h1>
            <p>Kiểm tra thông tin và giấy tờ trước khi đưa ra quyết định.</p>
        </header>

        @if (session('success'))
            <div class="admin-identity-alert admin-identity-alert--success" role="status">
                <x-directory-icon name="circle-check" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="admin-identity-alert admin-identity-alert--error" role="alert">
                <x-directory-icon name="x-circle" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="admin-identity-detail-layout">
            <div class="admin-identity-detail-main">
                <section class="admin-identity-section" aria-labelledby="identity-user-title">
                    <h2 id="identity-user-title">
                        <x-directory-icon name="users" />
                        <span>Thông tin người dùng</span>
                    </h2>
                    <div class="admin-identity-user-card">
                        <x-tutor-avatar :user="$verification->user" />
                        <span>
                            <strong>{{ $verification->user?->full_name ?? '—' }}</strong>
                            <small>
                                <x-directory-icon name="mail" />
                                {{ $verification->user?->email ?? '—' }}
                            </small>
                        </span>
                    </div>
                </section>

                <section class="admin-identity-section" aria-labelledby="identity-document-info-title">
                    <h2 id="identity-document-info-title">
                        <x-directory-icon name="document" />
                        <span>Thông tin giấy tờ</span>
                    </h2>
                    <dl class="admin-identity-facts">
                        <div>
                            <dt><x-directory-icon name="file-check" /> Loại giấy tờ</dt>
                            <dd>{{ $verification->document_type }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="document" /> Số giấy tờ</dt>
                            <dd>{{ $maskedDocumentNumber }}</dd>
                        </div>
                        <div>
                            <dt><x-directory-icon name="calendar" /> Ngày gửi</dt>
                            <dd>
                                @if ($verification->submitted_at)
                                    <time datetime="{{ $verification->submitted_at->toIso8601String() }}">
                                        {{ $verification->submitted_at->format('d/m/Y · H:i') }}
                                    </time>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-identity-section" aria-labelledby="identity-images-title">
                    <h2 id="identity-images-title">
                        <x-directory-icon name="document" />
                        <span>Ảnh giấy tờ</span>
                    </h2>
                    <div class="admin-identity-document-grid">
                        @foreach (['front' => 'Mặt trước', 'back' => 'Mặt sau'] as $side => $label)
                            @php
                                $documentUrl = route('admin.identity-verifications.document', [
                                    $verification,
                                    $side,
                                ]);
                            @endphp
                            <figure class="admin-identity-document">
                                <figcaption>{{ $label }}</figcaption>
                                <a
                                    class="admin-identity-document__image"
                                    href="{{ $documentUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Xem ảnh {{ mb_strtolower($label) }} đầy đủ"
                                >
                                    <img src="{{ $documentUrl }}" alt="{{ $label }} giấy tờ xác minh">
                                </a>
                                <a class="admin-identity-document__link" href="{{ $documentUrl }}" target="_blank" rel="noopener">
                                    <x-directory-icon name="search" />
                                    <span>Xem ảnh đầy đủ</span>
                                </a>
                            </figure>
                        @endforeach
                    </div>
                    <p class="admin-identity-privacy-note">
                        <x-directory-icon name="info" />
                        <span>Thông tin giấy tờ chỉ được sử dụng cho mục đích kiểm duyệt danh tính.</span>
                    </p>
                </section>
            </div>

            <aside class="admin-identity-detail-sidebar" aria-label="Trạng thái và kiểm duyệt hồ sơ">
                <section class="admin-identity-section admin-identity-status-card" aria-labelledby="identity-status-title">
                    <h2 id="identity-status-title">
                        <x-directory-icon name="info" />
                        <span>Trạng thái hồ sơ</span>
                    </h2>
                    <div class="admin-identity-status-summary admin-identity-status-summary--{{ $statusMeta['class'] }}">
                        <span class="admin-identity-status-summary__icon">
                            <x-directory-icon :name="$statusMeta['icon']" />
                        </span>
                        <span>
                            <span class="admin-identity-status admin-identity-status--{{ $statusMeta['class'] }}">
                                <x-directory-icon :name="$statusMeta['icon']" />
                                <span>{{ $statusMeta['label'] }}</span>
                            </span>
                            <small>{{ $statusMeta['description'] }}</small>
                        </span>
                    </div>
                    <dl class="admin-identity-status-meta">
                        @if ($verification->status === \App\Models\IdentityVerification::STATUS_VERIFIED && $verification->verified_at)
                            <div>
                                <dt>Xác minh lúc</dt>
                                <dd>{{ $verification->verified_at->format('d/m/Y · H:i') }}</dd>
                            </div>
                        @elseif ($verification->submitted_at)
                            <div>
                                <dt>Ngày gửi</dt>
                                <dd>{{ $verification->submitted_at->format('d/m/Y · H:i') }}</dd>
                            </div>
                        @endif

                        @if ($verification->verifiedBy)
                            <div>
                                <dt>Người xử lý</dt>
                                <dd>{{ $verification->verifiedBy->full_name }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($verification->status === \App\Models\IdentityVerification::STATUS_REJECTED)
                        <div class="admin-identity-rejection-readonly">
                            <strong>Lý do từ chối</strong>
                            <p>{{ $verification->rejection_reason ?? 'Không có lý do.' }}</p>
                        </div>
                    @endif
                </section>

                @if ($verification->status === \App\Models\IdentityVerification::STATUS_PENDING)
                    <section class="admin-identity-section admin-identity-review" aria-labelledby="identity-review-title">
                        <h2 id="identity-review-title">
                            <x-directory-icon name="shield" />
                            <span>Kiểm duyệt hồ sơ</span>
                        </h2>
                        <p>Vui lòng kiểm tra kỹ các thông tin và hình ảnh trước khi đưa ra quyết định.</p>

                        <div class="admin-identity-checklist" aria-label="Danh sách hỗ trợ kiểm duyệt">
                            <label><input type="checkbox"> <span>Ảnh mặt trước rõ ràng</span></label>
                            <label><input type="checkbox"> <span>Ảnh mặt sau rõ ràng</span></label>
                            <label><input type="checkbox"> <span>Thông tin hai mặt nhất quán</span></label>
                            <label><input type="checkbox"> <span>Không phát hiện dấu hiệu bất thường rõ ràng</span></label>
                        </div>

                        <form
                            id="identity-reject-form"
                            action="{{ route('admin.identity-verifications.reject', $verification) }}"
                            method="POST"
                        >
                            @csrf
                            @method('PATCH')
                            <div class="admin-identity-field @error('rejection_reason') has-error @enderror">
                                <label for="rejection_reason">Lý do từ chối</label>
                                <textarea
                                    id="rejection_reason"
                                    name="rejection_reason"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                    aria-required="true"
                                    aria-invalid="{{ $errors->has('rejection_reason') ? 'true' : 'false' }}"
                                    aria-describedby="{{ $errors->has('rejection_reason') ? 'rejection-reason-error' : 'rejection-reason-help' }}"
                                    placeholder="Nêu rõ thông tin hoặc hình ảnh cần người dùng cập nhật"
                                >{{ old('rejection_reason') }}</textarea>
                                @error('rejection_reason')
                                    <p class="admin-identity-field__error" id="rejection-reason-error">{{ $message }}</p>
                                @else
                                    <p class="admin-identity-field__help" id="rejection-reason-help">Chỉ bắt buộc khi từ chối hồ sơ.</p>
                                @enderror
                            </div>
                        </form>

                        <div class="admin-identity-actions">
                            <button class="admin-identity-action admin-identity-action--reject" type="submit" form="identity-reject-form">
                                <x-directory-icon name="x-circle" />
                                <span>Từ chối</span>
                            </button>
                            <form action="{{ route('admin.identity-verifications.approve', $verification) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button class="admin-identity-action admin-identity-action--approve" type="submit">
                                    <x-directory-icon name="circle-check" />
                                    <span>Xác minh</span>
                                </button>
                            </form>
                        </div>
                    </section>
                @endif

                <section class="admin-identity-notice" aria-labelledby="identity-notice-title">
                    <h2 id="identity-notice-title">
                        <x-directory-icon name="info" />
                        <span>Lưu ý</span>
                    </h2>
                    <ul>
                        <li>Chỉ xác minh khi thông tin và hình ảnh rõ ràng, nhất quán.</li>
                        <li>Nếu từ chối, vui lòng nêu rõ lý do để người dùng cập nhật.</li>
                        <li>Thông tin giấy tờ là dữ liệu nhạy cảm, chỉ sử dụng cho mục đích quản trị.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </article>
</x-admin-layout>
