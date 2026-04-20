<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Depense;
use App\Models\Revenu;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $mois = now()->month;
        $annee = now()->year;

        $budgetMensuel = Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->with('depenses')
            ->first();

        $totalDepenses = Depense::where('user_id', $user->id)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee)
            ->sum('montant');

        $totalRevenus = Revenu::where('user_id', $user->id)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant');

        $depensesParCategorie = Depense::where('user_id', $user->id)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee)
            ->with('categorie:id,nom,couleur')
            ->get()
            ->groupBy('categorie_id')
            ->map(fn ($items) => [
                'categorie' => $items->first()->categorie,
                'total'     => $items->sum('montant'),
            ])
            ->values();

        $dernieresDepenses = Depense::where('user_id', $user->id)
            ->with('categorie:id,nom,couleur', 'budget:id,libelle,type,mois,annee')
            ->latest('date_depense')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'budgetMensuel'        => $budgetMensuel,
            'totalDepenses'        => (float) $totalDepenses,
            'totalRevenus'         => (float) $totalRevenus,
            'solde'                => (float) $totalRevenus - (float) $totalDepenses,
            'depensesParCategorie' => $depensesParCategorie,
            'dernieresDepenses'    => $dernieresDepenses,
            'mois'                 => $mois,
            'annee'                => $annee,
        ]);
    }
}
