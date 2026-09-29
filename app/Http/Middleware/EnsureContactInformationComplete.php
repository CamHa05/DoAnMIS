<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContactInformationComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        if (filled($user->email) && filled($user->phone)) {
            return $next($request);
        }

        return redirect()
            ->route('profile.show')
            ->with('error', 'Vui lòng cập nhật đầy đủ thông tin liên hệ trước khi tiếp tục.')
            ->with('contact_profile_action', true);
    }
}