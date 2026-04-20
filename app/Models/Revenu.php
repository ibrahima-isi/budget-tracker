<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Revenu extends Model
{
    /** @use HasFactory<\Database\Factories\RevenuFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'source', 'montant', 'date_revenu', 'mois', 'annee', 'note'
    ];

    protected $casts = [
        'date_revenu' => 'date',
        'montant'     => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
