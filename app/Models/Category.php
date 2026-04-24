<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'color', 'icon', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function userSettings()
    {
        return $this->hasMany(CategoryUserSetting::class);
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
