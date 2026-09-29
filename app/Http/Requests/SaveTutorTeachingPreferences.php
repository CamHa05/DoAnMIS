<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTutorTeachingPreferences extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $supportsOnline = $this->normalizeCheckbox('supports_online');
        $supportsOffline = $this->normalizeCheckbox('supports_offline');

        $this->merge([
            'supports_online' => $supportsOnline,
            'supports_offline' => $supportsOffline,
            'ward_ids' => $supportsOffline === false
                ? []
                : $this->input('ward_ids'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supports_online' => ['boolean'],
            'supports_offline' => ['boolean'],
            'province_id' => [
                'nullable',
                'integer',
                Rule::exists('provinces', 'province_id'),
            ],
            'ward_ids' => [
                Rule::requiredIf(fn (): bool => $this->input('supports_offline') === true),
                'array',
                Rule::when(
                    $this->input('supports_offline') === true,
                    ['min:1']
                ),
            ],
            'ward_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('wards', 'ward_id'),
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
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

    /**
     * @return array<int, int>
     */
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supports_online.boolean' => 'Hình thức dạy online không hợp lệ.',
            'supports_offline.boolean' => 'Hình thức dạy trực tiếp không hợp lệ.',
            'province_id.integer' => 'Tỉnh hoặc thành phố không hợp lệ.',
            'province_id.exists' => 'Tỉnh hoặc thành phố đã chọn không tồn tại.',
            'ward_ids.required' => 'Vui lòng chọn ít nhất một khu vực dạy trực tiếp.',
            'ward_ids.array' => 'Danh sách khu vực dạy trực tiếp không hợp lệ.',
            'ward_ids.min' => 'Vui lòng chọn ít nhất một khu vực dạy trực tiếp.',
            'ward_ids.*.integer' => 'Phường hoặc xã đã chọn không hợp lệ.',
            'ward_ids.*.distinct' => 'Danh sách phường hoặc xã không được trùng lặp.',
            'ward_ids.*.exists' => 'Một hoặc nhiều phường hoặc xã đã chọn không tồn tại.',
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
