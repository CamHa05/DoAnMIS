<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectLevel extends Model
{
    protected $table = 'subject_levels';

    protected $primaryKey = 'subject_level_id';

    protected $fillable = [
        'subject_id',
        'education_level_id',
        'level_name',
        'description',
        'sort_order',
        'status',
    ];

    public function subject()
    {
        return $this->belongsTo(
            Subject::class,
            'subject_id',
            'subject_id'
        );
    }

    public function educationLevel()
    {
        return $this->belongsTo(
            EducationLevel::class,
            'education_level_id',
            'education_level_id'
        );
    }

    public function tutorSubjectLevels()
    {
        return $this->hasMany(
            TutorSubjectLevel::class,
            'subject_level_id',
            'subject_level_id'
        );
    }
        public function tutoringRequests()
    {
        return $this->hasMany(
            TutoringRequest::class,
            'subject_level_id',
            'subject_level_id'
        );
    }
}