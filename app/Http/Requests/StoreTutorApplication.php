<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTutorApplication extends FormRequest
{
    /** @var string */
    protected $errorBag = 'tutorApplication';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['fee_option', 'proposed_fee', 'message'] as $field) {
            if (is_array($this->input($field))) {
                $normalized[$field] = null;
            }
        }

        if (is_string($this->input('fee_option'))) {
            $normalized['fee_option'] = strtolower(trim($this->input('fee_option')));
        }

        if (is_string($this->input('message'))) {
            $normalized['message'] = trim($this->input('message'));
        }

        if (($normalized['fee_option'] ?? $this->input('fee_option')) === 'expected') {
            $normalized['proposed_fee'] = null;
        } elseif (is_string($this->input('proposed_fee'))) {
            $compactFee = preg_replace('/[\s\x{00A0}]+/u', '', trim($this->input('proposed_fee')));

            if (
                is_string($compactFee)
                && preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $compactFee) === 1
            ) {
                $compactFee = str_replace(['.', ','], '', $compactFee);
            }

            $normalized['proposed_fee'] = $compactFee;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_option' => ['required', Rule::in(['expected', 'custom'])],
            'proposed_fee' => [
                'exclude_unless:fee_option,custom',
                'bail',
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'message' => ['bail', 'required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fee_option.required' => 'Vui lòng chọn cách đề xuất học phí.',
            'fee_option.in' => 'Lựa chọn học phí không hợp lệ.',
            'proposed_fee.required' => 'Vui lòng nhập mức học phí bạn muốn đề xuất.',
            'proposed_fee.numeric' => 'Học phí đề xuất phải là một số hợp lệ.',
            'proposed_fee.min' => 'Học phí đề xuất không được nhỏ hơn 0.',
            'proposed_fee.max' => 'Học phí đề xuất vượt quá giới hạn cho phép.',
            'message.required' => 'Vui lòng nhập lời nhắn đến người học.',
            'message.string' => 'Lời nhắn không hợp lệ.',
            'message.max' => 'Lời nhắn không được vượt quá 500 ký tự.',
        ];
    }
}
