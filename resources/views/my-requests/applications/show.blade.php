<x-app-layout>
    @php
        $profile = $tutorApplication->tutorProfile;
        $user = $profile?->user;
        $subjects = $profile?->tutorSubjects
            ?->map(fn ($tutorSubject) => trim((string) $tutorSubject->subject?->subject_name))
            ->filter()->unique()->implode(', ');
        $subjectLevels = $profile?->tutorSubjects
            ?->flatMap(fn ($tutorSubject) => $tutorSubject->tutorSubjectLevels)
            ->map(fn ($level) => trim((string) $level->subjectLevel?->level_name))
            ->filter()->unique()->implode(', ');
        $agreesWithExpectedFee = $tutorApplication->proposed_fee === null;
        $expectedFeeLabel = $tutoringRequest->expected_fee !== null
            ? number_format((float) $tutoringRequest->expected_fee, 0, ',', '.').' VNĐ'.$feeUnit
            : 'Chưa cập nhật';
        $feeLabel = $agreesWithExpectedFee
            ? $expectedFeeLabel
            : number_format((float) $tutorApplication->proposed_fee, 0, ',', '.').' VNĐ'.$feeUnit;
        $teachingModes = collect([
            $profile?->supports_online ? 'Trực tuyến' : null,
            $profile?->supports_offline ? 'Tại nhà' : null,
        ])->filter()->implode(' · ');
        $subjectName = trim((string) $tutoringRequest->subjectLevel?->subject?->subject_name);
        $levelName = trim((string) $tutoringRequest->subjectLevel?->level_name);
        $canSelectByWorkflow = strtoupper((string) $tutoringRequest->status) === \App\Models\TutoringRequest::STATUS_OPEN
            && strtoupper((string) $tutorApplication->status) === \App\Models\TutorApplication::STATUS_PENDING;
        $canSelect = $canSelectByWorkflow && $user?->isActive() === true;
    @endphp

    <x-slot name="title">Hồ sơ ứng viên | GiaSu</x-slot>

    <section class="application-profile-page" aria-labelledby="application-profile-title">
        <div class="container application-profile-container">
            @if (session('error'))
                <div class="request-detail-flash request-detail-flash--error" role="alert">
                    <x-directory-icon name="x-circle" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <a class="application-directory-back" href="{{ route('my-requests.applications.index', $tutoringRequest) }}">
                <x-directory-icon name="arrow-left" />
                Quay lại danh sách ứng viên
            </a>

            <div class="application-profile-layout">
                <main class="application-profile-main">
                    <header class="application-profile-header">
                        <div id="application-avatar-{{ $tutorApplication->getKey() }}" class="application-select-avatar-source">
                            <x-tutor-avatar :user="$user" size="large" />
                        </div>
                        <div>
                            <div class="application-profile-name-row">
                                <h1 id="application-profile-title">{{ $user?->full_name ?: 'Gia sư không còn khả dụng' }}</h1>
                                @if ($profile?->approval_status === \App\Models\TutorProfile::STATUS_APPROVED)
                                    <span class="application-profile-approved"><x-directory-icon name="verified" />Hồ sơ đã duyệt</span>
                                @endif
                            </div>
                            <p>{{ filled($profile?->headline) ? $profile->headline : 'Gia sư trên GiaSu' }}</p>
                            <span>{{ $tutoringRequest->learningModeLabel() }}</span>
                        </div>
                    </header>

                    <section class="application-profile-card" aria-labelledby="application-introduction-title">
                        <h2 id="application-introduction-title">Giới thiệu</h2>
                        <p>{{ filled($profile?->bio) ? $profile->bio : 'Gia sư chưa cập nhật phần giới thiệu.' }}</p>
                    </section>

                    <section class="application-profile-card application-profile-two-columns">
                        <div><h2>Học vấn</h2><p>{{ filled($profile?->education_summary) ? $profile->education_summary : 'Chưa cập nhật.' }}</p></div>
                        <div><h2>Kinh nghiệm</h2><p>{{ filled($profile?->teaching_experience) ? $profile->teaching_experience : 'Chưa cập nhật.' }}</p></div>
                    </section>

                    <section class="application-profile-card" aria-labelledby="application-expertise-title">
                        <h2 id="application-expertise-title">Chuyên môn</h2>
                        <dl class="application-profile-facts">
                            <div><dt>Môn giảng dạy</dt><dd>{{ $subjects ?: 'Chưa cập nhật.' }}</dd></div>
                            <div><dt>Trình độ có thể dạy</dt><dd>{{ $subjectLevels ?: 'Chưa cập nhật.' }}</dd></div>
                            <div><dt>Hình thức dạy</dt><dd>{{ collect([$profile?->supports_online ? 'Trực tuyến' : null, $profile?->supports_offline ? 'Tại nhà' : null])->filter()->implode(' · ') ?: 'Chưa cập nhật.' }}</dd></div>
                        </dl>
                    </section>

                    @if ($profile?->teachingAreas?->isNotEmpty())
                        <section class="application-profile-card" aria-labelledby="application-areas-title">
                            <h2 id="application-areas-title">Khu vực dạy</h2>
                            <p>{{ $profile->teachingAreas->map(fn ($area) => collect([$area->ward?->ward_name, $area->ward?->province?->province_name])->filter()->implode(', '))->filter()->unique()->implode(' · ') }}</p>
                        </section>
                    @endif

                    <section class="application-profile-card" aria-labelledby="application-availability-title">
                        <h2 id="application-availability-title">Lịch rảnh</h2>
                        @if ($profile?->availabilities?->isNotEmpty())
                            <ul class="application-profile-availability">
                                @foreach ($profile->availabilities as $availability)
                                    @if ($availability->timeSlot)
                                        <li>{{ $availability->dayLabel() }} · {{ substr((string) $availability->timeSlot->start_time, 0, 5) }}–{{ substr((string) $availability->timeSlot->end_time, 0, 5) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @else
                            <p>Gia sư chưa cập nhật lịch rảnh.</p>
                        @endif
                    </section>
                </main>

                <aside class="application-profile-sidebar" aria-label="Thông tin ứng tuyển">
                    <section class="application-sidebar-card application-profile-application-card" aria-labelledby="application-information-title">
                        <h2 id="application-information-title">Thông tin ứng tuyển</h2>
                        <span class="application-owner-status" data-tone="{{ $tutorApplication->ownerStatusTone() }}">{{ $tutorApplication->ownerStatusLabel() }}</span>
                        <dl>
                            <div><dt>Ngày ứng tuyển</dt><dd>{{ $tutorApplication->applied_at?->format('d/m/Y, H:i') ?: 'Chưa cập nhật' }}</dd></div>
                            <div><dt>{{ $agreesWithExpectedFee ? 'Đồng ý mức học phí dự kiến' : 'Học phí đề xuất' }}</dt><dd>{{ $feeLabel }}</dd></div>
                        </dl>
                        <div class="application-profile-full-message"><h3>Lời nhắn</h3><p>{{ filled($tutorApplication->message) ? $tutorApplication->message : 'Gia sư không để lại lời nhắn.' }}</p></div>
                        @if ($canSelect)
                            <button
                                type="button"
                                data-select-tutor-open
                                data-select-tutor-action="{{ route('my-requests.applications.select', [$tutoringRequest, $tutorApplication]) }}"
                                data-tutor-avatar-source="application-avatar-{{ $tutorApplication->getKey() }}"
                                data-tutor-name="{{ $user?->full_name ?: 'Gia sư không còn khả dụng' }}"
                                data-tutor-headline="{{ filled($profile?->headline) ? $profile->headline : 'Gia sư trên GiaSu' }}"
                                data-tutor-approved="{{ $profile?->approval_status === \App\Models\TutorProfile::STATUS_APPROVED ? 'true' : 'false' }}"
                                data-tutor-subjects="{{ $subjects ?: 'Chưa cập nhật' }}"
                                data-tutor-modes="{{ $teachingModes ?: 'Chưa cập nhật' }}"
                                data-expected-fee="{{ $expectedFeeLabel }}"
                                data-has-proposed-fee="{{ $tutorApplication->proposed_fee !== null ? 'true' : 'false' }}"
                                data-proposed-fee="{{ $tutorApplication->proposed_fee !== null ? $feeLabel : '' }}"
                                data-application-message="{{ filled($tutorApplication->message) ? $tutorApplication->message : 'Gia sư không để lại lời nhắn.' }}"
                                data-application-applied="Ứng tuyển {{ $tutorApplication->applied_at?->locale('vi')->diffForHumans() ?: 'chưa cập nhật' }}"
                            >Chọn gia sư</button>
                        @elseif ($canSelectByWorkflow && $user?->isDisabled())
                            <button
                                class="application-selection-unavailable"
                                type="button"
                                disabled
                                data-select-tutor-disabled
                                title="Tài khoản gia sư đã bị vô hiệu hóa"
                            >Không thể chọn</button>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </section>

    @include('my-requests.applications.partials.select-tutor-modal', [
        'subjectName' => $subjectName,
        'levelName' => $levelName,
    ])
</x-app-layout>
