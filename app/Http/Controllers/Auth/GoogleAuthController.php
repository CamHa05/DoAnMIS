<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoogleAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ((bool) $request->user()?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function redirect(Request $request)
    {
        $state = Str::random(40);

        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]);

        return redirect(
            'https://accounts.google.com/o/oauth2/v2/auth?' . $query
        );
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('login')
                ->with('error', 'Bạn đã hủy đăng nhập Google.');
        }

        $expectedState = $request->session()->pull('google_oauth_state');

        if (
            !$expectedState ||
            !$request->filled('state') ||
            !hash_equals(
                $expectedState,
                $request->string('state')->toString()
            )
        ) {
            return redirect()
                ->route('login')
                ->with('error', 'Phiên đăng nhập Google không hợp lệ.');
        }

        if (!$request->filled('code')) {
            return redirect()
                ->route('login')
                ->with('error', 'Google không trả về mã xác thực.');
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | 1. Đổi authorization code thành access token
            |--------------------------------------------------------------------------
            */

            $tokenResponse = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'code' => $request->string('code')->toString(),
                    'redirect_uri' => config('services.google.redirect'),
                    'grant_type' => 'authorization_code',
                ]);

            if ($tokenResponse->failed()) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Không thể xác thực với Google.');
            }

            $accessToken = $tokenResponse->json('access_token');

            if (!$accessToken) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Google không trả về access token.');
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Lấy thông tin người dùng Google
            |--------------------------------------------------------------------------
            */

            $profileResponse = Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(8)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            if ($profileResponse->failed()) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Không thể lấy thông tin tài khoản Google.');
            }

            $googleUser = $profileResponse->json();

            if (
                empty($googleUser['sub']) ||
                empty($googleUser['email']) ||
                empty($googleUser['email_verified'])
            ) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Tài khoản Google chưa được xác minh.');
            }

            $googleId = $googleUser['sub'];
            $email = $googleUser['email'];
            $googleFullName = $this->normalizedGoogleFullName(
                $googleUser['name'] ?? null
            );
            $googlePicture = $this->normalizedGooglePicture(
                $googleUser['picture'] ?? null
            );

            /*
            |--------------------------------------------------------------------------
            | 3. Tìm tài khoản GiaSu
            |--------------------------------------------------------------------------
            */

            $user = User::where('google_id', $googleId)->first();

            /*
             * Database của bạn hiện đã có dữ liệu mẫu.
             * Nếu email đã tồn tại nhưng chưa có google_id,
             * liên kết tài khoản Google với user đó.
             */
            if (!$user) {
                $user = User::where('email', $email)->first();
            }

            if ($user !== null && ! $user->isActive()) {
                return redirect()
                    ->route('login')
                    ->with('error', EnsureAccountIsActive::DISABLED_MESSAGE);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Nếu chưa có user thì tạo mới
            |--------------------------------------------------------------------------
            */

            if (!$user) {
                $user = new User();

                $user->email = $email;
                $user->full_name = $googleFullName
                    ?? Str::substr(trim($email), 0, 100);
                $user->is_admin = false;
                $user->status = User::STATUS_ACTIVE;
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Đồng bộ thông tin Google
            |--------------------------------------------------------------------------
            */

            $user->google_id = $googleId;

            if ($googleFullName !== null) {
                $user->full_name = $googleFullName;
            }

            if ($googlePicture !== null && Str::length($googlePicture) <= 500) {
                $user->avatar_url = $googlePicture;
            }

            $user->last_login = now();

            $user->save();

            /*
            |--------------------------------------------------------------------------
            | 6. Kiểm tra trạng thái account
            |--------------------------------------------------------------------------
            */

            if (! $user->isActive()) {
                return redirect()
                    ->route('login')
                    ->with('error', EnsureAccountIsActive::DISABLED_MESSAGE);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Login Laravel
            |--------------------------------------------------------------------------
            */

            Auth::login($user);

            $request->session()->regenerate();

            if ((bool) $user->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('home'));

        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Đăng nhập Google thất bại. Vui lòng thử lại.'
                );
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function normalizedGoogleFullName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name === '' ? null : Str::substr($name, 0, 100);
    }

    private function normalizedGooglePicture(mixed $picture): ?string
    {
        if (!is_string($picture)) {
            return null;
        }

        $picture = trim($picture);

        return $picture === '' ? null : $picture;
    }
}
