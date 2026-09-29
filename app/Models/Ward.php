<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    protected $table = 'wards';

    protected $primaryKey = 'ward_id';

    protected $fillable = [
        'province_id',
        'ward_code',
        'ward_name',
        'ward_type',
    ];

    public function province()
    {
        return $this->belongsTo(
            Province::class,
            'province_id',
            'province_id'
        );
    }

    public function teachingAreas()
    {
        return $this->hasMany(
            TutorTeachingArea::class,
            'ward_id',
            'ward_id'
        );
    }

    public function tutoringRequests()
    {
        return $this->hasMany(
            TutoringRequest::class,
            'ward_id',
            'ward_id'
        );
    }
}