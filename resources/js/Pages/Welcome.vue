<script setup>
import { Link } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';

defineProps({
    canLogin:    { type: Boolean },
    canRegister: { type: Boolean },
});
</script>

<template>
    <Head title="BudgetTracker - GUI CONNECT" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white flex flex-col">

        <!-- Navbar -->
        <nav class="flex items-center justify-between px-6 py-5 max-w-7xl mx-auto w-full">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-blue-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <span class="font-bold text-lg tracking-tight">BudgetTracker</span>
            </div>

            <div v-if="canLogin" class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-sm font-medium transition"
                >
                    Mon tableau de bord
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-blue-200 hover:text-white transition"
                    >
                        Connexion
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-sm font-medium transition"
                    >
                        Créer un compte
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Hero -->
        <section class="flex-1 flex flex-col items-center justify-center text-center px-6 py-20">
            <span class="inline-flex items-center gap-2 bg-blue-500/10 border border-blue-500/30 text-blue-300 text-xs font-medium px-3 py-1.5 rounded-full mb-6">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                Un produit GUI CONNECT
            </span>

            <h1 class="text-5xl sm:text-6xl font-extrabold tracking-tight max-w-3xl leading-tight mb-6">
                Budget Tracker
            </h1>

            <p class="text-lg text-slate-400 max-w-xl mb-10 leading-relaxed">
                Suivez vos dépenses, planifiez vos budgets et visualisez vos revenus avec l'application de gestion de budget personnel de GUI CONNECT.
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="px-7 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold text-white shadow-lg shadow-blue-900/40 transition text-sm"
                >
                    Commencer gratuitement →
                </Link>
                <Link
                    :href="route('login')"
                    class="px-7 py-3.5 rounded-xl border border-white/10 hover:border-white/25 hover:bg-white/5 font-medium text-slate-300 hover:text-white transition text-sm"
                >
                    Se connecter
                </Link>
            </div>
        </section>

        <!-- Feature cards -->
        <section class="max-w-5xl mx-auto w-full px-6 pb-20 grid grid-cols-1 sm:grid-cols-3 gap-4">

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/8 transition">
                <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-semibold text-white mb-2">Suivi des revenus</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Enregistrez toutes vos sources de revenus et visualisez votre situation financière mois par mois.</p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/8 transition">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <h3 class="font-semibold text-white mb-2">Contrôle des dépenses</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Catégorisez chaque dépense, filtrez par budget ou catégorie, et gardez une vue claire de vos sorties.</p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/8 transition">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="font-semibold text-white mb-2">Budgets intelligents</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Définissez des budgets mensuels ou annuels et suivez en temps réel votre avancement avec des indicateurs visuels.</p>
            </div>

        </section>

        <!-- Stats bar -->
        <div class="border-t border-white/5 bg-white/[0.02]">
            <div class="max-w-5xl mx-auto px-6 py-8 grid grid-cols-3 divide-x divide-white/10 text-center">
                <div class="px-4">
                    <p class="text-2xl font-bold text-white">XOF</p>
                    <p class="text-xs text-slate-500 mt-1">Franc CFA</p>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-bold text-white">100%</p>
                    <p class="text-xs text-slate-500 mt-1">Données privées</p>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-bold text-white">∞</p>
                    <p class="text-xs text-slate-500 mt-1">Catégories</p>
                </div>
            </div>
        </div>

    </div>
</template>
