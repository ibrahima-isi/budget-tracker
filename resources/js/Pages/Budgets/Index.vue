<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useFormatMoney } from '@/composables/useFormatMoney';
import { useCurrency } from '@/composables/useCurrency';
import { useFlash } from '@/composables/useFlash';
import { useLocale } from '@/composables/useLocale';

const props = defineProps({ budgets: Object, categories: Array });

const { t } = useI18n();
const { format } = useFormatMoney();
const { currencies, currentCode } = useCurrency();
const { success } = useFlash();
const { moisCourts } = useLocale();

// Create
const showCreate = ref(false);
const form = useForm({ type: 'mensuel', mois: new Date().getMonth() + 1, annee: new Date().getFullYear(), montant_prevu: '', libelle: '', categorie_id: null, currency_code: currentCode.value });

function submitCreate() {
    form.post(route('budgets.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

// Edit
const showEdit = ref(false);
const editForm = useForm({ type: 'mensuel', mois: null, annee: new Date().getFullYear(), montant_prevu: '', libelle: '', categorie_id: null, currency_code: currentCode.value });
let editId = null;

function openEdit(budget) {
    editId                    = budget.id;
    editForm.type             = budget.type;
    editForm.mois             = budget.mois;
    editForm.annee            = budget.annee;
    editForm.montant_prevu    = budget.montant_prevu;
    editForm.libelle          = budget.libelle ?? '';
    editForm.categorie_id     = budget.categorie_id ?? null;
    editForm.currency_code    = budget.currency_code ?? currentCode.value;
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('budgets.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteBudget(id) {
    if (confirm(t('budgets.confirmDelete'))) {
        deleteForm.delete(route('budgets.destroy', id));
    }
}
</script>

<template>
    <Head :title="$t('budgets.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('budgets.title') }}</h2>
                <PrimaryButton @click="showCreate = true">{{ $t('budgets.new') }}</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="success" class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">{{ success }}</div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ $t('budgets.typePeriod') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.label') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.category') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('budgets.planned') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('budgets.spent') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('budgets.balance') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!budgets.data.length">
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">{{ $t('budgets.noData') }}</td>
                            </tr>
                            <tr v-for="b in budgets.data" :key="b.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <span class="font-medium text-gray-900 dark:text-gray-100 capitalize">{{ b.type }}</span>
                                    <span class="ml-2 text-gray-500 dark:text-gray-400">
                                        {{ b.type === 'mensuel' ? moisCourts[b.mois] + ' ' : '' }}{{ b.annee }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ b.libelle ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span
                                        v-if="b.categorie"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                        :style="{ backgroundColor: b.categorie.couleur }"
                                    >{{ b.categorie.nom }}</span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-800 dark:text-gray-200 font-medium">{{ format(b.montant_prevu) }}</td>
                                <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">{{ format(b.montant_depense) }}</td>
                                <td class="px-6 py-3 text-right font-semibold" :class="b.solde >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ format(b.solde) }}</td>
                                <td class="px-6 py-3 text-right space-x-2">
                                    <Link :href="route('budgets.show', b.id)" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">{{ $t('common.detail') }}</Link>
                                    <button @click="openEdit(b)" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">{{ $t('common.edit') }}</button>
                                    <button @click="deleteBudget(b.id)" class="text-red-600 dark:text-red-400 hover:underline text-xs">{{ $t('common.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <div v-if="budgets.last_page > 1" class="px-6 py-4 flex gap-2 border-t border-gray-100 dark:border-gray-700">
                        <template v-for="link in budgets.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                class="px-3 py-1 rounded text-sm border"
                                :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 rounded text-sm border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-default"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Create Modal -->
    <AppModal :show="showCreate" :title="$t('budgets.createTitle')" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="form.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="form.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.type')" />
                <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="mensuel">{{ $t('budgets.monthly') }}</option>
                    <option value="annuel">{{ $t('budgets.annual') }}</option>
                </select>
            </div>
            <div v-if="form.type === 'mensuel'">
                <InputLabel :value="$t('budgets.month')" />
                <select v-model="form.mois" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option v-for="(m, i) in moisCourts.slice(1)" :key="i+1" :value="i+1">{{ m }}</option>
                </select>
                <InputError :message="form.errors.mois" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.year')" />
                <TextInput v-model="form.annee" type="number" class="mt-1 block w-full" />
                <InputError :message="form.errors.annee" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.plannedAmount')" />
                <TextInput v-model="form.montant_prevu" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="form.errors.montant_prevu" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.labelOptional')" />
                <TextInput v-model="form.libelle" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel :value="$t('common.category')" />
                <select v-model="form.categorie_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option :value="null" disabled>{{ $t('common.select') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                </select>
                <InputError :message="form.errors.categorie_id" />
            </div>
            <div v-if="form.errors.periode" class="rounded-md bg-red-50 dark:bg-red-900/30 px-3 py-2 text-sm text-red-700 dark:text-red-400">
                {{ form.errors.periode }}
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="form.processing">{{ $t('common.create') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" :title="$t('budgets.editTitle')" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="editForm.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="editForm.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.type')" />
                <select v-model="editForm.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="mensuel">{{ $t('budgets.monthly') }}</option>
                    <option value="annuel">{{ $t('budgets.annual') }}</option>
                </select>
            </div>
            <div v-if="editForm.type === 'mensuel'">
                <InputLabel :value="$t('budgets.month')" />
                <select v-model="editForm.mois" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option v-for="(m, i) in moisCourts.slice(1)" :key="i+1" :value="i+1">{{ m }}</option>
                </select>
            </div>
            <div>
                <InputLabel :value="$t('budgets.year')" />
                <TextInput v-model="editForm.annee" type="number" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.plannedAmount')" />
                <TextInput v-model="editForm.montant_prevu" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.montant_prevu" />
            </div>
            <div>
                <InputLabel :value="$t('budgets.labelOptional')" />
                <TextInput v-model="editForm.libelle" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel :value="$t('common.category')" />
                <select v-model="editForm.categorie_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option :value="null" disabled>{{ $t('common.select') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                </select>
                <InputError :message="editForm.errors.categorie_id" />
            </div>
            <div v-if="editForm.errors.periode" class="rounded-md bg-red-50 dark:bg-red-900/30 px-3 py-2 text-sm text-red-700 dark:text-red-400">
                {{ editForm.errors.periode }}
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">{{ $t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
