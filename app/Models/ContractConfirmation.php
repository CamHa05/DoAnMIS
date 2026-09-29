<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractConfirmation extends Model
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    protected $table = 'contract_confirmations';

    protected $primaryKey = 'confirmation_id';

    protected $fillable = [
        'contract_id',
        'user_id',
        'confirmation_status',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function contract()
    {
        return $this->belongsTo(
            Contract::class,
            'contract_id',
            'contract_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'user_id'
        );
    }
}
