<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];
}
