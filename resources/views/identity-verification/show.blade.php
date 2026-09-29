<x-app-layout>
    @php
        $status = $verification?->status;
        $isReadonly = in_array($status, [
            \App\Models\IdentityVerification::STATUS_PENDING,
            \App\Models\IdentityVerification::STATUS_VERIFIED,
        ], true);
        $isRejected = $status === \App\Models\IdentityVerification::STATUS_REJECTED;
        $maskedDocumentNumber = $verification
            ? str_repeat('*', max(strlen($verification->document_number) - 4, 0)) . substr($verification->document_number, -4)
            : null;
        $storedImageData = static function (?string $path): ?string {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');

            if (! $path || ! $disk->exists($path)) {
                return null;
            }

            $mimeType = $disk->mimeType($path) ?: 'image/jpeg';

            return 'data:' . $mimeType . ';base64,' . base64_encode($disk->get($path));
        };
    @endphp

    <div class="identity-verification-page">
        <div class="identity-verification-breadcrumb" aria-label="Breadcrumb">
            <span aria-hidden="true">⌂</span>
            <span>Tài khoản</span>
            <span aria-hidden="true">›</span>
            <strong>Xác minh danh tính</strong>
        </div>

        <div class="identity-verification-layout">
            <div class="identity-verification-main">
                <header class="identity-verification-heading">
                    <h1>Xác minh danh tính</h1>
                    <p>Xác minh danh tính giúp tăng độ tin cậy và là điều kiện để sử dụng các chức năng quan trọng trên GiaSu.</p>
                </header>

                @if (session('success'))
                    <div class="identity-alert identity-alert--success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="identity-alert identity-alert--error">{{ session('error') }}</div>
                @endif

                <section class="identity-protection-banner">
                        <span class="identity-icon identity-icon--banner" aria-hidden="true">&#10003;</span>
                        <div>
                            <h2>Bảo vệ tài khoản của bạn</h2>
                            <p>Thông tin được sử dụng để kiểm duyệt danh tính trên GiaSu.</p>
                            <p>Trong môi trường đồ án, chỉ sử dụng giấy tờ mẫu hoặc dữ liệu giả lập.</p>
                        </div>
                </section>

                <section id="identity-verification-form" class="identity-card identity-form-card {{ $isReadonly ? 'identity-form-card--readonly' : '' }}">
                        <div class="identity-card-heading">
                            <span class="identity-icon" aria-hidden="true">▤</span>
                            <div>
                                <h2>Thông tin xác minh</h2>
                                <p>Vui lòng cung cấp đầy đủ thông tin trên căn cước công dân.</p>
                            </div>
                        </div>

                    <form action="{{ route('identity-verification.submit') }}" method="POST" enctype="multipart/form-data" data-identity-form @if ($isReadonly) onsubmit="return false" @endif>
                            @csrf
                            <div class="identity-fields-row">
                                <div class="identity-field">
                                    <label for="document_type">Loại giấy tờ <span>*</span></label>
                                    <select id="document_type" name="document_type" required @disabled($isReadonly)>
                                        <option value="{{ $verification?->document_type ?? 'CCCD' }}">{{ $verification?->document_type === 'CCCD' ? 'Căn cước công dân' : ($verification?->document_type ?? 'Căn cước công dân') }}</option>
                                    </select>
                                    @error('document_type') <p class="identity-field-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="identity-field">
                                    <label for="document_number">Số căn cước công dân <span>*</span></label>
                                    <input id="document_number" type="text" name="document_number" inputmode="numeric" maxlength="12" placeholder="Nhập 12 chữ số" value="{{ $isReadonly ? $maskedDocumentNumber : old('document_number', $verification?->document_number) }}" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 12)" required @readonly($isReadonly)>
                                    <p class="identity-helper">{{ $isReadonly ? 'Thông tin đã được gửi và hiện không thể chỉnh sửa.' : 'Nhập đúng 12 chữ số trên căn cước công dân.' }}</p>
                                    @error('document_number') <p class="identity-field-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @if ($isRejected)
                                <div class="identity-rejection-reason">
                                    <strong>Lý do cần cập nhật</strong>
                                    <p>{{ $verification->rejection_reason ?: 'Vui lòng kiểm tra lại thông tin và hình ảnh giấy tờ.' }}</p>
                                </div>
                            @endif

                            <div class="identity-upload-section">
                                <div class="identity-section-heading">
                                    <span class="identity-icon" aria-hidden="true">▧</span>
                                    <h2>Ảnh giấy tờ</h2>
                                </div>
                                <div class="identity-upload-grid">
                                    @foreach ([['front_image', 'Mặt trước'], ['back_image', 'Mặt sau']] as [$field, $label])
                                        <div class="identity-upload-field" data-identity-upload>
                                            <label for="{{ $field }}">{{ $label }} <span>*</span></label>
                                            @if ($isReadonly)
                                                <div class="identity-upload-card identity-upload-card--readonly">
                                                    @php
                                                        $storedPath = $field === 'front_image' ? $verification->front_image_path : $verification->back_image_path;
                                                        $storedPreview = $storedImageData($storedPath);
                                                    @endphp
                                                    @if ($storedPreview)
                                                        <button
                                                            class="identity-stored-preview-button"
                                                            type="button"
                                                            aria-label="Xem ảnh {{ strtolower($label) }} kích thước lớn"
                                                            data-identity-image-open
                                                            data-identity-image-title="{{ $label }}"
                                                        >
                                                            <img class="identity-stored-preview-image" src="{{ $storedPreview }}" alt="Ảnh {{ strtolower($label) }} đã gửi">
                                                            <span class="identity-stored-preview-hint" aria-hidden="true">Xem ảnh</span>
                                                        </button>
                                                    @else
                                                        <span class="identity-stored-preview" aria-hidden="true">▧</span>
                                                    @endif
                                                    <span class="identity-upload-file-copy">
                                                        <small>Đã gửi</small>
                                                    </span>
                                                </div>
                                            @else
                                                <input id="{{ $field }}" type="file" name="{{ $field }}" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required data-identity-file-input>
                                                <label class="identity-upload-card" for="{{ $field }}" data-identity-upload-card>
                                                    <span class="identity-upload-empty" data-identity-upload-empty>
                                                        <span class="identity-upload-icon" aria-hidden="true">▧</span>
                                                        <strong>Nhấn để tải ảnh lên</strong>
                                                        <small>JPG, PNG hoặc WEBP · tối đa 5 MB</small>
                                                    </span>
                                                    <span class="identity-upload-preview" data-identity-upload-preview hidden>
                                                        <img src="" alt="" data-identity-preview-image>
                                                        <span class="identity-upload-file-copy"><strong data-identity-file-name>Ảnh đã chọn</strong><small data-identity-file-meta></small><b>Thay ảnh</b></span>
                                                        <span class="identity-upload-remove" aria-hidden="true">×</span>
                                                    </span>
                                                </label>
                                            @endif
                                            @error($field) <p class="identity-field-error">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            @unless ($isReadonly)
                                <label class="identity-confirmation">
                                <input type="checkbox" name="privacy_confirmation" value="1" {{ old('privacy_confirmation') ? 'checked' : '' }}>
                                <span>Tôi xác nhận thông tin được cung cấp chỉ nhằm mô phỏng quy trình xác minh danh tính trong hệ thống GiaSu.</span>
                                </label>
                                @error('privacy_confirmation') <p class="identity-field-error">{{ $message }}</p> @enderror

                                <button class="identity-submit-button" type="submit"><span aria-hidden="true">➤</span>{{ $isRejected ? 'Cập nhật và gửi lại' : 'Gửi xác minh' }}</button>
                            @else
                                <p class="identity-readonly-note"><span aria-hidden="true">i</span> Hồ sơ đã gửi. Bạn chỉ có thể xem lại thông tin tại đây.</p>
                            @endunless
                        </form>
                    </section>
            </div>

            <aside class="identity-sidebar">
                <section class="identity-card identity-status-card">
                    <div class="identity-card-heading">
                        <span class="identity-icon identity-icon--info" aria-hidden="true">i</span>
                        <div>
                            <h2>Trạng thái xác minh</h2>
                            <p>{{ $status === \App\Models\IdentityVerification::STATUS_PENDING ? 'Hồ sơ đang chờ quản trị viên kiểm tra.' : ($status === \App\Models\IdentityVerification::STATUS_VERIFIED ? 'Thông tin xác minh của bạn đã được hoàn tất.' : ($isRejected ? 'Bạn có thể cập nhật hồ sơ và gửi lại.' : 'Làm theo hướng dẫn dưới đây để gửi hồ sơ xác minh danh tính.')) }}</p>
                        </div>
                    </div>

                    @if ($status === \App\Models\IdentityVerification::STATUS_PENDING)
                        <div class="identity-state-panel identity-state-panel--pending">
                            <span class="identity-status-badge">Chờ duyệt</span>
                            <h3>Đang chờ kiểm duyệt</h3>
                            <p>Hồ sơ xác minh danh tính của bạn đã được gửi và đang chờ quản trị viên kiểm tra.</p>
                            @if ($verification->submitted_at)<p><strong>Gửi lúc:</strong> {{ $verification->submitted_at->format('d/m/Y H:i') }}</p>@endif
                            <small>Bạn chưa thể chỉnh sửa hồ sơ trong thời gian kiểm duyệt.</small>
                        </div>
                    @elseif ($status === \App\Models\IdentityVerification::STATUS_VERIFIED)
                        <div class="identity-state-panel identity-state-panel--verified">
                            <span class="identity-status-badge">Đã xác minh</span>
                            <h3>Đã xác minh danh tính</h3>
                            <p>Hồ sơ của bạn đã được GiaSu kiểm duyệt.</p>
                            <dl class="identity-summary-list">
                                <div><dt>Loại giấy tờ</dt><dd>{{ $verification->document_type }}</dd></div>
                                <div><dt>Số giấy tờ</dt><dd>{{ str_repeat('*', max(strlen($verification->document_number) - 4, 0)) }}{{ substr($verification->document_number, -4) }}</dd></div>
                                @if ($verification->verified_at)<div><dt>Thời gian xác minh</dt><dd>{{ $verification->verified_at->format('d/m/Y H:i') }}</dd></div>@endif
                            </dl>
                        </div>
                    @elseif ($isRejected)
                        <div class="identity-state-panel identity-state-panel--rejected">
                            <span class="identity-status-badge">Chưa thành công</span>
                            <h3>Xác minh chưa thành công</h3>
                            <p>Vui lòng cập nhật lại thông tin và hình ảnh giấy tờ để gửi lại hồ sơ.</p>
                            <a class="identity-retry-link" href="#identity-verification-form">Cập nhật và gửi lại <span aria-hidden="true">↓</span></a>
                            <small>Bạn có thể cập nhật lại thông tin và gửi xác minh mới.</small>
                        </div>
                    @else
                        <div class="identity-guide-panel">
                            <div class="identity-guide-heading"><span class="identity-icon" aria-hidden="true">▤</span><div><h3>Các bước xác minh danh tính</h3><p>Chỉ với 3 bước đơn giản, bạn có thể hoàn tất việc xác minh danh tính trên GiaSu.</p></div></div>
                            <ol class="identity-stepper">
                                <li><span>1</span><div><strong>Điền số CCCD</strong><p>Nhập chính xác 12 chữ số trên căn cước công dân.</p></div></li>
                                <li><span>2</span><div><strong>Tải ảnh mặt trước và mặt sau</strong><p>Chụp hoặc tải lên ảnh rõ nét, đầy đủ thông tin.</p></div></li>
                                <li><span>3</span><div><strong>Gửi hồ sơ để quản trị viên kiểm duyệt</strong><p>Sau khi gửi, hồ sơ của bạn sẽ được đội ngũ quản trị viên xem xét và xác minh.</p></div></li>
                            </ol>
                            <p class="identity-info-note"><span aria-hidden="true">◷</span> Sau khi gửi hồ sơ, trạng thái hồ sơ của bạn sẽ chuyển sang <strong>“Chờ duyệt”</strong>. Bạn sẽ nhận được thông báo khi có kết quả.</p>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>

    @if ($isReadonly)
        <dialog
            class="identity-image-dialog"
            aria-labelledby="identity-image-dialog-title"
            data-identity-image-dialog
        >
            <div class="identity-image-dialog__surface">
                <header class="identity-image-dialog__header">
                    <h2 id="identity-image-dialog-title" data-identity-image-title>Xem ảnh giấy tờ</h2>
                    <button
                        class="identity-image-dialog__close"
                        type="button"
                        aria-label="Đóng ảnh xem lớn"
                        data-identity-image-close
                        data-identity-image-initial
                    >×</button>
                </header>
                <figure class="identity-image-dialog__figure">
                    <img src="" alt="" data-identity-image-preview>
                </figure>
            </div>
        </dialog>
    @endif
</x-app-layout>
