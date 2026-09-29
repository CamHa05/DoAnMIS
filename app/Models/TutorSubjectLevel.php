<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorSubjectLevel extends Model
{
    protected $table = 'tutor_subject_levels';

    protected $primaryKey = 'tutor_subject_level_id';

    protected $fillable = [
        'tutor_subject_id',
        'subject_level_id',
    ];

    public function tutorSubject()
    {
        return $this->belongsTo(
            TutorSubject::class,
            'tutor_subject_id',
            'tutor_subject_id'
        );
    }

    public function subjectLevel()
    {
        return $this->belongsTo(
            SubjectLevel::class,
            'subject_level_id',
            'subject_level_id'
        );
    }
}