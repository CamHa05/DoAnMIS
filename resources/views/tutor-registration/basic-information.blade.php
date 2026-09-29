<x-app-layout title="Thông tin cơ bản | Hồ sơ gia sư | GiaSu" body-class="tutor-registration-shell">
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
                    <li class="is-active" aria-current="step">
                        <span><x-directory-icon name="user-round" /></span>
                        <strong>Thông tin cơ bản</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="book-open" /></span>
                        <strong>Chuyên môn</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="map-pin" /></span>
                        <strong>Hình thức &amp; khu vực</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="calendar-days" /></span>
                        <strong>Lịch rảnh</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="file-check" /></span>
                        <strong>Minh chứng</strong>
                    </li>
                    <li>
                        <span><x-directory-icon name="circle-check" /></span>
                        <strong>Xác nhận</strong>
                    </li>
                </ol>
            </nav>

            <section class="tutor-basic-card" aria-labelledby="tutor-basic-title">
                <header class="tutor-basic-card-heading">
                    <h2 id="tutor-basic-title">Thông tin cơ bản</h2>
                    <p>Hãy cho chúng tôi biết thêm về bạn. Những thông tin này sẽ giúp học viên hiểu bạn hơn.</p>
                </header>

                @if (session('success'))
                    <div class="tutor-basic-alert" role="status">
                        <x-directory-icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('tutor-registration.basic.update') }}" class="tutor-basic-form">
                    @csrf
                    @method('PUT')

                    <div class="tutor-basic-identity">
                        <div class="tutor-basic-avatar-block">
                            <div class="tutor-basic-avatar">
                                <x-tutor-avatar :user="$user" size="large" />
                            </div>
                            <div>
                                <strong>Ảnh đại diện</strong>
                                <p>
                                    <x-directory-icon name="lock" />
                                    Thông tin từ hồ sơ cá nhân
                                </p>
                            </div>
                        </div>

                        <div class="tutor-basic-name-block">
                            <label for="tutor-registration-name">Họ và tên <span aria-hidden="true">*</span></label>
                            <input
                                id="tutor-registration-name"
                                type="text"
                                value="{{ $user->full_name }}"
                                readonly
                                aria-describedby="tutor-registration-name-help"
                            >
                            <p id="tutor-registration-name-help">
                                <x-directory-icon name="lock" />
                                Thông tin từ hồ sơ cá nhân
                            </p>
                        </div>
                    </div>

                    <div class="tutor-basic-fields">
                        <div class="tutor-basic-field tutor-basic-field--headline">
                            <label for="headline">Tiêu đề hồ sơ <span aria-hidden="true">*</span></label>
                            <input
                                id="headline"
                                name="headline"
                                type="text"
                                value="{{ old('headline', $tutorProfile?->headline) }}"
                                maxlength="150"
                                placeholder="VD: Gia sư Toán THCS – Dễ hiểu, kiên nhẫn"
                                required
                                aria-required="true"
                                @error('headline') aria-invalid="true" aria-describedby="headline-error headline-help" @else aria-describedby="headline-help" @enderror
                            >
                            @error('headline')
                                <p class="tutor-basic-error" id="headline-error" role="alert">{{ $message }}</p>
                            @enderror
                            <p class="tutor-basic-support" id="headline-help">Một câu ngắn giúp người học nhanh chóng hiểu thế mạnh của bạn.</p>
                        </div>

                        <div class="tutor-basic-field tutor-basic-field--bio">
                            <label for="bio">Giới thiệu bản thân <span aria-hidden="true">*</span></label>
                            <textarea
                                id="bio"
                                name="bio"
                                rows="6"
                                placeholder="Hãy chia sẻ ngắn gọn về bản thân, phong cách giảng dạy và điểm mạnh của bạn..."
                                required
                                aria-required="true"
                                @error('bio') aria-invalid="true" aria-describedby="bio-error" @enderror
                            >{{ old('bio', $tutorProfile?->bio) }}</textarea>
                            @error('bio')
                                <p class="tutor-basic-error" id="bio-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="tutor-basic-field tutor-basic-field--education">
                            <label for="education_summary">Học vấn <span aria-hidden="true">*</span></label>
                            <span class="tutor-basic-input-with-icon">
                                <x-directory-icon name="education" />
                                <input
                                    id="education_summary"
                                    name="education_summary"
                                    type="text"
                                    value="{{ old('education_summary', $tutorProfile?->education_summary) }}"
                                    maxlength="500"
                                    placeholder="Nhập trình độ và quá trình học vấn"
                                    required
                                    aria-required="true"
                                    @error('education_summary') aria-invalid="true" aria-describedby="education-summary-error" @enderror
                                >
                            </span>
                            @error('education_summary')
                                <p class="tutor-basic-error" id="education-summary-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="tutor-basic-field tutor-basic-field--experience">
                            <label for="teaching_experience">Kinh nghiệm giảng dạy <span aria-hidden="true">*</span></label>
                            <textarea
                                id="teaching_experience"
                                name="teaching_experience"
                                rows="4"
                                placeholder="Mô tả kinh nghiệm và đối tượng học viên bạn từng giảng dạy"
                                required
                                aria-required="true"
                                @error('teaching_experience') aria-invalid="true" aria-describedby="teaching-experience-error" @enderror
                            >{{ old('teaching_experience', $tutorProfile?->teaching_experience) }}</textarea>
                            @error('teaching_experience')
                                <p class="tutor-basic-error" id="teaching-experience-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="tutor-basic-field tutor-basic-field--fee">
                            <label for="hourly_rate">Học phí mong muốn <span aria-hidden="true">*</span></label>
                            <span class="tutor-basic-money-input">
                                <x-directory-icon name="wallet" />
                                <input
                                    id="hourly_rate"
                                    name="hourly_rate"
                                    type="number"
                                    value="{{ old('hourly_rate', $tutorProfile?->hourly_rate) }}"
                                    min="0"
                                    max="9999999999.99"
                                    step="0.01"
                                    inputmode="decimal"
                                    placeholder="Nhập học phí"
                                    required
                                    aria-required="true"
                                    @error('hourly_rate') aria-invalid="true" aria-describedby="hourly-rate-error hourly-rate-help" @else aria-describedby="hourly-rate-help" @enderror
                                >
                                <small>VNĐ/giờ</small>
                            </span>
                            @error('hourly_rate')
                                <p class="tutor-basic-error" id="hourly-rate-error" role="alert">{{ $message }}</p>
                            @enderror
                            <p class="tutor-basic-support" id="hourly-rate-help">Mức học phí này sẽ được hiển thị trên hồ sơ gia sư sau khi hồ sơ được duyệt.</p>
                        </div>
                    </div>

                    <footer class="tutor-basic-actions">
                        <a class="tutor-basic-back" href="{{ route('home') }}">
                            <x-directory-icon name="arrow-left" />
                            Quay lại
                        </a>
                        <button class="button tutor-basic-submit" type="submit">
                            Tiếp tục
                            <x-directory-icon name="chevron-right" />
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </section>
</x-app-layout>
