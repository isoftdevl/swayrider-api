<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'value', 'type', 'description', 'group', 'is_public', 'updated_by'
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];
}
