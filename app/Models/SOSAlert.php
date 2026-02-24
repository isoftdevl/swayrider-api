<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SOSAlert extends Model
{
    protected $table = 'sos_alerts';

    protected $fillable = [
        'rider_id',
        'delivery_id',
        'latitude',
        'longitude',
        'status',
        'comment'
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
