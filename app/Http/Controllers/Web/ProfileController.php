<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
            'verification' => $request->user()->identityVerification()->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'full_name' => ['required', 'string', 'max:100'],
                'phone' => ['nullable', 'string', 'max:20'],
            ],
            [
                'full_name.required' => 'Vui lòng nhập họ và tên.',
                'full_name.string' => 'Họ và tên phải là chuỗi ký tự hợp lệ.',
                'full_name.max' => 'Họ và tên không được vượt quá 100 ký tự.',
                'phone.string' => 'Số điện thoại phải là chuỗi ký tự hợp lệ.',
                'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            ],
        );

        $request->user()->update([
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return redirect()
            ->route('profile.show')
            ->with('success', 'Cập nhật hồ sơ thành công.');
    }
}
