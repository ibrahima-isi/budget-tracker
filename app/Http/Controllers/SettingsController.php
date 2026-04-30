<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::instance(),
            'currencies' => Currency::orderedOptions(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'business_email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'language' => ['required', 'in:fr,en,es'],
            'default_currency' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = Setting::instance();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('local')->delete($settings->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('logos', 'local');
        }

        unset($validated['logo']);
        $settings->update($validated);

        return redirect()->route('settings.index')->with('success', 'Paramètres mis à jour.');
    }

    public function destroyLogo()
    {
        $settings = Setting::instance();
        if ($settings->logo_path) {
            Storage::disk('local')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return redirect()->route('settings.index')->with('success', 'Logo supprimé.');
    }
}
