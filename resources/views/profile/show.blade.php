<x-app-layout title="Hồ sơ của tôi | GiaSu">
    <section class="profile-page" aria-labelledby="profile-title">
        <div class="container profile-container">
            <header class="profile-page-header">
                <h1 id="profile-title">Hồ sơ của tôi</h1>
                <p>Cập nhật thông tin cá nhân dùng trên GiaSu.</p>
            </header>

            @if (session('error') && session('contact_profile_action'))
                <div class="profile-alert profile-alert--error" role="alert">
                    <x-directory-icon name="info" />
                    <span>{{ session('error') }}</span>
                    <a class="button button-small" href="#full_name">Cập nhật hồ sơ</a>
                </div>
            @endif

            @if (session('success'))
                <div class="profile-alert" role="status">
                    <x-directory-icon name="check" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <div class="profile-shell">
                <aside class="profile-identity" aria-label="Thông tin tài khoản">
                    <div class="profile-avatar-frame">
                        <x-tutor-avatar :user="$user" size="large" />
                    </div>

                    <div class="profile-identity-copy">
                        <h2>{{ $user->full_name }}</h2>
                        <p>{{ $user->email }}</p>
                    </div>
                </aside>

                <div class="profile-content-stack">
                    <section class="profile-form-panel" aria-labelledby="personal-information-title">
                    <div class="profile-panel-heading">
                        <div class="profile-panel-icon" aria-hidden="true">
                            <x-directory-icon name="user" />
                        </div>
                        <div>
                            <h2 id="personal-information-title">Thông tin cá nhân</h2>
                            <p>Những thông tin cơ bản gắn với tài khoản của bạn.</p>
                        </div>
                    </div>

                        <form class="profile-form" method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="profile-field">
                            <label for="full_name">Họ và tên</label>
                            <div class="profile-input-wrap @error('full_name') has-error @enderror">
                                <x-directory-icon name="user" />
                                <input
                                    id="full_name"
                                    name="full_name"
                                    type="text"
                                    value="{{ old('full_name', $user->full_name) }}"
                                    maxlength="100"
                                    autocomplete="name"
                                    required
                                    aria-required="true"
                                    @error('full_name') aria-invalid="true" aria-describedby="full-name-error" @enderror
                                >
                            </div>
                            @error('full_name')
                                <p class="profile-field-support profile-field-error" id="full-name-error" role="alert">{{ $message }}</p>
                            @else
                                <p class="profile-field-support">Tên được hiển thị trong khu vực tài khoản.</p>
                            @enderror
                        </div>

                        <div class="profile-field">
                            <label for="email">Email</label>
                            <div class="profile-input-wrap is-readonly">
                                <x-directory-icon name="mail" />
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ $user->email }}"
                                    autocomplete="email"
                                    aria-describedby="email-help"
                                    readonly
                                >
                                <x-directory-icon class="profile-input-lock" name="lock" />
                            </div>
                            <p class="profile-field-support" id="email-help">Email đã được xác thực qua Google nên không thể chỉnh sửa tại đây.</p>
                        </div>

                        <div class="profile-field">
                            <label for="phone">Số điện thoại</label>
                            <div class="profile-input-wrap @error('phone') has-error @enderror">
                                <x-directory-icon name="phone" />
                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    value="{{ old('phone', $user->phone) }}"
                                    maxlength="20"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                                >
                            </div>
                            @error('phone')
                                <p class="profile-field-support profile-field-error" id="phone-error" role="alert">{{ $message }}</p>
                            @else
                                <p class="profile-field-support">Bạn có thể để trống nếu chưa muốn bổ sung.</p>
                            @enderror
                        </div>

                        <div class="profile-form-actions">
                            <button class="button profile-save-button" type="submit">
                                <x-directory-icon name="save" />
                                Lưu thay đổi
                            </button>
                        </div>
                        </form>
                    </section>

                    @php
                        $verificationStatus = $verification?->status;
                        $isPending = $verificationStatus === \App\Models\IdentityVerification::STATUS_PENDING;
                        $isVerified = $verificationStatus === \App\Models\IdentityVerification::STATUS_VERIFIED;
                        $isRejected = $verificationStatus === \App\Models\IdentityVerification::STATUS_REJECTED;
                    @endphp

                    <section class="profile-verification-card" aria-labelledby="profile-verification-title">
                        <div class="profile-verification-heading">
                            <div class="profile-panel-icon" aria-hidden="true">
                                <x-directory-icon name="verified" />
                            </div>
                            <div>
                                <div class="profile-verification-title-row">
                                    <h2 id="profile-verification-title">Xác minh danh tính</h2>
                                    <span class="profile-verification-badge profile-verification-badge--{{ $isPending ? 'pending' : ($isVerified ? 'verified' : ($isRejected ? 'rejected' : 'unverified')) }}">
                                        {{ $isPending ? 'Chờ duyệt' : ($isVerified ? 'Đã xác minh' : ($isRejected ? 'Cần cập nhật' : 'Chưa xác minh')) }}
                                    </span>
                                </div>
                                @if ($isPending)
                                    <p>Hồ sơ xác minh danh tính của bạn đang được quản trị viên kiểm tra.</p>
                                @elseif ($isVerified)
                                    <p>Hồ sơ của bạn đã được GiaSu kiểm duyệt.</p>
                                @elseif ($isRejected)
                                    <p>Hồ sơ cần được cập nhật trước khi gửi lại.</p>
                                @else
                                    <p>Xác minh danh tính để tăng độ tin cậy và sử dụng các chức năng quan trọng trên GiaSu.</p>
                                @endif
                            </div>
                        </div>

                        @if ($isPending && $verification->submitted_at)
                            <p class="profile-verification-meta"><strong>Gửi lúc:</strong> {{ $verification->submitted_at->format('d/m/Y H:i') }}</p>
                        @elseif ($isVerified)
                            <dl class="profile-verification-summary">
                                <div><dt>Loại giấy tờ</dt><dd>{{ $verification->document_type }}</dd></div>
                                <div><dt>Số giấy tờ</dt><dd>{{ str_repeat('*', max(strlen($verification->document_number) - 4, 0)) }}{{ substr($verification->document_number, -4) }}</dd></div>
                                @if ($verification->verified_at)<div><dt>Thời gian xác minh</dt><dd>{{ $verification->verified_at->format('d/m/Y H:i') }}</dd></div>@endif
                            </dl>
                        @elseif ($isRejected && $verification->rejection_reason)
                            <p class="profile-verification-reason"><strong>Lý do:</strong> {{ $verification->rejection_reason }}</p>
                        @endif

                        <a class="button button-small profile-verification-action" href="{{ route('identity-verification.show') }}">
                            <x-directory-icon name="verified" />
                            {{ $isPending ? 'Xem trạng thái xác minh' : ($isRejected ? 'Cập nhật và gửi lại' : ($isVerified ? 'Xem thông tin xác minh' : 'Bắt đầu xác minh')) }}
                        </a>
                    </section>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
