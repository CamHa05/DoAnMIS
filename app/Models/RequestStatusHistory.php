<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatusHistory extends Model
{
    protected $table = 'request_status_history';

    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'initiated_by_user_id',
        'old_status',
        'new_status',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function tutoringRequest()
    {
        return $this->belongsTo(
            TutoringRequest::class,
            'request_id',
            'request_id'
        );
    }

    public function initiatedBy()
    {
        return $this->belongsTo(
            User::class,
            'initiated_by_user_id',
            'user_id'
        );
    }
}