<x-tutor-account-layout title="Yêu cầu học của tôi | GiaSu">

    <section class="my-requests-page" aria-labelledby="my-requests-title">
        <div class="container my-requests-container">
            <header class="my-requests-page-header">
                <div>
                    <h1 id="my-requests-title">Yêu cầu của tôi</h1>
                    <p>Theo dõi các yêu cầu tìm gia sư và yêu cầu đã gửi của bạn.</p>
                </div>

                @if ($myRequests->total() > 0)
                    <span class="my-requests-total"><strong>{{ $myRequests->total() }}</strong> yêu cầu</span>
                @endif
            </header>

            @if (session('success'))
                <div class="my-requests-alert" role="status">
                    <x-directory-icon name="check" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <nav class="my-request-tabs" aria-label="Lọc yêu cầu theo trạng thái">
                <a href="{{ route('my-requests.index') }}" @class(['is-active' => ! $statusFilter]) @if (! $statusFilter) aria-current="page" @endif>
                    Tất cả
                </a>
                <a href="{{ route('my-requests.index', ['status' => 'active']) }}" @class(['is-active' => $statusFilter === 'active']) @if ($statusFilter === 'active') aria-current="page" @endif>
                    Đang xử lý
                </a>
                <a href="{{ route('my-requests.index', ['status' => 'matched']) }}" @class(['is-active' => $statusFilter === 'matched']) @if ($statusFilter === 'matched') aria-current="page" @endif>
                    Đã ghép
                </a>
                <a href="{{ route('my-requests.index', ['status' => 'expired']) }}" @class(['is-active' => $statusFilter === 'expired']) @if ($statusFilter === 'expired') aria-current="page" @endif>
                    Đã hết hạn
                </a>
            </nav>

            @if ($myRequests->isNotEmpty())
                <div class="my-request-list">
                    @foreach ($myRequests as $myRequest)
                        <x-my-request-card :request="$myRequest" />
                    @endforeach
                </div>

                @if ($myRequests->hasPages())
                    <nav class="request-pagination my-requests-pagination" aria-label="Phân trang yêu cầu của tôi">
                        {{ $myRequests->links() }}
                    </nav>
                @endif
            @else
                <div class="my-requests-empty-state">
                    @if ($hasAnyRequests && $statusFilter)
                        <span class="my-requests-empty-icon"><x-directory-icon name="filter" /></span>
                        <h2>Không có yêu cầu ở trạng thái này</h2>
                        <p>Hãy chọn một bộ lọc khác để xem các yêu cầu học của bạn.</p>
                        <a class="button my-requests-empty-cta" href="{{ route('my-requests.index') }}">Xem tất cả yêu cầu</a>
                    @else
                        <span class="my-requests-empty-icon"><x-directory-icon name="request" /></span>
                        <h2>Bạn chưa có yêu cầu học nào</h2>
                        <p>{{ $canCreateRequest
                            ? 'Bạn có thể đăng yêu cầu để tìm gia sư phù hợp hoặc gửi yêu cầu trực tiếp cho một gia sư.'
                            : $createRequestVerificationMessage }}</p>

                        @unless (auth()->user()?->is_admin)
                            <a
                                class="button my-requests-empty-cta"
                                href="{{ $canCreateRequest ? route('requests.create') : route('identity-verification.show') }}"
                            >{{ $canCreateRequest ? 'Tạo yêu cầu học' : 'Xác minh danh tính để tạo yêu cầu' }}</a>
                        @endunless
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-tutor-account-layout>
