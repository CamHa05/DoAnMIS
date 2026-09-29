<?php

namespace App\Http\Requests;

use App\Models\SubjectLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTutorSpecialization extends FormRequest
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
            'subject_ids' => ['bail', 'required', 'array', 'min:1'],
            'subject_ids.*' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('subjects', 'subject_id')
                    ->where(fn ($query) => $query->where('status', 'ACTIVE')),
            ],
            'subject_levels' => ['bail', 'required', 'array'],
            'subject_levels.*' => ['bail', 'array'],
            'subject_levels.*.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('subject_levels', 'subject_level_id')
                    ->where(fn ($query) => $query->where('status', 'ACTIVE')),
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
                $subjectIds = $this->integerArray($this->input('subject_ids'));
                $rawLevelMap = $this->input('subject_levels');

                if (! is_array($rawLevelMap) || $subjectIds === []) {
                    return;
                }

                $levelIdsBySubject = [];

                foreach ($rawLevelMap as $rawSubjectId => $rawLevelIds) {
                    if (! $this->isPositiveInteger($rawSubjectId) || ! is_array($rawLevelIds)) {
                        $validator->errors()->add(
                            'subject_levels',
                            'Dữ liệu trình độ không hợp lệ. Vui lòng chọn lại chuyên môn.'
                        );

                        continue;
                    }

                    $subjectId = (int) $rawSubjectId;

                    if (! in_array($subjectId, $subjectIds, true)) {
                        $validator->errors()->add(
                            'subject_levels',
                            'Trình độ chỉ được chọn cho những môn học đã chọn.'
                        );

                        continue;
                    }

                    $levelIdsBySubject[$subjectId] = $this->integerArray($rawLevelIds);
                }

                foreach ($subjectIds as $subjectId) {
                    if (($levelIdsBySubject[$subjectId] ?? []) === []) {
                        $validator->errors()->add(
                            "subject_levels.$subjectId",
                            'Vui lòng chọn ít nhất một trình độ cho môn học này.'
                        );
                    }
                }

                $selectedLevelIds = collect($levelIdsBySubject)
                    ->flatten()
                    ->unique()
                    ->values();

                if ($selectedLevelIds->isEmpty()) {
                    return;
                }

                $levels = SubjectLevel::query()
                    ->where('status', 'ACTIVE')
                    ->whereIn('subject_level_id', $selectedLevelIds)
                    ->get(['subject_level_id', 'subject_id'])
                    ->keyBy(fn ($subjectLevel) => (int) $subjectLevel->subject_level_id);

                foreach ($levelIdsBySubject as $subjectId => $subjectLevelIds) {
                    foreach ($subjectLevelIds as $subjectLevelId) {
                        $subjectLevel = $levels->get($subjectLevelId);

                        if ($subjectLevel && (int) $subjectLevel->subject_id !== $subjectId) {
                            $validator->errors()->add(
                                "subject_levels.$subjectId",
                                'Trình độ được chọn không thuộc môn học tương ứng.'
                            );

                            break;
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function specializations(): array
    {
        $validated = $this->validated();
        $rawLevelMap = $validated['subject_levels'];
        $specializations = [];

        foreach ($this->integerArray($validated['subject_ids']) as $subjectId) {
            $specializations[$subjectId] = array_values(array_unique(
                $this->integerArray($rawLevelMap[$subjectId] ?? [])
            ));
        }

        return $specializations;
    }

    /**
     * @return array<int, int>
     */
    private function integerArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => $this->isPositiveInteger($item))
            ->map(fn ($item) => (int) $item)
            ->values()
            ->all();
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return (is_int($value) && $value > 0)
            || (is_string($value) && ctype_digit($value) && (int) $value > 0);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_ids.required' => 'Vui lòng chọn ít nhất một môn học.',
            'subject_ids.array' => 'Danh sách môn học không hợp lệ.',
            'subject_ids.min' => 'Vui lòng chọn ít nhất một môn học.',
            'subject_ids.*.required' => 'Môn học đã chọn không hợp lệ.',
            'subject_ids.*.integer' => 'Môn học đã chọn không hợp lệ.',
            'subject_ids.*.distinct' => 'Danh sách môn học không được trùng lặp.',
            'subject_ids.*.exists' => 'Môn học đã chọn không tồn tại hoặc không còn hoạt động.',
            'subject_levels.required' => 'Vui lòng chọn trình độ cho các môn học.',
            'subject_levels.array' => 'Danh sách trình độ không hợp lệ.',
            'subject_levels.*.array' => 'Danh sách trình độ của môn học không hợp lệ.',
            'subject_levels.*.*.integer' => 'Trình độ đã chọn không hợp lệ.',
            'subject_levels.*.*.distinct' => 'Danh sách trình độ không được trùng lặp.',
            'subject_levels.*.*.exists' => 'Trình độ đã chọn không tồn tại hoặc không còn hoạt động.',
        ];
    }
}
