<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'user_type', 'user_id', 'delivery_id',
        'category', 'subject', 'description', 'priority', 'status',
        'assigned_to', 'resolved_at'
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
