<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorProfile extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const APPROVAL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'tutor_profiles';

    protected $primaryKey = 'tutor_profile_id';

    protected $fillable = [
        'user_id',
        'headline',
        'bio',
        'education_summary',
        'teaching_experience',
        'hourly_rate',
        'supports_online',
        'supports_offline',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'supports_online' => 'boolean',
            'supports_offline' => 'boolean',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function canEditTutorRegistration(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED
            || (
                $this->approval_status === self::STATUS_PENDING
                && $this->submitted_at === null
            );
    }

    public function canSubmitTutorRegistration(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED
            || (
                $this->approval_status === self::STATUS_PENDING
                && $this->submitted_at === null
            );
    }

    public function hasSubmittedTutorRegistration(): bool
    {
        return $this->approval_status === self::STATUS_PENDING
            && $this->submitted_at !== null;
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'user_id'
        );
    }

    public function tutorSubjects()
    {
        return $this->hasMany(
            TutorSubject::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function teachingAreas()
    {
        return $this->hasMany(
            TutorTeachingArea::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function availabilities()
    {
        return $this->hasMany(
            TutorAvailability::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function directRequests()
    {
        return $this->hasMany(
            TutoringRequest::class,
            'target_tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function applications()
    {
        return $this->hasMany(
            TutorApplication::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function contracts()
    {
        return $this->hasMany(
            Contract::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function documents()
    {
        return $this->hasMany(
            TutorDocument::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function reviews()
    {
        return $this->hasMany(
            TutorProfileReview::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }

    public function changeRequests()
    {
        return $this->hasMany(
            TutorProfileChangeRequest::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }
}
