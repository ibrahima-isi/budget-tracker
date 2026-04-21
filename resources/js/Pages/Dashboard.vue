<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import AppBadge from '@/Components/AppBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { useFormatMoney } from '@/composables/useFormatMoney';
import { useLocale } from '@/composables/useLocale';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    mensuel:           Object,
    annuel:            Object,
    dernieresDepenses: Array,
    mois:              Number,
    annee:             Number,
});

const { format } = useFormatMoney();
const { locale, formatDate } = useLocale();

// Persist toggle selection across navigations
const stored = localStorage.getItem('dashboard_periode');
const periode = ref(stored === 'annuel' ? 'annuel' : 'mensuel');
watch(periode, (val) => localStorage.setItem('dashboard_periode', val));

// Per-card periods (start synced with global, can diverge independently)
const periodeBudget   = ref(periode.value);
const periodeDepenses = ref(periode.value);
const periodeRevenus  = ref(periode.value);
const periodeSolde    = ref(periode.value);

// Global toggle pushes to all cards
watch(periode, v => {
    periodeBudget.value   = v;
    periodeDepenses.value = v;
    periodeRevenus.value  = v;
    periodeSolde.value    = v;
});

const current = computed(() => periode.value === 'mensuel' ? props.mensuel : props.annuel);

const periodeLabel = computed(() => {
    if (periode.value === 'mensuel') {
        return new Date(props.annee, props.mois - 1)
            .toLocaleString(locale.value, { month: 'long', year: 'numeric' });
    }
    return String(props.annee);
});

const chartData = computed(() => ({
    labels: current.value.depensesParCategorie.map(d => d.categorie?.nom ?? 'Sans catégorie'),
    datasets: [{
        data:            current.value.depensesParCategorie.map(d => d.total),
        backgroundColor: current.value.depensesParCategorie.map(d => d.categorie?.couleur ?? '#6b7280'),
        borderWidth: 2,
    }],
}));

// computed so dark-mode theming can be added here later
const chartOptions = computed(() => ({
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
}));
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100"
                    :class="{ capitalize: periode === 'mensuel' }"
                >
                    {{ periodeLabel }}
                </h2>

                <!-- Period toggle -->
                <div class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-gray-700 p-1">
                    <button
                        type="button"
                        @click="periode = 'mensuel'"
                        :class="periode === 'mensuel'
                            ? 'bg-white dark:bg-gray-600 shadow text-gray-900 dark:text-gray-100'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="px-4 py-1.5 rounded-md text-sm font-medium transition-all"
                    >Mensuel</button>
                    <button
                        type="button"
                        @click="periode = 'annuel'"
                        :class="periode === 'annuel'
                            ? 'bg-white dark:bg-gray-600 shadow text-gray-900 dark:text-gray-100'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="px-4 py-1.5 rounded-md text-sm font-medium transition-all"
                    >Annuel</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Stat Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Budget du mois"
                        labelAnnuel="Budgets planifiés"
                        :valueMensuel="mensuel.totalBudget > 0 ? format(mensuel.totalBudget) : '—'"
                        :valueAnnuel="annuel.totalBudget > 0 ? format(annuel.totalBudget) : '—'"
                        color="blue"
                        :periode="periodeBudget"
                        :href="route('budgets.index')"
                        @update:periode="v => periodeBudget = v"
                    />
                    <StatCard
                        label="Dépenses du mois"
                        labelAnnuel="Dépenses annuelles"
                        :valueMensuel="format(mensuel.totalDepenses)"
                        :valueAnnuel="format(annuel.totalDepenses)"
                        color="red"
                        :periode="periodeDepenses"
                        :href="route('depenses.index')"
                        @update:periode="v => periodeDepenses = v"
                    />
                    <StatCard
                        label="Revenus du mois"
                        labelAnnuel="Revenus annuels"
                        :valueMensuel="format(mensuel.totalRevenus)"
                        :valueAnnuel="format(annuel.totalRevenus)"
                        color="green"
                        :periode="periodeRevenus"
                        :href="route('revenus.index')"
                        @update:periode="v => periodeRevenus = v"
                    />
                    <StatCard
                        label="Solde"
                        :valueMensuel="format(mensuel.solde)"
                        :valueAnnuel="format(annuel.solde)"
                        :color="mensuel.solde >= 0 ? 'green' : 'red'"
                        :colorAnnuel="annuel.solde >= 0 ? 'green' : 'red'"
                        :periode="periodeSolde"
                        @update:periode="v => periodeSolde = v"
                    />
                </div>

                <!-- Budget Progress + Chart -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Link :href="route('budgets.index')" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800 transition-shadow block">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">
                            Avancement du budget {{ periode === 'mensuel' ? 'mensuel' : 'annuel' }}
                        </h3>
                        <BudgetProgress
                            v-if="current.totalBudget > 0"
                            :prevu="current.totalBudget"
                            :depense="current.totalDepenses"
                        />
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">
                            Aucun budget {{ periode === 'mensuel' ? 'pour ce mois' : 'pour cette année' }}.
                        </p>
                        <div v-if="current.totalBudget > 0" class="mt-3 space-y-1">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Solde :
                                <span
                                    class="font-semibold"
                                    :class="current.solde >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                >{{ format(current.solde) }}</span>
                            </div>
                            <!-- Annual breakdown: monthly-cumulated vs annual-type -->
                            <div v-if="periode === 'annuel'" class="text-xs text-gray-400 dark:text-gray-500 flex gap-3">
                                <span>Mensuel cumulé : {{ format(annuel.totalBudgetMensualise) }}</span>
                                <span>·</span>
                                <span>Annuel : {{ format(annuel.totalBudgetAnnuelType) }}</span>
                            </div>
                        </div>
                    </Link>

                    <Link :href="route('depenses.index')" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800 transition-shadow block">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Dépenses par catégorie</h3>
                        <div v-if="current.depensesParCategorie.length" class="max-w-xs mx-auto">
                            <Doughnut :data="chartData" :options="chartOptions" />
                        </div>
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Aucune dépense sur cette période.</p>
                    </Link>
                </div>

                <!-- Last 5 expenses -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">Dernières dépenses</h3>
                        <Link :href="route('depenses.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Voir tout</Link>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Libellé</th>
                                <th class="px-6 py-3 text-left">Catégorie</th>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!dernieresDepenses.length">
                                <td colspan="4" class="px-6 py-4 text-center text-gray-400 dark:text-gray-500">Aucune dépense.</td>
                            </tr>
                            <tr v-for="d in dernieresDepenses" :key="d.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ d.libelle }}</td>
                                <td class="px-6 py-3">
                                    <AppBadge v-if="d.categorie" :label="d.categorie.nom" :couleur="d.categorie.couleur" />
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(d.date_depense) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-red-600 dark:text-red-400">{{ format(d.montant) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
