<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $primaryKey = 'subject_id';

    protected $fillable = [
        'category_id',
        'subject_name',
        'description',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(
            SubjectCategory::class,
            'category_id',
            'category_id'
        );
    }

    public function subjectLevels()
    {
        return $this->hasMany(
            SubjectLevel::class,
            'subject_id',
            'subject_id'
        );
    }

    public function tutorSubjects()
    {
        return $this->hasMany(
            TutorSubject::class,
            'subject_id',
            'subject_id'
        );
    }
}