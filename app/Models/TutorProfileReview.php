<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorProfileReview extends Model
{
    protected $table = 'tutor_profile_reviews';

    protected $primaryKey = 'review_id';

    protected $fillable = [
        'tutor_profile_id',
        'reviewer_user_id',
        'review_source',
        'review_result',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function tutorProfile()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function reviewer()
    {
        return $this->belongsTo(
            User::class,
            'reviewer_user_id',
            'user_id'
        );
    }
}