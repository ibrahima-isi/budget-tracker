<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Depense;
use App\Models\Revenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Period: query params override, fall back to current month/year
        $mois  = $request->query('mois')  ? (int) $request->query('mois')  : now()->month;
        $annee = $request->query('annee') ? (int) $request->query('annee') : now()->year;

        // Currency: 'all' = no filter, '' / null = session default
        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        // Closure to apply currency filter conditionally
        $c = fn ($q) => $currency !== 'all' ? $q->where('currency_code', $currency) : $q;

        // ── Monthly ──────────────────────────────────────────────────────────
        $budgetMensuel = $c(Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee))
            ->first();

        $totalBudgetMensuel = $c(Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee))
            ->sum('montant_prevu');

        $totalDepensesMensuel = $c(Depense::where('user_id', $user->id)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee))
            ->sum('montant');

        $totalRevenusMensuel = $c(Revenu::where('user_id', $user->id)
            ->where('mois', $mois)
            ->where('annee', $annee))
            ->sum('montant');

        $depensesParCategorieMensuel = $c(Depense::where('user_id', $user->id)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee))
            ->selectRaw('categorie_id, SUM(montant) as total')
            ->groupBy('categorie_id')
            ->with('categorie:id,nom,couleur')
            ->get()
            ->map(fn ($item) => [
                'categorie' => $item->categorie,
                'total'     => (float) $item->total,
            ])
            ->values();

        // ── Annual ───────────────────────────────────────────────────────────
        $totalBudgetMensualise = $c(Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('annee', $annee))
            ->sum('montant_prevu');

        $totalBudgetAnnuelType = $c(Budget::where('user_id', $user->id)
            ->where('type', 'annuel')
            ->where('annee', $annee))
            ->sum('montant_prevu');

        $totalDepensesAnnuel = $c(Depense::where('user_id', $user->id)
            ->whereYear('date_depense', $annee))
            ->sum('montant');

        $totalRevenusAnnuel = $c(Revenu::where('user_id', $user->id)
            ->where('annee', $annee))
            ->sum('montant');

        $depensesParCategorieAnnuel = $c(Depense::where('user_id', $user->id)
            ->whereYear('date_depense', $annee))
            ->selectRaw('categorie_id, SUM(montant) as total')
            ->groupBy('categorie_id')
            ->with('categorie:id,nom,couleur')
            ->get()
            ->map(fn ($item) => [
                'categorie' => $item->categorie,
                'total'     => (float) $item->total,
            ])
            ->values();

        // ── Recent expenses (filtered by period & currency) ──────────────────
        $dernieresDepenses = $c(Depense::where('user_id', $user->id))
            ->with('categorie:id,nom,couleur')
            ->latest('date_depense')
            ->take(5)
            ->get();

        $solde = (float) $totalRevenusMensuel - (float) $totalDepensesMensuel;

        return Inertia::render('Dashboard', [
            // Flat props (tested contract)
            'budgetMensuel'        => $budgetMensuel,
            'totalDepenses'        => (float) $totalDepensesMensuel,
            'totalRevenus'         => (float) $totalRevenusMensuel,
            'solde'                => $solde,
            'depensesParCategorie' => $depensesParCategorieMensuel,
            'dernieresDepenses'    => $dernieresDepenses,
            'mois'                 => $mois,
            'annee'                => $annee,
            // Nested props for the Vue dashboard (both periods)
            'mensuel' => [
                'totalBudget'          => (float) $totalBudgetMensuel,
                'totalDepenses'        => (float) $totalDepensesMensuel,
                'totalRevenus'         => (float) $totalRevenusMensuel,
                'solde'                => $solde,
                'depensesParCategorie' => $depensesParCategorieMensuel,
            ],
            'annuel' => [
                'totalBudget'           => (float) $totalBudgetMensualise + (float) $totalBudgetAnnuelType,
                'totalBudgetMensualise' => (float) $totalBudgetMensualise,
                'totalBudgetAnnuelType' => (float) $totalBudgetAnnuelType,
                'totalDepenses'         => (float) $totalDepensesAnnuel,
                'totalRevenus'          => (float) $totalRevenusAnnuel,
                'solde'                 => (float) $totalRevenusAnnuel - (float) $totalDepensesAnnuel,
                'depensesParCategorie'  => $depensesParCategorieAnnuel,
            ],
            'filters' => [
                'mois'     => $mois,
                'annee'    => $annee,
                'currency' => $currency,
            ],
        ]);
    }
}
