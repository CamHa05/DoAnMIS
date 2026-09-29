<?php

namespace App\Http\Requests;

use App\Models\TutorDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreTutorDocument extends FormRequest
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
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys(TutorDocument::REGISTRATION_TYPE_LABELS)),
            ],
            'document_name' => [
                'required',
                'string',
                'max:255',
            ],
            'document' => [
                'required',
                File::types(TutorDocument::ALLOWED_EXTENSIONS)
                    ->extensions(TutorDocument::ALLOWED_EXTENSIONS)
                    ->max(TutorDocument::MAX_FILE_SIZE_KB),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.required' => 'Vui lòng chọn loại tài liệu.',
            'document_type.string' => 'Loại tài liệu không hợp lệ.',
            'document_type.in' => 'Loại tài liệu đã chọn không được hỗ trợ.',
            'document_name.required' => 'Vui lòng nhập tên hoặc mô tả ngắn cho tài liệu.',
            'document_name.string' => 'Tên tài liệu không hợp lệ.',
            'document_name.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
            'document.required' => 'Vui lòng chọn tệp minh chứng.',
            'document.file' => 'Tệp minh chứng không hợp lệ.',
            'document.mimes' => 'Tệp minh chứng phải có định dạng PDF, JPG, JPEG hoặc PNG.',
            'document.extensions' => 'Phần mở rộng của tệp phải là PDF, JPG, JPEG hoặc PNG.',
            'document.max' => 'Dung lượng tệp minh chứng không được vượt quá 5 MB.',
            'document.uploaded' => 'Không thể tải tệp minh chứng lên. Vui lòng thử lại.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document_name') && is_string($this->input('document_name'))) {
            $this->merge([
                'document_name' => trim($this->input('document_name')),
            ]);
        }
    }
}
