<?php

namespace App\Http\Middleware;

use App\Models\TutorProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTutorRegistrationEditable
{
    public function handle(Request $request, Closure $next): Response
    {
        $tutorProfile = $request->user()?->tutorProfile()->first();

        if ($tutorProfile === null || $tutorProfile->canEditTutorRegistration()) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()
                ->route('tutor-registration.confirmation.edit')
                ->with('info', $this->lockedMessage($tutorProfile->approval_status));
        }

        abort(403, 'Hồ sơ gia sư hiện không được phép chỉnh sửa.');
    }

    private function lockedMessage(string $status): string
    {
        return match ($status) {
            TutorProfile::STATUS_PENDING => 'Hồ sơ của bạn đã được gửi xét duyệt và tạm thời không thể chỉnh sửa.',
            TutorProfile::STATUS_APPROVED => 'Hồ sơ của bạn đã được duyệt và không thể chỉnh sửa trong trình đăng ký.',
            default => 'Hồ sơ hiện có trạng thái không hợp lệ và không thể chỉnh sửa.',
        };
    }
}
