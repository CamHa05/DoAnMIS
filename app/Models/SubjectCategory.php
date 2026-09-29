<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectCategory extends Model
{
    protected $table = 'subject_categories';

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'description',
        'status',
    ];

    public function subjects()
    {
        return $this->hasMany(
            Subject::class,
            'category_id',
            'category_id'
        );
    }
}