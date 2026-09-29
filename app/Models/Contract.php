<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'BANK_TRANSFER';

    public const PAYMENT_METHOD_CASH = 'CASH';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_CASH,
    ];

    protected $table = 'contracts';

    protected $primaryKey = 'contract_id';

    protected $fillable = [
        'request_id',
        'tutor_profile_id',
        'agreed_fee',
        'agreed_fee_type',
        'payment_method',
        'learning_mode',
        'start_date',
        'end_date',
        'terms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'agreed_fee' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
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

    public function confirmations()
    {
        return $this->hasMany(
            ContractConfirmation::class,
            'contract_id',
            'contract_id'
        );
    }

    public function tutoringClass()
    {
        return $this->hasOne(
            TutoringClass::class,
            'contract_id',
            'contract_id'
        );
    }
}
