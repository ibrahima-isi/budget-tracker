<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorieUserSetting extends Model
{
    protected $fillable = ['user_id', 'categorie_id', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
