<x-app-layout>
    <x-slot name="title">GiaSu — Tìm đúng người, học đúng cách</x-slot>

    <section class="hero-shell">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Nền tảng kết nối gia sư</p>
                <h1>Học tốt hơn khi có <span>đúng người</span> đồng hành.</h1>
                <p class="hero-lede">Tìm gia sư phù hợp với mục tiêu, lịch học và cách học của bạn — rõ ràng, chủ động, không mất thời gian.</p>
                <x-search-panel
                    :subjects="$subjects"
                    :locations="$locations" />
                @unless (auth()->user()?->is_admin)
                    <div class="hero-request-option" id="request-options">
                        <strong>Chưa tìm được người phù hợp?</strong>
                        <span>Đăng yêu cầu để gia sư phù hợp chủ động ứng tuyển.</span>
                        <a href="#request-options">Đăng yêu cầu <x-directory-icon name="arrow" /></a>
                    </div>
                @endunless
                <div class="hero-note"><x-directory-icon class="note-check" name="check" /> Hồ sơ minh bạch · Chủ động chọn lịch · Học online hoặc tại nhà</div>
            </div>

            <div class="hero-visual" aria-label="Minh họa cách GiaSu kết nối người học và gia sư">
                <div class="visual-orbit orbit-one"></div>
                <div class="visual-orbit orbit-two"></div>
                <div class="snapshot-label">Kết nối phù hợp <x-directory-icon name="arrow" /></div>
                <div class="snapshot-card snapshot-main snapshot-illustration">
                    <div class="illustration-topline"><span class="illustration-dot"></span><span class="illustration-dot"></span><span class="illustration-dot"></span><span>GiaSu matching</span></div>
                    <div class="illustration-map"><span class="map-line line-a"></span><span class="map-line line-b"></span><span class="map-node node-a">Bạn</span><span class="map-node node-b">Gia sư</span><span class="map-node node-c">Mục tiêu</span><span class="map-center"><x-directory-icon name="arrow" /></span></div>
                    <div class="illustration-footer"><strong>Từ nhu cầu đến kết nối</strong><span>01 — 03</span></div>
                </div>
                <div class="snapshot-card snapshot-float float-top"><span class="float-icon"><x-directory-icon name="search" /></span>
                    <div><strong>Tìm theo nhu cầu</strong><small>Môn học · khu vực · hình thức</small></div>
                </div>
                <div class="snapshot-card snapshot-float float-bottom"><span class="float-icon"><x-directory-icon name="calendar" /></span>
                    <div><strong>Hoặc đăng yêu cầu</strong><small>Để gia sư phù hợp ứng tuyển</small></div>
                </div>
                <div class="visual-caption"><span class="caption-line"></span><span>Hai cách bắt đầu</span></div>
            </div>
        </div>
    </section>

    <section class="subject-section section-pad" aria-labelledby="subjects-heading">
        <div class="container">
            <div class="section-heading-row">
                <x-section-heading eyebrow="Khám phá" title="Môn học phổ biến" copy="Chọn một môn học để xem những gia sư phù hợp." />
                <a class="text-link" href="#featured-tutors">Xem tất cả môn học <x-directory-icon name="arrow" /></a>
            </div>
            @if ($popularSubjects->isNotEmpty())
            <div class="subject-grid">
                @foreach ($popularSubjects as $subject)
                <x-subject-item :subject="$subject" :href="route('home', ['subject' => $subject->subject_id]) . '#featured-tutors'" />
                @endforeach
            </div>
            @else
            <div class="empty-state">Chưa có môn học đang hoạt động để hiển thị.</div>
            @endif
        </div>
    </section>

    <section class="tutors-section section-pad" id="featured-tutors" aria-labelledby="tutors-heading">
        <div class="container">
            <div class="section-heading-row tutors-heading-row">
                <x-section-heading eyebrow="Gia sư nổi bật" title="Những người dạy bằng cả chuyên môn và sự tận tâm." copy="Xem thông tin hồ sơ và chọn người phù hợp với cách học của bạn." />
                <a class="text-link" href="{{ route('tutors.index') }}">Xem tất cả gia sư <x-directory-icon name="arrow" /></a>
            </div>
            @if ($featuredTutors->isNotEmpty())
            <div class="tutor-grid">
                @foreach ($featuredTutors as $tutor)
                <x-tutor-card :tutor="$tutor" />
                @endforeach
            </div>
            @else
            <div class="empty-state">Chưa có hồ sơ gia sư phù hợp để hiển thị.</div>
            @endif
        </div>
    </section>

    <section class="requests-section section-pad" id="learning-requests" aria-labelledby="requests-heading">
        <div class="container">
            <div class="section-heading-row">
                <x-section-heading eyebrow="Dành cho gia sư" title="Lớp đang tìm gia sư" copy="Những nhu cầu công khai đang chờ người đồng hành phù hợp." />
                  <a class="text-link" href="{{ route('requests.index') }}">Xem tất cả lớp <x-directory-icon name="arrow" /></a>
            </div>
            @if ($learningRequests->isNotEmpty())
            <div class="request-grid">
                @foreach ($learningRequests as $learningRequest)
                <x-learning-request-card :request="$learningRequest" />
                @endforeach
            </div>
            @else
            <div class="empty-state">Hiện chưa có lớp công khai đang tìm gia sư.</div>
            @endif
        </div>
    </section>

    <section class="process-section section-pad" aria-labelledby="process-heading">
        <div class="container process-grid process-grid-dual">
            <div class="process-intro">
                <p class="eyebrow">Cách hoạt động</p>
                <h2>Hai cách để bắt đầu một kết nối phù hợp.</h2>
                <p>GiaSu giúp người học và gia sư chủ động tìm thấy nhau theo nhu cầu thật.</p>
            </div>
            <div class="process-track">
                <div class="process-track-label">Dành cho người học</div>
                <div class="process-list">
                    <div class="process-step"><span class="step-number">01</span>
                        <div>
                            <h3>Tìm gia sư hoặc đăng yêu cầu</h3>
                            <p>Chọn hồ sơ phù hợp hoặc mô tả nhu cầu để gia sư chủ động ứng tuyển.</p>
                        </div>
                    </div>
                    <div class="process-step"><span class="step-number">02</span>
                        <div>
                            <h3>Kết nối với gia sư phù hợp</h3>
                            <p>Xem thông tin cần thiết và chọn người đồng hành.</p>
                        </div>
                    </div>
                    <div class="process-step"><span class="step-number">03</span>
                        <div>
                            <h3>Thống nhất và bắt đầu học</h3>
                            <p>Hai bên chủ động thống nhất cách học phù hợp.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="process-track tutor-process-track">
                <div class="process-track-label">Dành cho gia sư</div>
                <div class="process-list">
                    <div class="process-step"><span class="step-number">01</span>
                        <div>
                            <h3>Đăng ký và hoàn thiện hồ sơ</h3>
                            <p>Cung cấp thông tin về chuyên môn, môn dạy và hình thức nhận lớp.</p>
                        </div>
                    </div>
                    <div class="process-step"><span class="step-number">02</span>
                        <div>
                            <h3>Tìm lớp phù hợp</h3>
                            <p>Xem các yêu cầu công khai phù hợp với khả năng của bạn.</p>
                        </div>
                    </div>
                    <div class="process-step"><span class="step-number">03</span>
                        <div>
                            <h3>Kết nối và bắt đầu giảng dạy</h3>
                            <p>Chủ động ứng tuyển và thống nhất trực tiếp với người học.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="trust-section section-pad" aria-labelledby="trust-heading">
        <div class="container trust-grid">
            <div class="trust-quote"><span class="quote-mark">“</span>
                <p>Không có một cách học đúng cho tất cả mọi người. Có một người phù hợp với cách bạn học.</p><span class="quote-rule"></span><small>Triết lý của GiaSu</small>
            </div>
            <div class="trust-list"><x-section-heading eyebrow="Vì sao sử dụng GiaSu" title="Rõ ràng để bắt đầu, linh hoạt để gắn bó." />
                <div class="trust-item"><span>01</span>
                    <div>
                        <h3>Hồ sơ gia sư được kiểm duyệt</h3>
                        <p>Thông tin hồ sơ và minh chứng được kiểm tra trước khi gia sư được nhận lớp.</p>
                    </div>
                </div>
                <div class="trust-item"><span>02</span>
                    <div>
                        <h3>Tìm kiếm theo nhu cầu</h3>
                        <p>Lọc theo môn học, cấp học, khu vực, hình thức và các tiêu chí phù hợp.</p>
                    </div>
                </div>
                <div class="trust-item"><span>03</span>
                    <div>
                        <h3>Online hoặc tại nhà</h3>
                        <p>Người học có thể lựa chọn hình thức học phù hợp với điều kiện của mình.</p>
                    </div>
                </div>
                <div class="trust-item"><span>04</span>
                    <div>
                        <h3>Hai cách kết nối</h3>
                        <p>Tự chọn gia sư hoặc đăng yêu cầu để gia sư phù hợp chủ động ứng tuyển.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section section-pad" id="final-cta">
        <div class="container cta-inner cta-inner-stacked">
            <div>
                <p class="eyebrow eyebrow-light">Sẵn sàng bắt đầu?</p>
                <h2>Chưa tìm được gia sư phù hợp?</h2>
                <p>Bạn có thể tự tìm kiếm hoặc đăng nhu cầu để các gia sư phù hợp chủ động ứng tuyển.</p>
            </div>
            <div class="cta-actions">
                <a class="button button-light" href="#featured-tutors">Tìm gia sư <x-directory-icon name="arrow" /></a>
                @unless (auth()->user()?->is_admin)
                    <a class="button button-outline-light" href="#request-options">Đăng yêu cầu <x-directory-icon name="arrow" /></a>
                @endunless
            </div>
        </div>
    </section>
</x-app-layout>
