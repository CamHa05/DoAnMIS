<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTutorRegistration extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Vui lòng xác nhận thông tin hồ sơ trước khi gửi xét duyệt.',
            'confirmation.accepted' => 'Vui lòng xác nhận thông tin hồ sơ trước khi gửi xét duyệt.',
        ];
    }
}
