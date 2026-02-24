<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryStatusLog extends Model
{
    use HasFactory;

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'status',
        'latitude',
        'longitude',
        'note',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function createdBy()
    {
        return $this->morphTo();
    }
}
