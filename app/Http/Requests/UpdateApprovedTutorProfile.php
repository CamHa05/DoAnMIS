<?php

namespace App\Http\Requests;

use App\Models\TutorProfile;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateApprovedTutorProfile extends SaveTutorSpecialization
{
    private const MYSQL_TEXT_MAX_BYTES = 65535;

    public function authorize(): bool
    {
        return $this->user()?->tutorProfile()
            ->where('approval_status', TutorProfile::STATUS_APPROVED)
            ->exists() === true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['headline', 'bio', 'education_summary', 'teaching_experience', 'hourly_rate'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            } elseif (is_array($value)) {
                $normalized[$field] = null;
            }
        }

        $supportsOnline = $this->normalizeCheckbox('supports_online');
        $supportsOffline = $this->normalizeCheckbox('supports_offline');

        $this->merge([
            ...$normalized,
            'supports_online' => $supportsOnline,
            'supports_offline' => $supportsOffline,
            'ward_ids' => $supportsOffline === false ? [] : $this->input('ward_ids'),
        ]);
    }

    public function rules(): array
    {
        $fitsMysqlTextColumn = static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strlen($value) > self::MYSQL_TEXT_MAX_BYTES) {
                $fail('Nội dung vượt quá giới hạn lưu trữ cho phép.');
            }
        };

        return [
            ...parent::rules(),
            'headline' => ['bail', 'required', 'string', 'max:150'],
            'bio' => ['bail', 'required', 'string', $fitsMysqlTextColumn],
            'education_summary' => ['bail', 'required', 'string', 'max:500'],
            'teaching_experience' => ['bail', 'required', 'string', $fitsMysqlTextColumn],
            'hourly_rate' => [
                'bail',
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'supports_online' => ['boolean'],
            'supports_offline' => ['boolean'],
            'ward_ids' => [
                Rule::requiredIf(fn (): bool => $this->input('supports_offline') === true),
                'array',
                Rule::when($this->input('supports_offline') === true, ['min:1']),
            ],
            'ward_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('wards', 'ward_id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (
                    $this->input('supports_online') !== true
                    && $this->input('supports_offline') !== true
                ) {
                    $validator->errors()->add(
                        'supports_online',
                        'Vui lòng chọn ít nhất một hình thức giảng dạy.'
                    );
                }
            },
        ];
    }

    /** @return array<int, int> */
    public function wardIds(): array
    {
        if (! $this->boolean('supports_offline')) {
            return [];
        }

        return collect($this->validated('ward_ids', []))
            ->map(fn ($wardId) => (int) $wardId)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'ward_ids.required' => 'Vui lòng thêm ít nhất một khu vực khi chọn dạy trực tiếp.',
            'ward_ids.array' => 'Danh sách khu vực giảng dạy không hợp lệ.',
            'ward_ids.min' => 'Vui lòng thêm ít nhất một khu vực khi chọn dạy trực tiếp.',
            'ward_ids.*.integer' => 'Khu vực đã chọn không hợp lệ.',
            'ward_ids.*.distinct' => 'Mỗi khu vực chỉ được thêm một lần.',
            'ward_ids.*.exists' => 'Phường/xã đã chọn không tồn tại.',
        ];
    }

    private function normalizeCheckbox(string $key): mixed
    {
        if (! $this->exists($key)) {
            return false;
        }

        $value = $this->input($key);

        if (in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [false, 0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $value;
    }
}
