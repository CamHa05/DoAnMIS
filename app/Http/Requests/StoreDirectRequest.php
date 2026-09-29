<?php

namespace App\Http\Requests;

use App\Models\TimeSlot;
use App\Models\TutorProfile;
use App\Services\TutorScheduleAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $schedules = [];

        foreach ((array) $this->input('schedule_selection') as $selection) {
            if (is_string($selection) && preg_match('/^(\d+):(\d+)$/', $selection, $matches)) {
                $schedules[] = [
                    'day_of_week' => $matches[1],
                    'time_slot_id' => $matches[2],
                ];
            }
        }

        $this->merge([
            'learning_mode' => strtoupper(trim((string) $this->input('learning_mode'))),
            'fee_type' => strtoupper(trim((string) $this->input('fee_type'))),
            'address_detail' => is_string($this->input('address_detail'))
                ? trim($this->input('address_detail'))
                : $this->input('address_detail'),
            'description' => is_string($this->input('description'))
                ? trim($this->input('description'))
                : $this->input('description'),
            'schedules' => $schedules,
            'terms_accepted' => filter_var($this->input('terms_accepted'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $tutorProfileId = (int) $this->route('tutor')->getKey();
        $subjectId = (int) $this->input('subject_id');
        $provinceId = (int) $this->input('province_id');

        return [
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('tutor_subjects', 'subject_id')->where(
                    fn ($query) => $query->where('tutor_profile_id', $tutorProfileId)
                ),
            ],
            'subject_level_id' => [
                'required',
                'integer',
                Rule::exists('tutor_subject_levels', 'subject_level_id')->where(
                    fn ($query) => $query
                        ->whereExists(fn ($subjectQuery) => $subjectQuery
                            ->selectRaw('1')
                            ->from('tutor_subjects')
                            ->whereColumn('tutor_subjects.tutor_subject_id', 'tutor_subject_levels.tutor_subject_id')
                            ->where('tutor_subjects.tutor_profile_id', $tutorProfileId)
                            ->where('tutor_subjects.subject_id', $subjectId)
                        )
                ),
            ],
            'learning_mode' => ['required', Rule::in(['ONLINE', 'OFFLINE'])],
            'province_id' => ['exclude_unless:learning_mode,OFFLINE', 'required', 'integer'],
            'ward_id' => [
                'exclude_unless:learning_mode,OFFLINE',
                'required',
                'integer',
                Rule::exists('wards', 'ward_id')->where(fn ($query) => $query->where('province_id', $provinceId)),
            ],
            'address_detail' => ['exclude_unless:learning_mode,OFFLINE', 'required', 'string', 'max:255'],
            'expected_fee' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'fee_type' => ['required', Rule::in(['HOURLY', 'MONTHLY'])],
            'description' => ['required', 'string', 'max:3000'],
            'schedules' => ['required', 'array', 'min:1', 'max:20'],
            'schedules.*' => ['array:day_of_week,time_slot_id'],
            'schedules.*.day_of_week' => ['required', 'integer', Rule::in(range(1, 7))],
            'schedules.*.time_slot_id' => ['required', 'integer'],
            'terms_accepted' => ['accepted'],
            'expires_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schedules = $this->input('schedules', []);
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
                $validator->errors()->add('schedules', 'Một hoặc nhiều khung giờ không còn hoạt động.');

                return;
            }

            $tutorProfile = $this->route('tutor');
            $availabilityKeys = $tutorProfile->availabilities()
                ->where('is_available', true)
                ->get(['day_of_week', 'time_slot_id'])
                ->mapWithKeys(fn ($availability): array => [
                    app(TutorScheduleAvailabilityService::class)->slotKey(
                        (int) $availability->day_of_week,
                        (int) $availability->time_slot_id
                    ) => true,
                ]);
            $occupiedKeys = app(TutorScheduleAvailabilityService::class)
                ->occupiedSlotKeys((int) $tutorProfile->getKey());

            foreach ($pairKeys as $pairKey) {
                if (! isset($availabilityKeys[$pairKey]) || isset($occupiedKeys[$pairKey])) {
                    $validator->errors()->add(
                        'schedules',
                        'Một hoặc nhiều khung giờ gia sư đã chọn không còn khả dụng.'
                    );

                    break;
                }
            }
        });
    }
}
