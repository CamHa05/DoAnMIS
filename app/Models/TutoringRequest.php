<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringRequest extends Model
{
    public const STATUS_OPEN = 'OPEN';

    public const STATUS_MATCHED = 'MATCHED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'tutoring_requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'user_id',
        'target_tutor_profile_id',
        'subject_level_id',
        'ward_id',
        'request_type',
        'learning_mode',
        'preferred_tutor_gender',
        'address_detail',
        'expected_fee',
        'fee_type',
        'description',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_fee' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return match (strtoupper((string) $this->request_type)) {
            'PUBLIC' => 'Công khai',
            'DIRECT' => 'Đã gửi trực tiếp',
            default => 'Yêu cầu học',
        };
    }

    public function statusLabel(): string
    {
        $status = strtoupper((string) $this->status);
        $requestType = strtoupper((string) $this->request_type);

        return match (true) {
            $status === 'OPEN' && $requestType === 'PUBLIC' => 'Đang tìm gia sư',
            $status === 'PENDING' && $requestType === 'DIRECT' => 'Chờ gia sư phản hồi',
            $status === 'MATCHED' && $requestType === 'DIRECT' => 'Đã nhận',
            $status === 'MATCHED' => 'Đã ghép gia sư',
            $status === 'REJECTED' && $requestType === 'DIRECT' => 'Gia sư đã từ chối',
            $status === 'EXPIRED' => 'Đã hết hạn',
            default => 'Trạng thái khác',
        };
    }

    public function statusTone(): string
    {
        return match (strtoupper((string) $this->status)) {
            'OPEN', 'PENDING' => 'active',
            'MATCHED' => 'matched',
            'EXPIRED' => 'expired',
            'REJECTED' => 'rejected',
            default => 'neutral',
        };
    }

    public function learningModeLabel(): string
    {
        return match (strtoupper((string) $this->learning_mode)) {
            'ONLINE' => 'Trực tuyến',
            'OFFLINE' => 'Tại nhà',
            default => 'Chưa xác định',
        };
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'user_id'
        );
    }

    public function targetTutor()
    {
        return $this->belongsTo(
            TutorProfile::class,
            'target_tutor_profile_id',
            'tutor_profile_id'
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

    public function ward()
    {
        return $this->belongsTo(
            Ward::class,
            'ward_id',
            'ward_id'
        );
    }

    public function schedules()
    {
        return $this->hasMany(
            RequestSchedule::class,
            'request_id',
            'request_id'
        );
    }

    public function applications()
    {
        return $this->hasMany(
            TutorApplication::class,
            'request_id',
            'request_id'
        );
    }

    public function statusHistories()
    {
        return $this->hasMany(
            RequestStatusHistory::class,
            'request_id',
            'request_id'
        );
    }

    public function contract()
    {
        return $this->hasOne(
            Contract::class,
            'request_id',
            'request_id'
        );
    }
}
