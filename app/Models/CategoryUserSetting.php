<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryUserSetting extends Model
{
    protected $table = 'category_user_settings';

    protected $fillable = ['user_id', 'category_id', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
