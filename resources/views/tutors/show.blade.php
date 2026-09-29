<x-app-layout>
    @php
        $name = trim((string) ($tutor->user?->full_name ?: 'Gia sư'));
        $subjects = $tutor->tutorSubjects
            ->filter(fn ($tutorSubject) => $tutorSubject->subject)
            ->unique('subject_id');
        $modes = collect([
            $tutor->supports_online ? 'Online' : null,
            $tutor->supports_offline ? 'Tại nhà' : null,
        ])->filter();
        $areas = $tutor->teachingAreas
            ->map(fn ($area) => trim(implode(', ', array_filter([
                $area->ward?->ward_name,
                $area->ward?->province?->province_name,
            ]))))
            ->filter()
            ->unique();
        $provinces = $tutor->teachingAreas->pluck('ward.province.province_name')->filter()->unique();
        $subjectLevelsBySubject = $subjects->mapWithKeys(fn ($subject) => [
            $subject->subject_id => $subject->tutorSubjectLevels->pluck('subjectLevel.level_name')->filter()->unique(),
        ]);
        $hourlyRate = $tutor->hourly_rate !== null
            ? number_format((float) $tutor->hourly_rate, 0, ',', '.') . 'đ'
            : null;
        $bioParagraphs = collect(preg_split('/\R\s*\R/u', trim((string) $tutor->bio)))->filter();
    @endphp

    <section class="tutor-detail-page tutor-profile-compact">
        <div class="container">
            <nav class="tutor-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Trang chủ</a>
                <x-directory-icon name="chevron-right" />
                <a href="{{ route('tutors.index') }}">Gia sư</a>
                <x-directory-icon name="chevron-right" />
                <span aria-current="page">{{ $name }}</span>
            </nav>

            <section class="tutor-profile-header" aria-labelledby="tutor-name">
                <div class="tutor-profile-avatar">
                    <x-tutor-avatar :user="$tutor->user" size="large" />
                </div>

                <div class="tutor-profile-heading">
                    <p class="eyebrow">Hồ sơ gia sư</p>
                    <div class="tutor-profile-name-row">
                        <h1 id="tutor-name">{{ $name }}</h1>
                        <span class="profile-approved-badge"><x-directory-icon name="check" /> Đã kiểm duyệt</span>
                    </div>
                    @if ($tutor->headline)
                        <p class="tutor-profile-headline">{{ $tutor->headline }}</p>
                    @endif
                    <div class="tutor-profile-meta">
                        @if ($subjects->isNotEmpty())
                            <span>{{ $subjects->pluck('subject.subject_name')->join(' · ') }}</span>
                        @endif
                        @if ($modes->isNotEmpty())
                            <span>{{ $modes->join(' · ') }}</span>
                        @endif
                        @if ($hourlyRate !== null)
                            <strong>{{ $hourlyRate }} / giờ</strong>
                        @endif
                    </div>
                </div>
            </section>

            <div class="tutor-detail-layout">
                <div class="tutor-detail-content">
                    <section class="tutor-detail-section" aria-labelledby="about-heading">
                        <h2 id="about-heading"><b>Giới thiệu</b></h2>
                        <div class="tutor-rich-copy">
                            @forelse ($bioParagraphs as $paragraph)
                                <p>{!! nl2br(e($paragraph)) !!}</p>
                            @empty
                                <p class="tutor-muted-message">Gia sư chưa cập nhật phần giới thiệu.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="tutor-detail-section" aria-labelledby="profile-info-heading">
                        <h2 id="profile-info-heading"><b>Thông tin gia sư</b></h2>
                        <dl class="tutor-profile-facts">
                            <div>
                                <dt><b>Học vấn</b></dt>
                                <dd>{!! nl2br(e(filled($tutor->education_summary) ? $tutor->education_summary : 'Chưa cập nhật thông tin học vấn.')) !!}</dd>
                            </div>
                            <div>
                                <dt><b>Kinh nghiệm</b></dt>
                                <dd>{!! nl2br(e(filled($tutor->teaching_experience) ? $tutor->teaching_experience : 'Chưa cập nhật kinh nghiệm giảng dạy.')) !!}</dd>
                            </div>
                            <div>
                                <dt><b>Hình thức</b></dt>
                                <dd>{{ $modes->isNotEmpty() ? $modes->join(' · ') : 'Chưa cập nhật hình thức dạy.' }}</dd>
                            </div>
                            @if ($tutor->supports_offline)
                                <div>
                                    <dt><b>Khu vực</b></dt>
                                    <dd>
                                        @if ($provinces->isNotEmpty())
                                            <a class="tutor-area-jump" href="#areas-heading">{{ $provinces->join(' · ') }} <x-directory-icon name="arrow" /></a>
                                        @else
                                            Chưa cập nhật khu vực nhận dạy.
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="tutor-detail-section" aria-labelledby="evidence-heading">
                        <h2 id="evidence-heading"><b>Minh chứng năng lực</b></h2>
                        @if ($tutor->documents->isNotEmpty())
                            <div class="tutor-evidence-list">
                                @foreach ($tutor->documents as $document)
                                    <article class="tutor-evidence-item">
                                        <span class="tutor-evidence-icon" aria-hidden="true">
                                            <x-directory-icon name="file-check" />
                                        </span>
                                        <div class="tutor-evidence-content">
                                            <h3>{{ filled($document->document_name) ? $document->document_name : $document->typeLabel() }}</h3>
                                            <p>{{ $document->typeLabel() }}</p>
                                        </div>
                                        <span class="tutor-evidence-status">
                                            <x-directory-icon name="check" />
                                            Đã xác minh
                                        </span>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="tutor-muted-message">Gia sư chưa có minh chứng năng lực công khai.</p>
                        @endif
                    </section>

                    <section class="tutor-detail-section" aria-labelledby="subjects-heading">
                        <h2 id="subjects-heading"><b>Chuyên môn</b></h2>
                        <div class="tutor-subject-list">
                            @forelse ($subjects as $tutorSubject)
                                @php
                                    $subjectLevels = $subjectLevelsBySubject->get($tutorSubject->subject_id);
                                @endphp
                                <div class="tutor-subject-row">
                                    <h3>{{ $tutorSubject->subject->subject_name }}</h3>
                                    @if ($subjectLevels->isNotEmpty())
                                        <div class="tutor-chip-list">
                                            @foreach ($subjectLevels as $level)
                                                <span class="tutor-chip">{{ $level }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p>Đang cập nhật cấp độ giảng dạy.</p>
                                    @endif
                                </div>
                            @empty
                                <p class="tutor-muted-message">Chưa cập nhật môn học và cấp độ giảng dạy.</p>
                            @endforelse
                        </div>
                    </section>

                    @if ($tutor->supports_offline)
                        <section class="tutor-detail-section" aria-labelledby="areas-heading">
                            <h2 id="areas-heading"><b>Khu vực nhận dạy</b></h2>
                            <div class="tutor-location-chips">
                                @forelse ($areas as $area)
                                    <span>{{ $area }}</span>
                                @empty
                                    <p class="tutor-muted-message">Chưa cập nhật khu vực nhận dạy.</p>
                                @endforelse
                            </div>
                        </section>
                    @endif

                    <section class="tutor-detail-section" aria-labelledby="availability-heading">
                        <h2 id="availability-heading"><b>Lịch có thể nhận dạy</b></h2>
                        @if ($availabilityGroups->isNotEmpty())
                            <div class="tutor-availability-list">
                                @foreach ($availabilityGroups as $day => $availabilities)
                                    <div class="tutor-availability-row">
                                        <strong>{{ $dayLabels[$day] ?? 'Ngày trong tuần' }}</strong>
                                        <div>
                                            @foreach ($availabilities as $availability)
                                                <span>
                                                    {{ substr((string) $availability->timeSlot->start_time, 0, 5) }}
                                                    –
                                                    {{ substr((string) $availability->timeSlot->end_time, 0, 5) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="tutor-muted-message">Gia sư chưa công khai lịch rảnh.</p>
                        @endif
                    </section>
                </div>

                <aside class="tutor-summary-card" aria-label="Học phí và gửi yêu cầu học">

                    @if ($hourlyRate !== null)
                        <div class="tutor-summary-price">
                            <strong>{{ $hourlyRate }}</strong>
                            <span>/ giờ</span>
                        </div>
                    @else
                        <p class="tutor-summary-price-missing">Chưa cập nhật học phí.</p>
                    @endif

                    <div class="tutor-summary-subjects">
                        @forelse ($subjects as $tutorSubject)
                            <div>
                                <h3>{{ $tutorSubject->subject->subject_name }}</h3>
                                <p>{{ $subjectLevelsBySubject->get($tutorSubject->subject_id)->join(' · ') ?: 'Chưa cập nhật cấp độ.' }}</p>
                            </div>
                        @empty
                            <p class="tutor-muted-message">Chưa cập nhật môn dạy.</p>
                        @endforelse
                    </div>

                    <div class="tutor-summary-modes">
                        @forelse ($modes as $mode)
                            <span><x-directory-icon name="check" /> {{ $mode }}</span>
                        @empty
                            <span>Chưa cập nhật hình thức dạy.</span>
                        @endforelse
                    </div>

                    @unless (auth()->user()?->is_admin)
                        @auth
                            <a class="button tutor-request-button" href="{{ route('requests.direct.create', $tutor) }}">Gửi yêu cầu học <x-directory-icon name="arrow" /></a>
                        @else
                            <a class="button tutor-request-button" href="{{ route('login') }}">Đăng nhập để gửi yêu cầu <x-directory-icon name="arrow" /></a>
                        @endauth
                    @endunless
                    <p class="tutor-approved-note"><x-directory-icon name="check" /> Hồ sơ đã được kiểm duyệt</p>
                </aside>
            </div>
        </div>
    </section>
</x-app-layout>
