<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenuRequest;
use App\Http\Requests\UpdateRevenuRequest;
use App\Models\Revenu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RevenuController extends Controller
{
    public function index()
    {
        $revenus = Revenu::where('user_id', Auth::id())
            ->latest('date_revenu')
            ->paginate(20);

        return Inertia::render('Revenus/Index', [
            'revenus' => $revenus,
        ]);
    }

    public function store(StoreRevenuRequest $request)
    {
        $date = Carbon::parse($request->date_revenu);

        Revenu::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
            'mois'    => $date->month,
            'annee'   => $date->year,
        ]);

        return redirect()->back()->with('success', 'Revenu ajouté.');
    }

    public function update(UpdateRevenuRequest $request, Revenu $revenu)
    {
        $this->authorize('update', $revenu);

        $date = Carbon::parse($request->date_revenu);

        $revenu->update([
            ...$request->validated(),
            'mois'  => $date->month,
            'annee' => $date->year,
        ]);

        return redirect()->back()->with('success', 'Revenu mis à jour.');
    }

    public function destroy(Revenu $revenu)
    {
        $this->authorize('delete', $revenu);

        $revenu->delete();

        return redirect()->back()->with('success', 'Revenu supprimé.');
    }
}
