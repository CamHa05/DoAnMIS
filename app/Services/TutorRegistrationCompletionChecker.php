<?php

namespace App\Services;

use App\Models\TutorProfile;

class TutorRegistrationCompletionChecker
{
    public const STEP_BASIC = 'basic';

    public const STEP_SPECIALIZATION = 'specialization';

    public const STEP_TEACHING_PREFERENCES = 'teaching_preferences';

    public const STEP_DOCUMENTS = 'documents';

    /**
     * @return array<string, string>
     */
    public function errors(TutorProfile $tutorProfile): array
    {
        $tutorProfile->loadMissing([
            'tutorSubjects.tutorSubjectLevels',
            'teachingAreas',
            'documents',
        ]);

        $errors = [];

        if (! $this->hasCompleteBasicInformation($tutorProfile)) {
            $errors[self::STEP_BASIC] = 'Thông tin cơ bản chưa hoàn tất.';
        }

        if (
            $tutorProfile->tutorSubjects->isEmpty()
            || $tutorProfile->tutorSubjects->contains(
                fn ($tutorSubject) => $tutorSubject->tutorSubjectLevels->isEmpty()
            )
        ) {
            $errors[self::STEP_SPECIALIZATION] = 'Chuyên môn chưa hoàn tất.';
        }

        if (
            (! $tutorProfile->supports_online && ! $tutorProfile->supports_offline)
            || ($tutorProfile->supports_offline && $tutorProfile->teachingAreas->isEmpty())
        ) {
            $errors[self::STEP_TEACHING_PREFERENCES] = 'Hình thức và khu vực giảng dạy chưa hoàn tất.';
        }

        if ($tutorProfile->documents->isEmpty()) {
            $errors[self::STEP_DOCUMENTS] = 'Minh chứng chưa hoàn tất.';
        }

        return $errors;
    }

    public function isComplete(TutorProfile $tutorProfile): bool
    {
        return $this->errors($tutorProfile) === [];
    }

    private function hasCompleteBasicInformation(TutorProfile $tutorProfile): bool
    {
        foreach (['headline', 'bio', 'education_summary', 'teaching_experience'] as $field) {
            if (! is_string($tutorProfile->{$field}) || trim($tutorProfile->{$field}) === '') {
                return false;
            }
        }

        return is_numeric($tutorProfile->hourly_rate)
            && (float) $tutorProfile->hourly_rate >= 0
            && (float) $tutorProfile->hourly_rate <= 9999999999.99;
    }
}
