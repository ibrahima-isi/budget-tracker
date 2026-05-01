<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal     from '@/Components/AppModal.vue';
import AppBadge     from '@/Components/AppBadge.vue';
import PeriodFilter from '@/Components/PeriodFilter.vue';
import PrimaryButton   from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput  from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import { useFlash }    from '@/composables/useFlash';
import { useLocale }   from '@/composables/useLocale';

const props = defineProps({
    expenses:    Object,
    totalAmount: Number,
    budgets:     Array,
    categories:  Array,
    filters:     Object,
});

const { t } = useI18n();
const { format, formatWithCode, currencies, currentCode } = useCurrency();
const { success } = useFlash();
const { moisCourts, formatDate } = useLocale();

const isAllCurrencies = computed(() => props.filters?.currency === 'all');

const filterBudget   = ref(props.filters.budget_id    ?? '');
const filterCategory = ref(props.filters.category_id  ?? '');

function applyFilters(periodFilters = {}) {
    router.get(route('expenses.index'), {
        budget_id:   filterBudget.value   || undefined,
        category_id: filterCategory.value || undefined,
        month:       periodFilters.mois     ?? props.filters?.month    ?? undefined,
        year:        periodFilters.annee    ?? props.filters?.year     ?? undefined,
        currency:    periodFilters.currency ?? props.filters?.currency ?? undefined,
    }, { preserveState: false, replace: true });
}

function applyEntityFilters() {
    applyFilters();
}

const showCreate = ref(false);
const form = useForm({ budget_id: '', category_id: '', label: '', amount: '', expense_date: new Date().toISOString().slice(0, 10), note: '', currency_code: currentCode.value });

function submitCreate() {
    form.post(route('expenses.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

const showEdit = ref(false);
const editForm = useForm({ budget_id: '', category_id: '', label: '', amount: '', expense_date: '', note: '', currency_code: currentCode.value });
let editId = null;

function openEdit(d) {
    editId                 = d.id;
    editForm.budget_id     = d.budget_id;
    editForm.category_id   = d.category_id ?? '';
    editForm.label         = d.label;
    editForm.amount        = d.amount;
    editForm.expense_date  = d.expense_date?.slice(0, 10) ?? '';
    editForm.note          = d.note ?? '';
    editForm.currency_code = d.currency_code ?? currentCode.value;
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('expenses.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteExpense(id) {
    if (confirm(t('expenses.confirmDelete'))) {
        deleteForm.delete(route('expenses.destroy', id));
    }
}

function budgetLabel(b) {
    return (b.label ? b.label + ' — ' : '') + (b.type === 'mensuel' ? moisCourts.value[b.month] + ' ' : '') + b.year;
}
</script>

<template>
    <Head :title="$t('expenses.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('expenses.title') }}</h2>
                    <div class="hidden sm:block h-6 w-px bg-gray-300 dark:bg-gray-700"></div>
                    <div class="text-lg font-bold text-red-600 dark:text-red-400">
                        {{ isAllCurrencies ? formatWithCode(totalAmount, filters.currency) : format(totalAmount) }}
                    </div>
                </div>
                <PrimaryButton @click="showCreate = true">{{ $t('expenses.new') }}</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">
                <div v-if="success" class="rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">{{ success }}</div>

                <!-- Period / currency filter -->
                <PeriodFilter
                    :mois="filters?.month"
                    :annee="filters?.year"
                    :currency="filters?.currency"
                    @change="applyFilters"
                />

                <!-- Entity filters (budget + category) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 sm:px-6 py-4 flex flex-col sm:flex-row flex-wrap gap-4 items-start sm:items-end">
                    <div class="w-full sm:w-auto">
                        <InputLabel :value="$t('expenses.budget')" />
                        <select v-model="filterBudget" @change="applyEntityFilters" class="mt-1 block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                            <option value="">{{ $t('expenses.allBudgets') }}</option>
                            <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-auto">
                        <InputLabel :value="$t('common.category')" />
                        <select v-model="filterCategory" @change="applyEntityFilters" class="mt-1 block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                            <option value="">{{ $t('expenses.allCategories') }}</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ $t('common.label') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.category') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('expenses.budget') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.date') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.amount') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!expenses.data.length">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">{{ $t('expenses.noData') }}</td>
                            </tr>
                            <tr v-for="d in expenses.data" :key="d.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ d.label }}</td>
                                <td class="px-6 py-3">
                                    <AppBadge v-if="d.category" :label="d.category.name" :couleur="d.category.color" />
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                    {{ d.budget ? budgetLabel(d.budget) : '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(d.expense_date) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-red-600 dark:text-red-400">{{ isAllCurrencies ? formatWithCode(d.amount, d.currency_code) : format(d.amount) }}</td>
                                <td class="px-6 py-3 text-right space-x-2">
                                    <button @click="openEdit(d)" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">{{ $t('common.edit') }}</button>
                                    <button @click="deleteExpense(d.id)" class="text-red-600 dark:text-red-400 hover:underline text-xs">{{ $t('common.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <div v-if="expenses.last_page > 1" class="px-6 py-4 flex gap-2 border-t border-gray-100 dark:border-gray-700">
                        <template v-for="link in expenses.links" :key="link.label">
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
    <AppModal :show="showCreate" :title="$t('expenses.createTitle')" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="form.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="form.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('expenses.budget')" />
                <select v-model="form.budget_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option value="">{{ $t('common.select') }}</option>
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                </select>
                <InputError :message="form.errors.budget_id" />
            </div>
            <div>
                <InputLabel :value="$t('common.category')" />
                <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option value="">{{ $t('common.none') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError :message="form.errors.category_id" />
            </div>
            <div>
                <InputLabel :value="$t('common.label')" />
                <TextInput v-model="form.label" class="mt-1 block w-full" />
                <InputError :message="form.errors.label" />
            </div>
            <div>
                <InputLabel :value="$t('expenses.amountLabel')" />
                <TextInput v-model="form.amount" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="form.errors.amount" />
            </div>
            <div>
                <InputLabel :value="$t('common.date')" />
                <TextInput v-model="form.expense_date" type="date" class="mt-1 block w-full" />
                <InputError :message="form.errors.expense_date" />
            </div>
            <div>
                <InputLabel :value="$t('common.note')" />
                <textarea v-model="form.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="form.processing">{{ $t('common.add') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" :title="$t('expenses.editTitle')" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="editForm.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="editForm.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('expenses.budget')" />
                <select v-model="editForm.budget_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ budgetLabel(b) }}</option>
                </select>
                <InputError :message="editForm.errors.budget_id" />
            </div>
            <div>
                <InputLabel :value="$t('common.category')" />
                <select v-model="editForm.category_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option value="">{{ $t('common.none') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError :message="editForm.errors.category_id" />
            </div>
            <div>
                <InputLabel :value="$t('common.label')" />
                <TextInput v-model="editForm.label" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.label" />
            </div>
            <div>
                <InputLabel :value="$t('expenses.amountLabel')" />
                <TextInput v-model="editForm.amount" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.amount" />
            </div>
            <div>
                <InputLabel :value="$t('common.date')" />
                <TextInput v-model="editForm.expense_date" type="date" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.expense_date" />
            </div>
            <div>
                <InputLabel :value="$t('common.note')" />
                <textarea v-model="editForm.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">{{ $t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
