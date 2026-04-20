<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'business_name',
        'business_email',
        'phone',
        'logo_path',
        'language',
        'default_currency',
    ];

    /** Always return the single settings row, creating it if absent. */
    public static function instance(): self
    {
        return self::firstOrCreate([], [
            'business_name'    => 'Mon Entreprise',
            'language'         => 'fr',
            'default_currency' => 'XOF',
        ]);
    }
}
