<?php

namespace App\Http\Controllers;

use App\Models\Setting;

abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Return the currency code the current user has selected (from session),
     * falling back to the app default from Settings.
     */
    protected function currentCurrency(): string
    {
        return session('current_currency')
            ?: (Setting::instance()->default_currency ?? 'XOF');
    }
}
