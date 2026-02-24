<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCodeUsage extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $table = 'promo_code_usage';

    protected $fillable = [
        'promo_code_id', 'user_id', 'delivery_id', 'discount_amount', 'created_at'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
