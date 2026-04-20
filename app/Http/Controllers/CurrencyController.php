<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper($request->input('code', ''))]);

        $request->validate([
            'code'   => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'name'   => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        Currency::create([
            'code'       => $request->code,
            'name'       => $request->name,
            'symbol'     => $request->symbol,
            'is_default' => false,
            'is_active'  => true,
        ]);

        return redirect()->route('settings.index')->with('success', 'Devise ajoutée.');
    }

    public function update(Request $request, Currency $currency)
    {
        $request->merge(['code' => strtoupper($request->input('code', ''))]);

        $request->validate([
            'code'   => ['required', 'string', 'max:10', 'unique:currencies,code,' . $currency->id],
            'name'   => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        $currency->update([
            'code'   => $request->code,
            'name'   => $request->name,
            'symbol' => $request->symbol,
        ]);

        return redirect()->route('settings.index')->with('success', 'Devise mise à jour.');
    }

    public function setDefault(Currency $currency)
    {
        Currency::where('is_default', true)->update(['is_default' => false]);
        $currency->update(['is_default' => true]);

        return redirect()->route('settings.index')->with('success', 'Devise par défaut mise à jour.');
    }

    public function toggle(Currency $currency)
    {
        $currency->update(['is_active' => ! $currency->is_active]);

        return redirect()->route('settings.index')->with('success', 'Devise mise à jour.');
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_default) {
            return redirect()->route('settings.index')->with('error', 'Impossible de supprimer la devise par défaut.');
        }

        $currency->delete();

        return redirect()->route('settings.index')->with('success', 'Devise supprimée.');
    }
}
