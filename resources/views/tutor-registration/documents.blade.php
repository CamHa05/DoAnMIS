@php
    $documentCount = $documents->count();
    $hasDocuments = $documentCount > 0;
    $hasReachedLimit = $documentCount >= $maximumDocuments;
@endphp

<x-app-layout title="Minh chứng | Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
    <section
        class="tutor-registration-page"
        aria-labelledby="tutor-registration-title"
        style="--tutor-registration-background: url('{{ asset('images/nen.webp') }}')"
    >
        <div class="container tutor-registration-container">
            <header class="tutor-registration-heading">
                <h1 id="tutor-registration-title">Đăng ký trở thành <span>gia sư</span></h1>
                <p>Chia sẻ kiến thức – Truyền cảm hứng – Cùng nhau phát triển</p>
            </header>

            <nav class="tutor-registration-progress" aria-label="Tiến trình đăng ký gia sư">
                <ol>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Thông tin cơ bản</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Chuyên môn</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Hình thức &amp; khu vực</strong>
                    </li>
                    <li class="is-complete">
                        <span aria-hidden="true"><x-directory-icon name="check" /></span>
                        <strong>Lịch rảnh</strong>
                    </li>
                    <li class="is-active" aria-current="step">
                        <span><x-directory-icon name="file-check" /></span>
                        <strong>Minh chứng</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="circle-check" /></span>
                        <strong>Xác nhận</strong>
                    </li>
                </ol>
            </nav>

            <section class="tutor-basic-card tutor-documents-card" aria-labelledby="tutor-documents-title">
                <header class="tutor-basic-card-heading tutor-specialization-heading">
                    <span class="tutor-specialization-heading-icon" aria-hidden="true">
                        <x-directory-icon name="file-check" />
                    </span>
                    <span>
                        <h2 id="tutor-documents-title">Minh chứng</h2>
                        <p>Tải lên tài liệu giúp xác minh chuyên môn của bạn.</p>
                    </span>
                </header>

                @if (session('success'))
                    <div class="tutor-basic-alert" role="status">
                        <x-directory-icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="tutor-basic-alert tutor-specialization-alert-error" role="alert">
                        <x-directory-icon name="flag" />
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div class="tutor-documents-content">
                    <aside class="tutor-specialization-note tutor-documents-note">
                        <x-directory-icon name="shield" />
                        <p>
                            <strong>Tài liệu của bạn được lưu riêng tư.</strong>
                            Chỉ bạn và bộ phận kiểm duyệt hồ sơ mới có thể truy cập minh chứng này.
                        </p>
                    </aside>

                    <section class="tutor-documents-upload" aria-labelledby="tutor-documents-upload-title">
                        <header>
                            <span>
                                <h3 id="tutor-documents-upload-title">Tải lên tài liệu mới</h3>
                                <p>Hỗ trợ PDF, JPG, JPEG và PNG; dung lượng tối đa 5 MB.</p>
                            </span>
                            <strong>{{ $documentCount }}/{{ $maximumDocuments }} tài liệu</strong>
                        </header>

                        <form
                            method="POST"
                            action="{{ route('tutor-registration.documents.store') }}"
                            enctype="multipart/form-data"
                            class="tutor-documents-upload-form"
                        >
                            @csrf

                            <div class="tutor-documents-field">
                                <label for="tutor-document-type">Loại tài liệu <span aria-hidden="true">*</span></label>
                                <select
                                    id="tutor-document-type"
                                    name="document_type"
                                    @disabled($hasReachedLimit)
                                    @if ($errors->has('document_type')) aria-invalid="true" aria-describedby="tutor-document-type-error" @endif
                                >
                                    <option value="">Chọn loại tài liệu</option>
                                    @foreach ($documentTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('document_type')
                                    <p class="tutor-basic-error" id="tutor-document-type-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="tutor-documents-field">
                                <label for="tutor-document-name">Tên tài liệu / Mô tả ngắn <span aria-hidden="true">*</span></label>
                                <input
                                    id="tutor-document-name"
                                    type="text"
                                    name="document_name"
                                    value="{{ old('document_name') }}"
                                    maxlength="255"
                                    placeholder="VD: Chứng chỉ IELTS 6.5, Bảng điểm HK1 năm 2025..."
                                    @disabled($hasReachedLimit)
                                    @if ($errors->has('document_name')) aria-invalid="true" aria-describedby="tutor-document-name-error" @endif
                                >
                                @error('document_name')
                                    <p class="tutor-basic-error" id="tutor-document-name-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="tutor-documents-field">
                                <label for="tutor-document-file">Tệp minh chứng <span aria-hidden="true">*</span></label>
                                <input
                                    id="tutor-document-file"
                                    type="file"
                                    name="document"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    @disabled($hasReachedLimit)
                                    @if ($errors->has('document')) aria-invalid="true" aria-describedby="tutor-document-file-error" @endif
                                >
                                @error('document')
                                    <p class="tutor-basic-error" id="tutor-document-file-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <button class="button tutor-documents-upload-submit" type="submit" @disabled($hasReachedLimit)>
                                Tải lên
                            </button>
                        </form>

                        @if ($hasReachedLimit)
                            <p class="tutor-documents-limit" role="status">Bạn đã tải lên tối đa {{ $maximumDocuments }} tài liệu.</p>
                        @endif
                    </section>

                    <section class="tutor-documents-list" aria-labelledby="tutor-documents-list-title">
                        <header>
                            <h3 id="tutor-documents-list-title">Tài liệu đã tải lên</h3>
                            <span>{{ $documentCount }} tài liệu</span>
                        </header>

                        @if ($hasDocuments)
                            <div class="tutor-documents-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Tên tài liệu</th>
                                            <th scope="col">Loại tài liệu</th>
                                            <th scope="col">Ngày tải lên</th>
                                            <th scope="col">Trạng thái</th>
                                            <th scope="col">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($documents as $document)
                                            @php
                                                $documentId = (int) $document->document_id;
                                                $isDownloadable = $downloadableDocumentIds[$documentId] ?? false;
                                            @endphp
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    <span class="tutor-documents-name">
                                                        <x-directory-icon name="document" />
                                                        <span>{{ $document->document_name ?: 'Tài liệu minh chứng' }}</span>
                                                    </span>
                                                </td>
                                                <td>{{ $document->typeLabel() }}</td>
                                                <td>{{ $document->uploaded_at?->format('d/m/Y') ?? '—' }}</td>
                                                <td>
                                                    <span class="tutor-documents-status is-{{ $document->verificationStatusTone() }}">
                                                        {{ $document->verificationStatusLabel() }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="tutor-documents-row-actions">
                                                        @if ($isDownloadable)
                                                            <a href="{{ route('tutor-registration.documents.download', $documentId) }}">
                                                                Tải xuống
                                                            </a>
                                                        @else
                                                            <span class="tutor-documents-unavailable">Tệp không khả dụng</span>
                                                        @endif

                                                        @if ($document->canBeDeletedByTutor())
                                                            <form method="POST" action="{{ route('tutor-registration.documents.destroy', $documentId) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit">Xóa</button>
                                                            </form>
                                                        @else
                                                            <span class="tutor-documents-locked">Không thể xóa</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="tutor-documents-empty" role="status">
                                <x-directory-icon name="document" />
                                <h4>Chưa có tài liệu minh chứng</h4>
                                <p>Hãy tải lên ít nhất một tài liệu trước khi tiếp tục.</p>
                            </div>
                        @endif
                    </section>

                    <footer class="tutor-basic-actions tutor-specialization-actions tutor-documents-actions">
                        <a class="tutor-basic-back" href="{{ route('tutor-registration.availability.edit') }}">
                            <x-directory-icon name="arrow-left" />
                            Quay lại
                        </a>

                        <form method="POST" action="{{ route('tutor-registration.documents.continue') }}">
                            @csrf
                            <button class="button tutor-basic-submit" type="submit" @disabled(! $hasDocuments)>
                                Tiếp tục
                                <x-directory-icon name="chevron-right" />
                            </button>
                        </form>
                    </footer>
                </div>
            </section>
        </div>
    </section>
</x-app-layout>
