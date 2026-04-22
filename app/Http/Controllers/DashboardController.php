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
        $user     = Auth::user();
        $mois     = now()->month;
        $annee    = now()->year;
        $currency = $this->currentCurrency();

        // ── Monthly ──────────────────────────────────────────────────────────
        $budgetMensuel = Budget::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->first();

        $totalBudgetMensuel = Budget::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalDepensesMensuel = Depense::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee)
            ->sum('montant');

        $totalRevenusMensuel = Revenu::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant');

        $depensesParCategorieMensuel = Depense::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee)
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
        $totalBudgetMensualise = Budget::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('type', 'mensuel')
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalBudgetAnnuelType = Budget::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('type', 'annuel')
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalDepensesAnnuel = Depense::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->whereYear('date_depense', $annee)
            ->sum('montant');

        $totalRevenusAnnuel = Revenu::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->where('annee', $annee)
            ->sum('montant');

        $depensesParCategorieAnnuel = Depense::where('user_id', $user->id)
            ->where('currency_code', $currency)
            ->whereYear('date_depense', $annee)
            ->selectRaw('categorie_id, SUM(montant) as total')
            ->groupBy('categorie_id')
            ->with('categorie:id,nom,couleur')
            ->get()
            ->map(fn ($item) => [
                'categorie' => $item->categorie,
                'total'     => (float) $item->total,
            ])
            ->values();

        // ── Recent expenses (period-independent) ─────────────────────────────
        $dernieresDepenses = Depense::where('user_id', $user->id)
            ->where('currency_code', $currency)
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
        ]);
    }
}
