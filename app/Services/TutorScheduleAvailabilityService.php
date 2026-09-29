<?php

namespace App\Services;

use App\Models\TutoringClass;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TutorScheduleAvailabilityService
{
    /**
     * @return array<string, true>
     */
    public function occupiedSlotKeys(int $tutorProfileId, ?CarbonInterface $date = null): array
    {
        $date = ($date ?? now())->toDateString();

        return DB::table('class_schedules as class_schedules')
            ->join('tutoring_classes as tutoring_classes', 'tutoring_classes.class_id', '=', 'class_schedules.class_id')
            ->join('contracts as contracts', 'contracts.contract_id', '=', 'tutoring_classes.contract_id')
            ->where('contracts.tutor_profile_id', $tutorProfileId)
            ->where('tutoring_classes.status', TutoringClass::STATUS_ACTIVE)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('class_schedules.effective_from')
                    ->orWhereDate('class_schedules.effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('class_schedules.effective_to')
                    ->orWhereDate('class_schedules.effective_to', '>=', $date);
            })
            ->get(['class_schedules.day_of_week', 'class_schedules.time_slot_id'])
            ->mapWithKeys(fn ($schedule): array => [
                $this->slotKey((int) $schedule->day_of_week, (int) $schedule->time_slot_id) => true,
            ])
            ->all();
    }

    /**
     * @return array<string, true>
     */
    public function availableSlotKeys(int $tutorProfileId, ?CarbonInterface $date = null): array
    {
        $occupiedSlotKeys = $this->occupiedSlotKeys($tutorProfileId, $date);

        return DB::table('tutor_availabilities')
            ->where('tutor_profile_id', $tutorProfileId)
            ->where('is_available', true)
            ->get(['day_of_week', 'time_slot_id'])
            ->mapWithKeys(function ($availability) use ($occupiedSlotKeys): array {
                $key = $this->slotKey((int) $availability->day_of_week, (int) $availability->time_slot_id);

                return isset($occupiedSlotKeys[$key]) ? [] : [$key => true];
            })
            ->all();
    }

    public function hasConflictWithRequest(
        int $tutorProfileId,
        int $requestId,
        ?CarbonInterface $date = null
    ): bool {
        $date = ($date ?? now())->toDateString();
        $requestedSchedules = DB::table('request_schedules as request_schedules')
            ->join('time_slots as requested_slots', 'requested_slots.time_slot_id', '=', 'request_schedules.time_slot_id')
            ->where('request_schedules.request_id', $requestId)
            ->get([
                'request_schedules.day_of_week',
                'requested_slots.start_time',
                'requested_slots.end_time',
            ]);

        if ($requestedSchedules->isEmpty()) {
            return true;
        }

        return DB::table('class_schedules as class_schedules')
            ->join('time_slots as class_slots', 'class_slots.time_slot_id', '=', 'class_schedules.time_slot_id')
            ->join('tutoring_classes as tutoring_classes', 'tutoring_classes.class_id', '=', 'class_schedules.class_id')
            ->join('contracts as contracts', 'contracts.contract_id', '=', 'tutoring_classes.contract_id')
            ->where('contracts.tutor_profile_id', $tutorProfileId)
            ->where('tutoring_classes.status', TutoringClass::STATUS_ACTIVE)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('class_schedules.effective_from')
                    ->orWhereDate('class_schedules.effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('class_schedules.effective_to')
                    ->orWhereDate('class_schedules.effective_to', '>=', $date);
            })
            ->where(function ($query) use ($requestedSchedules): void {
                foreach ($requestedSchedules as $schedule) {
                    $query->orWhere(function ($scheduleQuery) use ($schedule): void {
                        $scheduleQuery
                            ->where('class_schedules.day_of_week', $schedule->day_of_week)
                            ->where('class_slots.start_time', '<', $schedule->end_time)
                            ->where('class_slots.end_time', '>', $schedule->start_time);
                    });
                }
            })
            ->exists();
    }

    public function slotKey(int $dayOfWeek, int $timeSlotId): string
    {
        return "{$dayOfWeek}:{$timeSlotId}";
    }
}
