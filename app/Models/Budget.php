<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Categorie;

class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'mois', 'annee', 'montant_prevu', 'libelle', 'categorie_id'
    ];

    protected $appends = ['montant_depense', 'solde'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function depenses()
    {
        return $this->hasMany(Depense::class);
    }

    public function getMontantDepenseAttribute(): float
    {
        return (float) $this->depenses()->sum('montant');
    }

    public function getSoldeAttribute(): float
    {
        return (float) $this->montant_prevu - $this->montant_depense;
    }
}
