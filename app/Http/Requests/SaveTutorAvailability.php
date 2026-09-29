<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTutorAvailability extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('availability')) {
            $this->merge(['availability' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'availability' => ['present', 'array:1,2,3,4,5,6,7'],
        ];

        foreach (range(1, 7) as $dayOfWeek) {
            $rules["availability.$dayOfWeek"] = ['sometimes', 'array', 'min:1'];
            $rules["availability.$dayOfWeek.*"] = [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('time_slots', 'time_slot_id')
                    ->where(fn ($query) => $query->where('status', 'ACTIVE')),
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function availabilities(): array
    {
        $availabilities = [];

        foreach ($this->validated('availability', []) as $dayOfWeek => $timeSlotIds) {
            $availabilities[(int) $dayOfWeek] = collect($timeSlotIds)
                ->map(fn ($timeSlotId) => (int) $timeSlotId)
                ->unique()
                ->values()
                ->all();
        }

        return $availabilities;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'availability.present' => 'Dữ liệu lịch rảnh không hợp lệ.',
            'availability.array' => 'Dữ liệu lịch rảnh hoặc ngày trong tuần không hợp lệ.',
            'availability.*.array' => 'Danh sách khung giờ của ngày đã chọn không hợp lệ.',
            'availability.*.min' => 'Ngày đã chọn phải có ít nhất một khung giờ.',
            'availability.*.*.required' => 'Khung giờ đã chọn không hợp lệ.',
            'availability.*.*.integer' => 'Khung giờ đã chọn không hợp lệ.',
            'availability.*.*.distinct' => 'Khung giờ trong cùng một ngày không được trùng lặp.',
            'availability.*.*.exists' => 'Một hoặc nhiều khung giờ đã chọn không tồn tại hoặc không còn hoạt động.',
        ];
    }
}
