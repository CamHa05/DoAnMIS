<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $table = 'provinces';

    protected $primaryKey = 'province_id';

    protected $fillable = [
        'province_code',
        'province_name',
    ];

    public function wards()
    {
        return $this->hasMany(
            Ward::class,
            'province_id',
            'province_id'
        );
    }
}
