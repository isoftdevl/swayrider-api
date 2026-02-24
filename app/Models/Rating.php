<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id', 'user_id', 'rider_id', 'rating', 
        'review', 'tags', 'response', 'responded_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'responded_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
