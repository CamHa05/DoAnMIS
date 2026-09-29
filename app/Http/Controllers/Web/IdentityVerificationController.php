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
use Throwable;

class IdentityVerificationController extends Controller
{
    public function show(Request $request): View
    {
        $verification = $request->user()
            ->identityVerification()
            ->first();

        return view('identity-verification.show', [
            'verification' => $verification,
        ]);
    }

    public function submit(
        Request $request,
        SystemNotificationService $notifications
    ): RedirectResponse
    {
        $user = $request->user();

        $verification = $user->identityVerification()->first();

        // Hồ sơ đang chờ duyệt thì không được gửi lại.
        if ($verification?->status === IdentityVerification::STATUS_PENDING) {
            return back()->with(
                'error',
                'Hồ sơ xác minh danh tính của bạn đang được kiểm duyệt.'
            );
        }

        // Đã xác minh thì không được sửa/gửi lại.
        if ($verification?->status === IdentityVerification::STATUS_VERIFIED) {
            return back()->with(
                'error',
                'Tài khoản của bạn đã được xác minh danh tính.'
            );
        }

        $validated = $request->validate(
            [
                'document_type' => ['required', 'in:CCCD'],
                'document_number' => [
                    'required',
                    'digits:12',
                ],
                'front_image' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
                'back_image' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
                'privacy_confirmation' => [
                    'accepted',
                ],
            ],
            [
                'document_type.required' => 'Vui lòng chọn loại giấy tờ.',
                'document_type.in' => 'Loại giấy tờ không hợp lệ.',

                'document_number.required' => 'Vui lòng nhập số căn cước công dân.',
                'document_number.digits' => 'Số căn cước công dân phải gồm đúng 12 chữ số.',

                'front_image.required' => 'Vui lòng tải ảnh mặt trước.',
                'front_image.image' => 'Ảnh mặt trước không hợp lệ.',
                'front_image.mimes' => 'Ảnh mặt trước phải là JPG, PNG hoặc WEBP.',
                'front_image.max' => 'Ảnh mặt trước không được vượt quá 5 MB.',

                'back_image.required' => 'Vui lòng tải ảnh mặt sau.',
                'back_image.image' => 'Ảnh mặt sau không hợp lệ.',
                'back_image.mimes' => 'Ảnh mặt sau phải là JPG, PNG hoặc WEBP.',
                'back_image.max' => 'Ảnh mặt sau không được vượt quá 5 MB.',

                'privacy_confirmation.accepted' =>
                'Bạn cần xác nhận trước khi gửi hồ sơ.',
            ]
        );

        $directory = 'identity-verifications/' . $user->user_id;

        $newFrontPath = null;
        $newBackPath = null;

        try {
            // Lưu private trên disk local, không đưa trực tiếp vào public/.
            $newFrontPath = $request
                ->file('front_image')
                ->store($directory, 'local');

            $newBackPath = $request
                ->file('back_image')
                ->store($directory, 'local');

            DB::transaction(function () use (
                $user,
                $verification,
                $validated,
                $newFrontPath,
                $newBackPath,
                $notifications
            ) {
                $oldFrontPath = $verification?->front_image_path;
                $oldBackPath = $verification?->back_image_path;

                $verification = IdentityVerification::updateOrCreate(
                    [
                        'user_id' => $user->user_id,
                    ],
                    [
                        'document_type' => $validated['document_type'],
                        'document_number' => $validated['document_number'],
                        'front_image_path' => $newFrontPath,
                        'back_image_path' => $newBackPath,
                        'status' => IdentityVerification::STATUS_PENDING,
                        'submitted_at' => now(),
                        'verified_at' => null,
                        'verified_by' => null,
                        'rejection_reason' => null,
                    ]
                );

                $notifications->identitySubmitted($verification);

                // Nếu đây là lần gửi lại sau REJECTED,
                // xóa ảnh cũ sau khi record mới đã lưu thành công.
                if (
                    $oldFrontPath &&
                    $oldFrontPath !== $newFrontPath
                ) {
                    Storage::disk('local')->delete($oldFrontPath);
                }

                if (
                    $oldBackPath &&
                    $oldBackPath !== $newBackPath
                ) {
                    Storage::disk('local')->delete($oldBackPath);
                }
            });
        } catch (Throwable $e) {
            if ($newFrontPath) {
                Storage::disk('local')->delete($newFrontPath);
            }

            if ($newBackPath) {
                Storage::disk('local')->delete($newBackPath);
            }

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Không thể gửi hồ sơ xác minh lúc này. Vui lòng thử lại.'
                );
        }

        return redirect()
            ->route('identity-verification.show')
            ->with(
                'success',
                'Hồ sơ xác minh danh tính đã được gửi và đang chờ kiểm duyệt.'
            );
    }
}
