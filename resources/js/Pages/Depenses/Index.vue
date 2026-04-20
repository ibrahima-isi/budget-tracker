<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import AppBadge from '@/Components/AppBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useFormatMoney } from '@/composables/useFormatMoney';
import { useFlash } from '@/composables/useFlash';

const props = defineProps({
    depenses:   Object,
    budgets:    Array,
    categories: Array,
    filters:    Object,
});

const { format } = useFormatMoney();
const { success } = useFlash();

const filterBudget    = ref(props.filters.budget_id    ?? '');
const filterCategorie = ref(props.filters.categorie_id ?? '');

function applyFilters() {
    router.get(route('depenses.index'), {
        budget_id:    filterBudget.value    || undefined,
        categorie_id: filterCategorie.value || undefined,
    }, { preserveState: true, replace: true });
}

// Create
const showCreate = ref(false);
const form = useForm({ budget_id: '', categorie_id: '', libelle: '', montant: '', date_depense: new Date().toISOString().slice(0, 10), note: '' });

function submitCreate() {
    form.post(route('depenses.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

// Edit
const showEdit = ref(false);
const editForm = useForm({ budget_id: '', categorie_id: '', libelle: '', montant: '', date_depense: '', note: '' });
let editId = null;

function openEdit(d) {
    editId                  = d.id;
    editForm.budget_id      = d.budget_id;
    editForm.categorie_id   = d.categorie_id ?? '';
    editForm.libelle        = d.libelle;
    editForm.montant        = d.montant;
    editForm.date_depense   = d.date_depense?.slice(0, 10) ?? '';
    editForm.note           = d.note ?? '';
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('depenses.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteDepense(id) {
    if (confirm('Supprimer cette dépense ?')) {
        deleteForm.delete(route('depenses.destroy', id));
    }
}

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR');

function budgetLabel(b) {
    const mois = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    return (b.libelle ? b.libelle + ' — ' : '') + (b.type === 'mensuel' ? mois[b.mois] + ' ' : '') + b.annee;
}
</script>

<template>
    <Head title="Dépenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Dépenses</h2>
                <PrimaryButton @click="showCreate = true">+ Nouvelle dépense</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">
                <div v-if="success" class="rounded-lg bg-green-50 px-4 py-3 text-green-700 text-sm">{{ success }}</div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-4 flex flex-wrap gap-4 items-end">
                    <div>
                        <InputLabel value="Budget" />
                        <select v-model="filterBudget" @change="applyFilters" class="mt-1 block rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Tous les budgets</option>
                            <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Catégorie" />
                        <select v-model="filterCategorie" @change="applyFilters" class="mt-1 block rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Toutes les catégories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Libellé</th>
                                <th class="px-6 py-3 text-left">Catégorie</th>
                                <th class="px-6 py-3 text-left">Budget</th>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-right">Montant</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="!depenses.data.length">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">Aucune dépense trouvée.</td>
                            </tr>
                            <tr v-for="d in depenses.data" :key="d.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-gray-900">{{ d.libelle }}</td>
                                <td class="px-6 py-3">
                                    <AppBadge v-if="d.categorie" :label="d.categorie.nom" :couleur="d.categorie.couleur" />
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs">
                                    {{ d.budget ? budgetLabel(d.budget) : '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ formatDate(d.date_depense) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-red-600">{{ format(d.montant) }}</td>
                                <td class="px-6 py-3 text-right space-x-2">
                                    <button @click="openEdit(d)" class="text-yellow-600 hover:underline text-xs">Modifier</button>
                                    <button @click="deleteDepense(d.id)" class="text-red-600 hover:underline text-xs">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="depenses.last_page > 1" class="px-6 py-4 flex gap-2 border-t border-gray-100">
                        <Link
                            v-for="link in depenses.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="px-3 py-1 rounded text-sm border"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Create Modal -->
    <AppModal :show="showCreate" title="Nouvelle dépense" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel value="Budget" />
                <select v-model="form.budget_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Sélectionner —</option>
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                </select>
                <InputError :message="form.errors.budget_id" />
            </div>
            <div>
                <InputLabel value="Catégorie" />
                <select v-model="form.categorie_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Aucune —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                </select>
                <InputError :message="form.errors.categorie_id" />
            </div>
            <div>
                <InputLabel value="Libellé" />
                <TextInput v-model="form.libelle" class="mt-1 block w-full" />
                <InputError :message="form.errors.libelle" />
            </div>
            <div>
                <InputLabel value="Montant (XOF)" />
                <TextInput v-model="form.montant" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="form.errors.montant" />
            </div>
            <div>
                <InputLabel value="Date" />
                <TextInput v-model="form.date_depense" type="date" class="mt-1 block w-full" />
                <InputError :message="form.errors.date_depense" />
            </div>
            <div>
                <InputLabel value="Note (optionnel)" />
                <textarea v-model="form.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" title="Modifier la dépense" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel value="Budget" />
                <select v-model="editForm.budget_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                </select>
                <InputError :message="editForm.errors.budget_id" />
            </div>
            <div>
                <InputLabel value="Catégorie" />
                <select v-model="editForm.categorie_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Aucune —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                </select>
            </div>
            <div>
                <InputLabel value="Libellé" />
                <TextInput v-model="editForm.libelle" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.libelle" />
            </div>
            <div>
                <InputLabel value="Montant (XOF)" />
                <TextInput v-model="editForm.montant" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.montant" />
            </div>
            <div>
                <InputLabel value="Date" />
                <TextInput v-model="editForm.date_depense" type="date" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.date_depense" />
            </div>
            <div>
                <InputLabel value="Note (optionnel)" />
                <textarea v-model="editForm.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
