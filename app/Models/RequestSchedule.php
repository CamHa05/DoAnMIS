<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestSchedule extends Model
{
    protected $table = 'request_schedules';

    protected $primaryKey = 'request_schedule_id';

    protected $fillable = [
        'request_id',
        'time_slot_id',
        'day_of_week',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
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

    public function tutoringRequest()
    {
        return $this->belongsTo(
            TutoringRequest::class,
            'request_id',
            'request_id'
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
