<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Depense extends Model
{
    /** @use HasFactory<\Database\Factories\DepenseFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'budget_id', 'categorie_id',
        'libelle', 'montant', 'date_depense', 'note', 'currency_code'
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant'      => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }
}
