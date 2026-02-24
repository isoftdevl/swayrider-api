<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id', 'raised_by_type', 'raised_by_id', 'category',
        'description', 'evidence_photos', 'status', 'resolution',
        'refund_amount', 'resolved_by', 'resolved_at'
    ];

    protected $casts = [
        'evidence_photos' => 'array',
        'refund_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function messages()
    {
        return $this->hasMany(DisputeMessage::class);
    }

    // Polymorphic for raised_by...
}
