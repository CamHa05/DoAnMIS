<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorProfileChangeRequest extends Model
{
    public const TYPE_EDUCATION = 'EDUCATION';

    public const TYPE_EXPERIENCE = 'EXPERIENCE';

    public const TYPE_SPECIALIZATION = 'SPECIALIZATION';

    public const TYPE_DOCUMENT = 'DOCUMENT';

    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_REPLACE = 'REPLACE';

    public const ACTION_ADD = 'ADD';

    public const ACTION_DELETE = 'DELETE';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'tutor_profile_change_requests';

    protected $primaryKey = 'change_request_id';

    protected $fillable = [
        'tutor_profile_id',
        'reviewer_user_id',
        'change_type',
        'change_action',
        'target_id',
        'payload',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'payload' => 'array',
            'submitted_at' => 'datetime',
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

    public function targetDocument()
    {
        return $this->belongsTo(
            TutorDocument::class,
            'target_id',
            'document_id'
        );
    }

    public function typeLabel(): string
    {
        return match ($this->change_type) {
            self::TYPE_EDUCATION => 'Học vấn',
            self::TYPE_EXPERIENCE => 'Kinh nghiệm giảng dạy',
            self::TYPE_SPECIALIZATION => 'Chuyên môn',
            self::TYPE_DOCUMENT => 'Minh chứng',
            default => 'Thay đổi hồ sơ',
        };
    }

    public function actionLabel(): string
    {
        return match ($this->change_action) {
            self::ACTION_ADD => 'Thêm mới',
            self::ACTION_DELETE => 'Yêu cầu xóa',
            self::ACTION_REPLACE => 'Thay thế',
            default => 'Cập nhật',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Đã duyệt',
            self::STATUS_REJECTED => 'Bị từ chối',
            default => 'Chờ duyệt',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            default => 'pending',
        };
    }
}
