<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'month', 'year', 'planned_amount', 'label', 'category_id', 'currency_code',
    ];

    protected $appends = ['expense_amount', 'balance'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function getExpenseAmountAttribute(): float
    {
        if ($this->relationLoaded('expenses')) {
            return (float) $this->expenses->sum('amount');
        }

        return (float) $this->expenses()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->planned_amount - $this->expense_amount;
    }
}
