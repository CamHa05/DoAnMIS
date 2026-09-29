<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_DISABLED = 'DISABLED';

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'google_id',
        'email',
        'full_name',
        'avatar_url',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'last_login' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_ACTIVE;
    }

    public function isDisabled(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_DISABLED;
    }

    public function tutorProfile()
    {
        return $this->hasOne(
            TutorProfile::class,
            'user_id',
            'user_id'
        );
    }
    public function tutoringRequests()
    {
        return $this->hasMany(
            TutoringRequest::class,
            'user_id',
            'user_id'
        );
    }

    public function requestStatusHistories()
    {
        return $this->hasMany(
            RequestStatusHistory::class,
            'initiated_by_user_id',
            'user_id'
        );
    }
    public function contractConfirmations()
    {
        return $this->hasMany(
            ContractConfirmation::class,
            'user_id',
            'user_id'
        );
    }
    public function tutorProfileReviews()
    {
        return $this->hasMany(
            TutorProfileReview::class,
            'reviewer_user_id',
            'user_id'
        );
    }
    public function systemNotifications()
    {
        return $this->hasMany(
            Notification::class,
            'user_id',
            'user_id'
        );
    }
    public function identityVerification(): HasOne
    {
        return $this->hasOne(
            IdentityVerification::class,
            'user_id'
        );
    }
}
