<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    /** @use HasFactory<\Database\Factories\CategorieFactory> */
    use HasFactory;

    protected $fillable = ['nom', 'couleur', 'icone', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function depenses()
    {
        return $this->hasMany(Depense::class);
    }

    public function userSettings()
    {
        return $this->hasMany(CategorieUserSetting::class);
    }

    /** Global + user-owned categories visible to a given user. */
    public function scopeVisibleFor($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        });
    }

    /** Visible categories that the user has not disabled. */
    public function scopeEnabledFor($query, $user)
    {
        return $query->visibleFor($user)->whereDoesntHave('userSettings', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('enabled', false);
        });
    }
}
