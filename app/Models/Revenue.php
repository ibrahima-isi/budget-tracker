<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    /** @use HasFactory<\Database\Factories\RevenueFactory> */
    use HasFactory;

    protected $table = 'revenues';

    protected $fillable = [
        'user_id', 'source', 'amount', 'revenue_date', 'month', 'year', 'note', 'currency_code',
    ];

    protected $casts = [
        'revenue_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
