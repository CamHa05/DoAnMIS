<x-app-layout>
    <x-slot name="title">Chi tiết yêu cầu | GiaSu</x-slot>

    @php
        $subjectName = trim((string) $tutoringRequest->subjectLevel?->subject?->subject_name);
        $levelName = trim((string) $tutoringRequest->subjectLevel?->level_name);
        $title = collect([$subjectName, $levelName])->filter()->join(' · ') ?: 'Môn học chưa cập nhật';
        $requestType = strtoupper((string) $tutoringRequest->request_type);
        $isDirect = $requestType === 'DIRECT';
        $isPublic = $requestType === 'PUBLIC';
        $isOffline = strtoupper((string) $tutoringRequest->learning_mode) === 'OFFLINE';
        $addressDetail = trim((string) $tutoringRequest->address_detail);
        $areaLabel = collect([
            $tutoringRequest->ward?->ward_name,
            $tutoringRequest->ward?->province?->province_name,
        ])->filter(fn ($value) => filled($value))->unique()->join(', ');
    @endphp

    <section class="my-request-detail-page" aria-labelledby="my-request-detail-title">
        <div class="container my-request-detail-container">
            <a class="my-request-back-link" href="{{ route('my-requests.index') }}">
                <x-directory-icon name="arrow" />
                Yêu cầu của tôi
            </a>

            <header class="my-request-detail-header">
                <div class="my-request-detail-heading">
                    <div class="my-request-detail-badges">
                        <span class="my-request-type" data-type="{{ strtolower($requestType) }}">{{ $tutoringRequest->typeLabel() }}</span>
                        <span class="my-request-status" data-tone="{{ $tutoringRequest->statusTone() }}">{{ $tutoringRequest->statusLabel() }}</span>
                    </div>
                    <h1 id="my-request-detail-title">{{ $title }}</h1>
                </div>

                <span class="my-request-detail-id">Mã yêu cầu #{{ $tutoringRequest->request_id }}</span>
            </header>

            <div class="my-request-detail-layout">
                <section class="my-request-detail-panel" aria-labelledby="request-information-title">
                    <div class="my-request-detail-panel-heading">
                        <span><x-directory-icon name="request" /></span>
                        <div>
                            <h2 id="request-information-title">Nội dung yêu cầu</h2>
                            <p>Thông tin bạn đã cung cấp khi tạo yêu cầu.</p>
                        </div>
                    </div>

                    <dl class="my-request-detail-facts">
                        <div>
                            <dt>Môn học</dt>
                            <dd>{{ $subjectName ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt>Trình độ</dt>
                            <dd>{{ $levelName ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt>Hình thức</dt>
                            <dd>{{ $tutoringRequest->learningModeLabel() }}</dd>
                        </div>
                        <div>
                            <dt>Học phí dự kiến</dt>
                            <dd>
                                @if ($tutoringRequest->expected_fee !== null)
                                    {{ number_format((float) $tutoringRequest->expected_fee, 0, ',', '.') }}đ / giờ
                                @else
                                    Chưa cập nhật
                                @endif
                            </dd>
                        </div>
                        @if ($isOffline)
                            <div class="my-request-detail-fact--wide">
                                <dt>Địa điểm</dt>
                                <dd>
                                    @if ($addressDetail)
                                        <strong>{{ $addressDetail }}</strong>
                                    @endif
                                    <span>{{ $areaLabel ?: 'Chưa cập nhật khu vực' }}</span>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt>Ngày tạo</dt>
                            <dd>{{ $tutoringRequest->created_at?->format('d/m/Y, H:i') ?: 'Chưa cập nhật' }}</dd>
                        </div>
                        <div>
                            <dt>Hạn phản hồi</dt>
                            <dd>{{ $tutoringRequest->expires_at?->format('d/m/Y, H:i') ?: 'Không có thời hạn' }}</dd>
                        </div>
                    </dl>

                    <div class="my-request-detail-description">
                        <h3>Mô tả</h3>
                        <p>{{ filled($tutoringRequest->description) ? $tutoringRequest->description : 'Bạn chưa thêm mô tả cho yêu cầu này.' }}</p>
                    </div>
                </section>

                <aside class="my-request-schedule-panel" aria-labelledby="desired-schedule-title">
                    <div class="my-request-schedule-heading">
                        <x-directory-icon name="calendar" />
                        <h2 id="desired-schedule-title">Lịch mong muốn</h2>
                    </div>

                    @if ($tutoringRequest->schedules->isNotEmpty())
                        <ul>
                            @foreach ($tutoringRequest->schedules as $schedule)
                                @php($timeSlot = $schedule->timeSlot)
                                @if ($timeSlot)
                                    <li>
                                        <span>{{ $schedule->dayLabel() }}</span>
                                        <strong>{{ substr((string) $timeSlot->start_time, 0, 5) }}–{{ substr((string) $timeSlot->end_time, 0, 5) }}</strong>
                                        @if (filled($timeSlot->slot_name))
                                            <small>{{ $timeSlot->slot_name }}</small>
                                        @endif
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <p class="my-request-schedule-empty">Chưa có lịch mong muốn.</p>
                    @endif
                </aside>
            </div>

            @if ($isDirect)
                @php($targetTutor = $tutoringRequest->targetTutor)
                @php($targetUser = $targetTutor?->user)
                <section class="my-request-related-section" aria-labelledby="direct-tutor-title">
                    <header>
                        <div>
                            <p class="eyebrow">Yêu cầu trực tiếp</p>
                            <h2 id="direct-tutor-title">Gia sư nhận yêu cầu</h2>
                        </div>
                    </header>

                    <article class="my-request-tutor-card">
                        @if ($targetUser)
                            <x-tutor-avatar :user="$targetUser" />
                            <div>
                                <h3>{{ $targetUser->full_name }}</h3>
                                <p>{{ filled($targetTutor?->headline) ? $targetTutor->headline : 'Gia sư trên GiaSu' }}</p>
                            </div>
                        @else
                            <span class="my-request-related-placeholder"><x-directory-icon name="user" /></span>
                            <div>
                                <h3>Gia sư không còn khả dụng</h3>
                                <p>Thông tin hồ sơ nhận yêu cầu hiện không thể hiển thị.</p>
                            </div>
                        @endif
                    </article>
                    @if ($targetTutor && $targetUser)
                        <a class="my-request-detail-link" href="{{ route('tutors.show', $targetTutor) }}">Xem hồ sơ gia sư <x-directory-icon name="arrow" /></a>
                    @endif
                    @if ($tutoringRequest->status === \App\Models\TutoringRequest::STATUS_MATCHED && $tutoringRequest->contract)
                        <a class="my-request-detail-link" href="{{ route('contracts.show', $tutoringRequest->contract) }}">Xem &amp; xác nhận thỏa thuận <x-directory-icon name="arrow" /></a>
                    @endif
                </section>
            @elseif ($isPublic)
                <section class="my-request-related-section" aria-labelledby="applications-title">
                    <header>
                        <div>
                            <p class="eyebrow">Yêu cầu công khai</p>
                            <h2 id="applications-title">Gia sư ứng tuyển</h2>
                        </div>
                        <span>{{ $tutoringRequest->applications_count }} ứng tuyển</span>
                    </header>

                    <a
                        class="my-request-applications-cta"
                        href="{{ route('my-requests.applications.index', $tutoringRequest) }}"
                    >
                        <span class="my-request-applications-cta-icon"><x-directory-icon name="users" /></span>
                        <span>
                            <strong>Xem gia sư ứng tuyển ({{ $tutoringRequest->applications_count }})</strong>
                            <small>Xem danh sách, học phí đề xuất và hồ sơ chi tiết của từng gia sư.</small>
                        </span>
                        <x-directory-icon name="arrow" />
                    </a>
                </section>
            @endif
        </div>
    </section>
</x-app-layout>
