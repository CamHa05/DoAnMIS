<?php

namespace App\Http\Requests;

use App\Models\TutorDocument;
use App\Models\TutorProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ManageApprovedTutorDocument extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tutorProfile()
            ->where('approval_status', TutorProfile::STATUS_APPROVED)
            ->exists() === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('document_name'))) {
            $this->merge(['document_name' => trim($this->input('document_name'))]);
        }
    }

    public function rules(): array
    {
        $fileRule = File::types(TutorDocument::ALLOWED_EXTENSIONS)
            ->extensions(TutorDocument::ALLOWED_EXTENSIONS)
            ->max(TutorDocument::MAX_FILE_SIZE_KB);

        return [
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys(TutorDocument::REGISTRATION_TYPE_LABELS)),
            ],
            'document_name' => ['required', 'string', 'max:255'],
            'document' => $this->route('document') === null
                ? ['required', $fileRule]
                : ['nullable', $fileRule],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Vui lòng chọn loại tài liệu.',
            'document_type.in' => 'Loại tài liệu không được hỗ trợ.',
            'document_name.required' => 'Vui lòng nhập tên tài liệu.',
            'document_name.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
            'document.required' => 'Vui lòng chọn tệp minh chứng.',
            'document.file' => 'Tệp minh chứng không hợp lệ.',
            'document.mimes' => 'Tệp phải có định dạng PDF, JPG, JPEG hoặc PNG.',
            'document.extensions' => 'Phần mở rộng của tệp không hợp lệ.',
            'document.max' => 'Dung lượng tệp không được vượt quá 5 MB.',
        ];
    }
}
