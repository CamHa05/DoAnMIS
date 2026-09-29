<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorSubject extends Model
{
    protected $table = 'tutor_subjects';

    protected $primaryKey = 'tutor_subject_id';

    protected $fillable = [
        'tutor_profile_id',
        'subject_id',
        'description',
    ];

    public function tutorProfile()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function subject()
    {
        return $this->belongsTo(
            Subject::class,
            'subject_id',
            'subject_id'
        );
    }

    public function tutorSubjectLevels()
    {
        return $this->hasMany(
            TutorSubjectLevel::class,
            'tutor_subject_id',
            'tutor_subject_id'
        );
    }
}