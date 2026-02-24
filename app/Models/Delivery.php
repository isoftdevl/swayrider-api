<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'user_id',
        'rider_id',
        'status',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_contact_name',
        'pickup_contact_phone',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'dropoff_contact_name',
        'dropoff_contact_phone',
        'package_size',
        'package_description',
        'package_value',
        'distance_km',
        'base_price',
        'distance_price',
        'size_fee',
        'time_fee',
        'urgency',
        'urgency_multiplier',
        'total_price',
        'rider_earning',
        'platform_commission',
        'company_commission',
        'payment_method',
        'payment_status',
        'assigned_at',
        'rider_accepted_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'pickup_proof_photo',
        'delivery_proof_photo',
        'delivery_pin',
        'company_id',
    ];

    protected $casts = [
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'package_value' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'base_price' => 'decimal:2',
        'distance_price' => 'decimal:2',
        'size_fee' => 'decimal:2',
        'time_fee' => 'decimal:2',
        'urgency_multiplier' => 'decimal:2',
        'total_price' => 'decimal:2',
        'rider_earning' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'company_commission' => 'decimal:2',
        'assigned_at' => 'datetime',
        'rider_accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(DeliveryStatusLog::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function chat()
    {
        return $this->hasOne(Chat::class);
    }
}
