<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\IdentityVerificationRequirement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdentityVerified
{
    public function __construct(
        private readonly IdentityVerificationRequirement $requirement
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $action = IdentityVerificationRequirement::ACTION_CREATE_REQUEST
    ): Response {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $verification = $this->requirement->verificationFor($user);

        if ($this->requirement->isVerified($verification)) {
            return $next($request);
        }

        return redirect()
            ->route('identity-verification.show')
            ->with('error', $this->requirement->blockingMessage($verification, $action));
    }
}
