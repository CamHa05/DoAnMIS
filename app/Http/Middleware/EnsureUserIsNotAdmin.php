<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            (bool) $request->user()?->is_admin,
            Response::HTTP_FORBIDDEN,
            'Tài khoản quản trị không được tham gia nghiệp vụ học/dạy.'
        );

        return $next($request);
    }
}
