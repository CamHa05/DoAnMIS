<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorAvailability extends Model
{
    protected $table = 'tutor_availabilities';

    protected $primaryKey = 'availability_id';

    protected $fillable = [
        'tutor_profile_id',
        'time_slot_id',
        'day_of_week',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function dayLabel(): string
    {
        return match ((int) $this->day_of_week) {
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
            default => 'Ngày chưa xác định',
        };
    }

    public function tutorProfile()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function timeSlot()
    {
        return $this->belongsTo(
            TimeSlot::class,
            'time_slot_id',
            'time_slot_id'
        );
    }
}
