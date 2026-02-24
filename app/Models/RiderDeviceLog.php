<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderDeviceLog extends Model
{
    protected $fillable = [
        'rider_id',
        'ip_address',
        'location',
        'latitude',
        'longitude',
        'device_name',
        'user_agent',
        'last_login_at'
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
