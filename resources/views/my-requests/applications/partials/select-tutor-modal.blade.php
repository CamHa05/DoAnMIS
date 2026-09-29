<dialog
    class="select-tutor-dialog"
    aria-labelledby="select-tutor-dialog-title"
    aria-describedby="select-tutor-dialog-description"
    data-select-tutor-dialog
>
    <div class="select-tutor-dialog__surface">
        <header class="select-tutor-dialog__header">
            <span class="select-tutor-dialog__hero-icon"><x-directory-icon name="user-round" /></span>
            <div>
                <h2 id="select-tutor-dialog-title">Chọn gia sư này?</h2>
                <p id="select-tutor-dialog-description">Xác nhận gia sư bạn muốn ghép với yêu cầu học.</p>
            </div>
            <button class="select-tutor-dialog__close" type="button" aria-label="Đóng hộp thoại" data-select-tutor-close>
                <x-directory-icon name="x-circle" />
            </button>
        </header>

        <div class="select-tutor-dialog__body">
            <section class="select-tutor-dialog__section" aria-labelledby="select-tutor-information-title">
                <h3 id="select-tutor-information-title"><x-directory-icon name="user" />Thông tin gia sư</h3>
                <div class="select-tutor-dialog__tutor">
                    <div class="select-tutor-dialog__avatar" data-select-tutor-avatar></div>
                    <div class="select-tutor-dialog__identity">
                        <div class="select-tutor-dialog__name-row">
                            <strong data-select-tutor-name></strong>
                            <span class="application-profile-approved" data-select-tutor-approved hidden>
                                <x-directory-icon name="verified" />Hồ sơ đã duyệt
                            </span>
                        </div>
                        <p data-select-tutor-headline></p>
                        <dl class="select-tutor-dialog__tutor-facts">
                            <div><dt><x-directory-icon name="book-open" /><span>Chuyên môn</span></dt><dd data-select-tutor-subjects></dd></div>
                            <div><dt><x-directory-icon name="mode" /><span>Hình thức dạy</span></dt><dd data-select-tutor-modes></dd></div>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="select-tutor-dialog__section" aria-labelledby="select-application-information-title">
                <h3 id="select-application-information-title"><x-directory-icon name="support" />Thông tin ứng tuyển</h3>
                <div class="select-tutor-dialog__application">
                    <div class="select-tutor-dialog__fee">
                        <span class="select-tutor-dialog__fact-icon"><x-directory-icon name="wallet" /></span>
                        <div data-select-tutor-fee-agreement>
                            <span>Đồng ý mức học phí dự kiến</span>
                            <strong data-select-tutor-expected-fee></strong>
                        </div>
                        <div class="select-tutor-dialog__fee-comparison" data-select-tutor-fee-comparison hidden>
                            <p><span>Học phí dự kiến của yêu cầu</span><strong data-select-tutor-comparison-expected></strong></p>
                            <p><span>Học phí gia sư đề xuất</span><strong data-select-tutor-proposed-fee></strong></p>
                        </div>
                    </div>
                    <div class="select-tutor-dialog__message">
                        <span class="select-tutor-dialog__fact-icon"><x-directory-icon name="support" /></span>
                        <div>
                            <span>Lời nhắn</span>
                            <blockquote data-select-tutor-message></blockquote>
                            <time data-select-tutor-applied-at></time>
                        </div>
                    </div>
                </div>
            </section>

            <section class="select-tutor-dialog__request" aria-labelledby="select-request-summary-title">
                <span><x-directory-icon name="book-open" /></span>
                <div>
                    <h3 id="select-request-summary-title">Tóm tắt yêu cầu học</h3>
                    <p>{{ $subjectName ?: 'Chưa cập nhật' }} <i>·</i> {{ $levelName ?: 'Chưa cập nhật' }} <i>·</i> {{ $tutoringRequest->learningModeLabel() }}</p>
                </div>
            </section>

            <aside class="select-tutor-dialog__notice" aria-label="Lưu ý sau khi xác nhận">
                <x-directory-icon name="info" />
                <div>
                    <strong>Sau khi xác nhận, yêu cầu học sẽ được ghép với gia sư này.</strong>
                    <p>Các hồ sơ ứng tuyển còn lại sẽ không còn được lựa chọn cho yêu cầu này.</p>
                    <p>Hệ thống sẽ tạo hợp đồng để hai bên tiếp tục xác nhận.</p>
                </div>
            </aside>
        </div>

        <footer class="select-tutor-dialog__footer">
            <button type="button" class="select-tutor-dialog__cancel" data-select-tutor-close data-select-tutor-initial>Hủy</button>
            <form method="POST" data-select-tutor-form>
                @csrf
                @method('PATCH')
                <button type="submit" class="select-tutor-dialog__submit" data-select-tutor-submit>
                    <x-directory-icon name="user-round" />
                    <span data-select-tutor-submit-label>Xác nhận chọn</span>
                </button>
            </form>
        </footer>
    </div>
</dialog>
