<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorApplication extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_EXPIRED = 'EXPIRED';

    protected $table = 'tutor_applications';

    protected $primaryKey = 'application_id';

    const CREATED_AT = 'applied_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'request_id',
        'tutor_profile_id',
        'message',
        'proposed_fee',
    ];

    protected function casts(): array
    {
        return [
            'proposed_fee' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match (strtoupper((string) $this->status)) {
            'PENDING' => 'Đang chờ',
            'ACCEPTED' => 'Đã chấp nhận',
            'REJECTED' => 'Không được chọn',
            'EXPIRED' => 'Đã hết hạn',
            default => 'Trạng thái khác',
        };
    }

    public function statusTone(): string
    {
        return match (strtoupper((string) $this->status)) {
            'PENDING' => 'pending',
            'ACCEPTED' => 'accepted',
            'REJECTED', 'EXPIRED' => 'muted',
            default => 'neutral',
        };
    }

    public function ownerStatusLabel(): string
    {
        return match (strtoupper((string) $this->status)) {
            self::STATUS_PENDING => 'Chờ xem xét',
            self::STATUS_ACCEPTED => 'Đã chọn',
            self::STATUS_REJECTED => 'Không được chọn',
            self::STATUS_EXPIRED => 'Hết hiệu lực',
            default => $this->statusLabel(),
        };
    }

    public function ownerStatusTone(): string
    {
        return match (strtoupper((string) $this->status)) {
            self::STATUS_PENDING => 'pending',
            self::STATUS_ACCEPTED => 'accepted',
            self::STATUS_REJECTED => 'rejected',
            self::STATUS_EXPIRED => 'expired',
            default => 'neutral',
        };
    }

    public function tutoringRequest()
    {
        return $this->belongsTo(
            TutoringRequest::class,
            'request_id',
            'request_id'
        );
    }

    public function tutorProfile()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'tutor_profile_id',
            'tutor_profile_id'
        );
    }
}
