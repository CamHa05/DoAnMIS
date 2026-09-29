<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminIdentityVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = IdentityVerification::query()
            ->with('user')
            ->latest('submitted_at');

        if (in_array($status, [
            IdentityVerification::STATUS_PENDING,
            IdentityVerification::STATUS_VERIFIED,
            IdentityVerification::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        $verifications = $query->paginate(15)->withQueryString();

        return view('admin.identity-verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
        ]);
    }

    public function show(IdentityVerification $identityVerification): View
    {
        $identityVerification->load([
            'user',
            'verifiedBy',
        ]);

        return view('admin.identity-verifications.show', [
            'verification' => $identityVerification,
        ]);
    }

    public function approve(
        Request $request,
        IdentityVerification $identityVerification,
        SystemNotificationService $notifications
    ): RedirectResponse {
        if ($identityVerification->status !== IdentityVerification::STATUS_PENDING) {
            return back()->with(
                'error',
                'Chỉ hồ sơ đang chờ kiểm duyệt mới có thể được xác minh.'
            );
        }

        DB::transaction(function () use ($request, $identityVerification, $notifications) {
            $verification = IdentityVerification::query()
                ->whereKey($identityVerification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($verification->status !== IdentityVerification::STATUS_PENDING) {
                return;
            }

            $verification->update([
                'status' => IdentityVerification::STATUS_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $request->user()->user_id,
                'rejection_reason' => null,
            ]);

            $notifications->identityVerified($verification);
        });

        return redirect()
            ->route('admin.identity-verifications.show', $identityVerification)
            ->with('success', 'Đã xác minh danh tính người dùng.');
    }

    public function reject(
        Request $request,
        IdentityVerification $identityVerification,
        SystemNotificationService $notifications
    ): RedirectResponse {
        $validated = $request->validate(
            [
                'rejection_reason' => [
                    'required',
                    'string',
                    'max:1000',
                ],
            ],
            [
                'rejection_reason.required' =>
                    'Vui lòng nhập lý do từ chối.',
                'rejection_reason.max' =>
                    'Lý do từ chối không được vượt quá 1000 ký tự.',
            ]
        );

        if ($identityVerification->status !== IdentityVerification::STATUS_PENDING) {
            return back()->with(
                'error',
                'Chỉ hồ sơ đang chờ kiểm duyệt mới có thể bị từ chối.'
            );
        }

        DB::transaction(function () use (
            $request,
            $identityVerification,
            $validated,
            $notifications
        ) {
            $verification = IdentityVerification::query()
                ->whereKey($identityVerification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($verification->status !== IdentityVerification::STATUS_PENDING) {
                return;
            }

            $verification->update([
                'status' => IdentityVerification::STATUS_REJECTED,
                'verified_at' => null,
                'verified_by' => $request->user()->user_id,
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $notifications->identityRejected($verification);
        });

        return redirect()
            ->route('admin.identity-verifications.show', $identityVerification)
            ->with('success', 'Đã từ chối hồ sơ xác minh danh tính.');
    }

    public function document(
        IdentityVerification $identityVerification,
        string $side
    ) {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = $side === 'front'
            ? $identityVerification->front_image_path
            : $identityVerification->back_image_path;

        abort_unless(
            $path && Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->response($path);
    }
}
