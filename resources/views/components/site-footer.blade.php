<footer class="site-footer" id="become-tutor">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand brand-footer" href="{{ route('home') }}">
                <span class="brand-mark">G</span>
                <span>GiaSu</span>
            </a>
            <p>Một người phù hợp có thể<br>thay đổi cách bạn học.</p>
        </div>

        <div>
            <p class="footer-label">Khám phá</p>
            <a href="{{ route('tutors.index') }}">Tìm gia sư</a>
            <a href="{{ route('requests.index') }}">Lớp đang tìm gia sư</a>
        </div>

        @unless (auth()->user()?->is_admin)
            <div>
                <p class="footer-label">Dành cho gia sư</p>
                <a href="#become-tutor">Trở thành gia sư</a>
                <a href="#login">Đăng nhập</a>
            </div>
        @endunless

        <div>
            <p class="footer-label">Liên hệ</p>
            <a href="mailto:hello@giasu.vn">hello@giasu.vn</a>
            <span class="footer-muted">Hà Nội · Việt Nam</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© {{ date('Y') }} GiaSu</span>
        <span>Kết nối để học tốt hơn.</span>
    </div>
</footer>
