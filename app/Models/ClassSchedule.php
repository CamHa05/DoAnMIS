<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $table = 'class_schedules';

    protected $primaryKey = 'class_schedule_id';

    protected $fillable = [
        'class_id',
        'time_slot_id',
        'day_of_week',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function tutoringClass()
    {
        return $this->belongsTo(
            TutoringClass::class,
            'class_id',
            'class_id'
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