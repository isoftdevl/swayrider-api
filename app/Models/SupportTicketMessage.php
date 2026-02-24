<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'sender_type', 'sender_id', 'message',
        'attachments', 'read_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];
    
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function sender()
    {
        return $this->morphTo();
    }
}
