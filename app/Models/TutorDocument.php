<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorDocument extends Model
{
    public const TYPE_DEGREE = 'DEGREE';

    public const TYPE_CERTIFICATE = 'CERTIFICATE';

    public const TYPE_STUDENT_CARD = 'STUDENT_CARD';

    public const TYPE_TRANSCRIPT = 'TRANSCRIPT';

    public const TYPE_OTHER = 'OTHER';

    public const TYPE_LABELS = [
        self::TYPE_DEGREE => 'Bằng cấp',
        self::TYPE_CERTIFICATE => 'Chứng chỉ',
        self::TYPE_STUDENT_CARD => 'Thẻ sinh viên',
        self::TYPE_TRANSCRIPT => 'Bảng điểm',
        self::TYPE_OTHER => 'Khác',
    ];

    public const REGISTRATION_TYPE_LABELS = [
        self::TYPE_DEGREE => 'Bằng cấp',
        self::TYPE_CERTIFICATE => 'Chứng chỉ',
        self::TYPE_OTHER => 'Khác',
    ];

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chờ xác minh',
        self::STATUS_APPROVED => 'Đã xác minh',
        self::STATUS_REJECTED => 'Từ chối',
    ];

    public const TUTOR_DELETABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REJECTED,
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
    ];

    public const MAX_DOCUMENTS_PER_PROFILE = 10;

    public const MAX_FILE_SIZE_KB = 5120;

    public const STORAGE_PREFIX = 'tutor-documents';

    protected $table = 'tutor_documents';

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'tutor_profile_id',
        'document_type',
        'document_name',
        'file_url',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
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

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? 'Không xác định';
    }

    public function verificationStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->verification_status] ?? 'Không xác định';
    }

    public function verificationStatusTone(): string
    {
        return match ($this->verification_status) {
            self::STATUS_PENDING => 'pending',
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            default => 'unknown',
        };
    }

    public function canBeDeletedByTutor(): bool
    {
        return in_array($this->verification_status, self::TUTOR_DELETABLE_STATUSES, true);
    }
}
