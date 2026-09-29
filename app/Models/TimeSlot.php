<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $table = 'time_slots';

    protected $primaryKey = 'time_slot_id';

    protected $fillable = [
        'start_time',
        'end_time',
        'slot_name',
        'status',
    ];

    public function tutorAvailabilities()
    {
        return $this->hasMany(
            TutorAvailability::class,
            'time_slot_id',
            'time_slot_id'
        );
    }
        public function requestSchedules()
    {
        return $this->hasMany(
            RequestSchedule::class,
            'time_slot_id',
            'time_slot_id'
        );
    }
        public function classSchedules()
    {
        return $this->hasMany(
            ClassSchedule::class,
            'time_slot_id',
            'time_slot_id'
        );
    }
}