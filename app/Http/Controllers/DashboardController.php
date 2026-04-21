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
        $user  = Auth::user();
        $mois  = now()->month;
        $annee = now()->year;

        // ── Monthly ──────────────────────────────────────────────────────────
        $totalBudgetMensuel = Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalDepensesMensuel = Depense::where('user_id', $user->id)
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee)
            ->sum('montant');

        $totalRevenusMensuel = Revenu::where('user_id', $user->id)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant');

        $depensesParCategorieMensuel = Depense::where('user_id', $user->id)
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
        // Split monthly-cumulated vs annual-type so the UI can show a breakdown
        $totalBudgetMensualise = Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalBudgetAnnuelType = Budget::where('user_id', $user->id)
            ->where('type', 'annuel')
            ->where('annee', $annee)
            ->sum('montant_prevu');

        $totalDepensesAnnuel = Depense::where('user_id', $user->id)
            ->whereYear('date_depense', $annee)
            ->sum('montant');

        $totalRevenusAnnuel = Revenu::where('user_id', $user->id)
            ->where('annee', $annee)
            ->sum('montant');

        $depensesParCategorieAnnuel = Depense::where('user_id', $user->id)
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
            ->with('categorie:id,nom,couleur')
            ->latest('date_depense')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'mensuel' => [
                'totalBudget'          => (float) $totalBudgetMensuel,
                'totalDepenses'        => (float) $totalDepensesMensuel,
                'totalRevenus'         => (float) $totalRevenusMensuel,
                'solde'                => (float) $totalRevenusMensuel - (float) $totalDepensesMensuel,
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
            'dernieresDepenses' => $dernieresDepenses,
            'mois'              => $mois,
            'annee'             => $annee,
        ]);
    }
}
