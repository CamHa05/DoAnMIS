<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationLevel extends Model
{
    protected $table = 'education_levels';

    protected $primaryKey = 'education_level_id';

    protected $fillable = [
        'level_name',
        'description',
        'sort_order',
        'status',
    ];

    public function subjectLevels()
    {
        return $this->hasMany(
            SubjectLevel::class,
            'education_level_id',
            'education_level_id'
        );
    }
}