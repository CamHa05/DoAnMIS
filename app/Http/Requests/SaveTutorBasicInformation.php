<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveTutorBasicInformation extends FormRequest
{
    private const MYSQL_TEXT_MAX_BYTES = 65535;

    public function authorize(): bool
    {
        return $this->user() !== null;
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

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $textColumnLabels = [
            'bio' => 'Giới thiệu bản thân',
            'teaching_experience' => 'Kinh nghiệm giảng dạy',
        ];
        $fitsMysqlTextColumn = static function (string $attribute, mixed $value, Closure $fail) use ($textColumnLabels): void {
            if (is_string($value) && strlen($value) > self::MYSQL_TEXT_MAX_BYTES) {
                $fail(($textColumnLabels[$attribute] ?? 'Nội dung').' vượt quá giới hạn lưu trữ cho phép.');
            }
        };

        return [
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'headline' => 'tiêu đề hồ sơ',
            'bio' => 'giới thiệu bản thân',
            'education_summary' => 'học vấn',
            'teaching_experience' => 'kinh nghiệm giảng dạy',
            'hourly_rate' => 'học phí mong muốn',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'headline.required' => 'Vui lòng nhập tiêu đề hồ sơ.',
            'headline.string' => 'Tiêu đề hồ sơ không hợp lệ.',
            'headline.max' => 'Tiêu đề hồ sơ không được vượt quá 150 ký tự.',
            'bio.required' => 'Vui lòng giới thiệu ngắn gọn về bản thân.',
            'bio.string' => 'Giới thiệu bản thân không hợp lệ.',
            'education_summary.required' => 'Vui lòng nhập thông tin học vấn.',
            'education_summary.string' => 'Thông tin học vấn không hợp lệ.',
            'education_summary.max' => 'Thông tin học vấn không được vượt quá 500 ký tự.',
            'teaching_experience.required' => 'Vui lòng nhập kinh nghiệm giảng dạy.',
            'teaching_experience.string' => 'Kinh nghiệm giảng dạy không hợp lệ.',
            'hourly_rate.required' => 'Vui lòng nhập học phí mong muốn.',
            'hourly_rate.numeric' => 'Học phí mong muốn phải là một số hợp lệ.',
            'hourly_rate.decimal' => 'Học phí mong muốn chỉ được có tối đa 2 chữ số thập phân.',
            'hourly_rate.min' => 'Học phí mong muốn không được nhỏ hơn 0.',
            'hourly_rate.max' => 'Học phí mong muốn vượt quá giới hạn cho phép.',
        ];
    }
}
