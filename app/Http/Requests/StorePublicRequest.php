<?php

namespace App\Http\Requests;

use App\Models\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'subject_id',
            'subject_level_id',
            'learning_mode',
            'preferred_tutor_gender',
            'province_id',
            'ward_id',
            'address_detail',
            'expected_fee',
            'fee_type',
            'description',
            'expires_at',
        ] as $field) {
            if (is_array($this->input($field))) {
                $normalized[$field] = null;
            }
        }

        if (is_string($this->input('learning_mode'))) {
            $normalized['learning_mode'] = strtoupper(trim($this->input('learning_mode')));
        }

        if (is_string($this->input('preferred_tutor_gender'))) {
            $normalized['preferred_tutor_gender'] = strtoupper(trim($this->input('preferred_tutor_gender')));
        }

        if (is_string($this->input('fee_type'))) {
            $normalized['fee_type'] = strtoupper(trim($this->input('fee_type')));
        }

        if (is_string($this->input('address_detail'))) {
            $normalized['address_detail'] = trim($this->input('address_detail'));
        }

        if (is_string($this->input('description'))) {
            $normalized['description'] = trim($this->input('description'));
        }

        $scheduleDays = $this->input('schedule_day');
        $scheduleTimeSlotIds = $this->input('schedule_time_slot');

        if (is_array($scheduleDays) && is_array($scheduleTimeSlotIds)) {
            $scheduleDays = array_values($scheduleDays);
            $scheduleTimeSlotIds = array_values($scheduleTimeSlotIds);
            $scheduleCount = max(count($scheduleDays), count($scheduleTimeSlotIds));
            $normalized['schedules'] = [];

            for ($index = 0; $index < $scheduleCount; $index++) {
                $normalized['schedules'][] = [
                    'day_of_week' => $scheduleDays[$index] ?? null,
                    'time_slot_id' => $scheduleTimeSlotIds[$index] ?? null,
                ];
            }
        } else {
            $normalized['schedules'] = null;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subjectId = is_scalar($this->input('subject_id'))
            ? (int) $this->input('subject_id')
            : 0;
        $provinceId = is_scalar($this->input('province_id'))
            ? (int) $this->input('province_id')
            : 0;

        return [
            'subject_id' => ['required', 'integer'],
            'subject_level_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('subject_levels', 'subject_level_id')->where(
                    fn ($query) => $query
                        ->where('subject_id', $subjectId)
                        ->where('status', 'ACTIVE')
                        ->whereExists(
                            fn ($subjectQuery) => $subjectQuery
                                ->selectRaw('1')
                                ->from('subjects')
                                ->whereColumn('subjects.subject_id', 'subject_levels.subject_id')
                                ->where('subjects.status', 'ACTIVE')
                        )
                ),
            ],
            'learning_mode' => ['required', Rule::in(['ONLINE', 'OFFLINE'])],
            'preferred_tutor_gender' => ['required', Rule::in(['ANY', 'MALE', 'FEMALE'])],
            'province_id' => [
                'exclude_unless:learning_mode,OFFLINE',
                'required',
                'integer',
            ],
            'ward_id' => [
                'exclude_unless:learning_mode,OFFLINE',
                'bail',
                'required',
                'integer',
                Rule::exists('wards', 'ward_id')->where(
                    fn ($query) => $query->where('province_id', $provinceId)
                ),
            ],
            'address_detail' => [
                'exclude_unless:learning_mode,OFFLINE',
                'required',
                'string',
                'max:255',
            ],
            'expected_fee' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'fee_type' => ['required', Rule::in(['HOURLY', 'MONTHLY'])],
            'description' => ['required', 'string', 'max:3000'],
            'schedules' => ['required', 'array', 'min:1', 'max:20'],
            'schedules.*' => ['array:day_of_week,time_slot_id'],
            'schedules.*.day_of_week' => ['bail', 'required', 'integer', Rule::in(range(1, 7))],
            'schedules.*.time_slot_id' => ['bail', 'required', 'integer'],
            'expires_at' => [
                'required',
                'date',
                'after:now',
                'before_or_equal:'.now()->addDays(7)->toDateTimeString(),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $schedules = $this->input('schedules');

            if (! is_array($schedules) || $validator->errors()->has('schedules')) {
                return;
            }

            foreach ($schedules as $index => $schedule) {
                if (
                    ! is_array($schedule)
                    || $validator->errors()->has("schedules.{$index}.day_of_week")
                    || $validator->errors()->has("schedules.{$index}.time_slot_id")
                ) {
                    return;
                }
            }

            $pairKeys = array_map(
                fn (array $schedule): string => "{$schedule['day_of_week']}:{$schedule['time_slot_id']}",
                $schedules
            );

            if (count($pairKeys) !== count(array_unique($pairKeys))) {
                $validator->errors()->add('schedules', 'Mỗi cặp ngày học và khung giờ chỉ được chọn một lần.');

                return;
            }

            $timeSlotIds = array_values(array_unique(array_map(
                fn (array $schedule): int => (int) $schedule['time_slot_id'],
                $schedules
            )));
            $activeTimeSlotCount = TimeSlot::query()
                ->where('status', 'ACTIVE')
                ->whereIn('time_slot_id', $timeSlotIds)
                ->count();

            if ($activeTimeSlotCount !== count($timeSlotIds)) {
                $validator->errors()->add('schedules', 'Một hoặc nhiều khung giờ đã chọn không tồn tại hoặc không còn hoạt động.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Vui lòng chọn môn học.',
            'subject_id.integer' => 'Môn học không hợp lệ.',
            'subject_level_id.required' => 'Vui lòng chọn trình độ.',
            'subject_level_id.integer' => 'Trình độ không hợp lệ.',
            'subject_level_id.exists' => 'Trình độ không thuộc môn học đã chọn.',
            'learning_mode.required' => 'Vui lòng chọn hình thức học.',
            'learning_mode.in' => 'Hình thức học không hợp lệ.',
            'preferred_tutor_gender.required' => 'Vui lòng chọn ưu tiên gia sư.',
            'preferred_tutor_gender.in' => 'Lựa chọn ưu tiên gia sư không hợp lệ.',
            'province_id.required' => 'Vui lòng chọn tỉnh hoặc thành phố.',
            'province_id.integer' => 'Tỉnh hoặc thành phố không hợp lệ.',
            'ward_id.required' => 'Vui lòng chọn phường hoặc xã.',
            'ward_id.integer' => 'Phường hoặc xã không hợp lệ.',
            'ward_id.exists' => 'Phường hoặc xã không thuộc tỉnh đã chọn.',
            'address_detail.required' => 'Vui lòng nhập địa chỉ chi tiết.',
            'address_detail.string' => 'Địa chỉ chi tiết không hợp lệ.',
            'address_detail.max' => 'Địa chỉ chi tiết không được vượt quá 255 ký tự.',
            'expected_fee.required' => 'Vui lòng nhập học phí dự kiến.',
            'expected_fee.numeric' => 'Học phí dự kiến phải là một con số.',
            'expected_fee.gt' => 'Học phí dự kiến phải lớn hơn 0.',
            'expected_fee.max' => 'Học phí dự kiến không được vượt quá 10.000.000đ.',
            'fee_type.required' => 'Vui lòng chọn loại học phí.',
            'fee_type.in' => 'Loại học phí không hợp lệ.',
            'description.required' => 'Vui lòng mô tả nhu cầu học.',
            'description.string' => 'Mô tả nhu cầu không hợp lệ.',
            'description.max' => 'Mô tả nhu cầu không được vượt quá 3.000 ký tự.',
            'schedules.required' => 'Vui lòng chọn ít nhất một lịch học.',
            'schedules.array' => 'Lịch học không hợp lệ.',
            'schedules.min' => 'Vui lòng chọn ít nhất một lịch học.',
            'schedules.max' => 'Bạn chỉ có thể chọn tối đa 20 lịch học.',
            'schedules.*.array' => 'Lịch học không hợp lệ.',
            'schedules.*.day_of_week.required' => 'Vui lòng chọn ngày học cho từng lịch.',
            'schedules.*.day_of_week.integer' => 'Ngày học không hợp lệ.',
            'schedules.*.day_of_week.in' => 'Ngày học phải nằm trong khoảng từ Thứ 2 đến Chủ nhật.',
            'schedules.*.time_slot_id.required' => 'Vui lòng chọn khung giờ cho từng lịch.',
            'schedules.*.time_slot_id.integer' => 'Khung giờ không hợp lệ.',
            'expires_at.required' => 'Thời hạn yêu cầu không hợp lệ.',
            'expires_at.date' => 'Thời hạn yêu cầu không hợp lệ.',
            'expires_at.after' => 'Thời hạn yêu cầu phải nằm trong tương lai.',
            'expires_at.before_or_equal' => 'Thời hạn yêu cầu không được vượt quá 7 ngày.',
        ];
    }
}
