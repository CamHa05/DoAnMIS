<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorTeachingArea extends Model
{
    protected $table = 'tutor_teaching_areas';

    protected $primaryKey = 'teaching_area_id';

    protected $fillable = [
        'tutor_profile_id',
        'ward_id',
    ];

    public function tutorProfile()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function ward()
    {
        return $this->belongsTo(
            Ward::class,
            'ward_id',
            'ward_id'
        );
    }
}