<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 
        'min_order_amount', 'usage_limit', 'usage_limit_per_user', 'times_used',
        'user_type', 'starts_at', 'expires_at', 'is_active', 'created_by'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the admin who created this promo code
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get usage records for this promo code
     */
    public function usages()
    {
        return $this->hasMany(PromoCodeUsage::class);
    }
}
