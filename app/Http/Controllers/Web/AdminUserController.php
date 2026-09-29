<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Models\TutoringClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $this->searchValue($request->query('search'));
        $accountType = $this->allowedValue(
            $request->query('account_type'),
            ['user', 'admin']
        );
        $status = $this->allowedValue(
            $request->query('status'),
            ['active', 'inactive']
        );
        $tutorStatus = $this->allowedValue(
            $request->query('tutor_status'),
            ['none', 'pending', 'approved', 'rejected']
        );

        $statistics = [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'with_tutor_profile' => User::query()->whereHas('tutorProfile')->count(),
            'identity_verified' => User::query()
                ->whereHas(
                    'identityVerification',
                    fn (Builder $query): Builder => $query->where(
                        'status',
                        IdentityVerification::STATUS_VERIFIED
                    )
                )
                ->count(),
        ];

        $usersQuery = User::query()
            ->with([
                'tutorProfile:tutor_profile_id,user_id,approval_status,submitted_at',
                'identityVerification:verification_id,user_id,status',
            ])
            ->when(
                $search !== null,
                fn (Builder $query): Builder => $query->where(
                    function (Builder $identityQuery) use ($search): void {
                        $identityQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    }
                )
            )
            ->when(
                $accountType === 'admin',
                fn (Builder $query): Builder => $query->where('is_admin', true)
            )
            ->when(
                $accountType === 'user',
                fn (Builder $query): Builder => $query->where('is_admin', false)
            )
            ->when(
                $status === 'active',
                fn (Builder $query): Builder => $query->where('status', User::STATUS_ACTIVE)
            )
            ->when(
                $status === 'inactive',
                fn (Builder $query): Builder => $query->where('status', '!=', User::STATUS_ACTIVE)
            )
            ->when(
                $tutorStatus === 'none',
                fn (Builder $query): Builder => $query->whereDoesntHave('tutorProfile')
            )
            ->when(
                $tutorStatus !== null && $tutorStatus !== 'none',
                fn (Builder $query): Builder => $query->whereHas(
                    'tutorProfile',
                    fn (Builder $profileQuery): Builder => $profileQuery->where(
                        'approval_status',
                        strtoupper($tutorStatus)
                    )
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('user_id');

        $filters = array_filter([
            'search' => $search,
            'account_type' => $accountType,
            'status' => $status,
            'tutor_status' => $tutorStatus,
        ], fn ($value): bool => $value !== null && $value !== '');

        $users = $usersQuery
            ->paginate(10, [
                'user_id',
                'email',
                'full_name',
                'avatar_url',
                'phone',
                'is_admin',
                'status',
                'created_at',
            ])
            ->appends($filters);

        return view('admin.users.index', compact(
            'accountType',
            'filters',
            'search',
            'statistics',
            'status',
            'tutorStatus',
            'users'
        ));
    }

    public function show(User $user): View
    {
        $user->load([
            'tutorProfile' => fn ($query) => $query->withCount('applications'),
            'identityVerification',
        ])->loadCount('tutoringRequests');

        $relatedClassCount = TutoringClass::query()
            ->join(
                'contracts',
                'tutoring_classes.contract_id',
                '=',
                'contracts.contract_id'
            )
            ->join(
                'tutoring_requests',
                'contracts.request_id',
                '=',
                'tutoring_requests.request_id'
            )
            ->where(function ($query) use ($user): void {
                $query->where('tutoring_requests.user_id', $user->getKey());

                if ($user->tutorProfile !== null) {
                    $query->orWhere(
                        'contracts.tutor_profile_id',
                        $user->tutorProfile->getKey()
                    );
                }
            })
            ->distinct()
            ->count('tutoring_classes.class_id');

        return view('admin.users.show', compact('relatedClassCount', 'user'));
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        abort_if(
            (int) $request->user()->getAuthIdentifier() === (int) $user->getKey(),
            403,
            'Quản trị viên không thể vô hiệu hóa tài khoản của chính mình.'
        );

        $changed = DB::transaction(function () use ($user): bool {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUser->isActive()) {
                return false;
            }

            $lockedUser->status = User::STATUS_DISABLED;
            $lockedUser->save();

            return true;
        }, 3);

        return redirect()
            ->route('admin.users.show', $user)
            ->with(
                'success',
                $changed
                    ? 'Tài khoản đã bị vô hiệu hóa.'
                    : 'Tài khoản đã ở trạng thái vô hiệu hóa.'
            );
    }

    public function activate(User $user): RedirectResponse
    {
        $changed = DB::transaction(function () use ($user): bool {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->isActive()) {
                return false;
            }

            $lockedUser->status = User::STATUS_ACTIVE;
            $lockedUser->save();

            return true;
        }, 3);

        return redirect()
            ->route('admin.users.show', $user)
            ->with(
                'success',
                $changed
                    ? 'Tài khoản đã được kích hoạt lại.'
                    : 'Tài khoản đang hoạt động.'
            );
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedValue(mixed $value, array $allowed): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true)
            ? $normalized
            : null;
    }

    private function searchValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim(mb_substr($value, 0, 100));

        return $search !== '' ? $search : null;
    }
}
