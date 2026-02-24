<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_id', 'status'];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
