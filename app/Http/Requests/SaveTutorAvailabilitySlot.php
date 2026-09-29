<?php

namespace App\Http\Requests;

use App\Models\TutorProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTutorAvailabilitySlot extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tutorProfile()
            ->where('approval_status', TutorProfile::STATUS_APPROVED)
            ->exists() === true;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'time_slot_id' => [
                'required',
                'integer',
                Rule::exists('time_slots', 'time_slot_id')
                    ->where(fn ($query) => $query->where('status', 'ACTIVE')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'day_of_week.required' => 'Vui lòng chọn ngày trong tuần.',
            'day_of_week.between' => 'Ngày trong tuần không hợp lệ.',
            'time_slot_id.required' => 'Vui lòng chọn khung giờ.',
            'time_slot_id.exists' => 'Khung giờ không tồn tại hoặc đã ngừng hoạt động.',
        ];
    }
}
