<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import AppBadge from '@/Components/AppBadge.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useFormatMoney } from '@/composables/useFormatMoney';
import { useFlash } from '@/composables/useFlash';
import { useLocale } from '@/composables/useLocale';

const props = defineProps({ budget: Object, categories: Array });

const { t } = useI18n();
const { format } = useFormatMoney();
const { success } = useFlash();
const { moisCourts, formatDate } = useLocale();

const showAdd = ref(false);
const form = useForm({
    budget_id:   props.budget.id,
    category_id: '',
    label:       '',
    amount:      '',
    expense_date: new Date().toISOString().slice(0, 10),
    note:        '',
});

function submitAdd() {
    form.post(route('expenses.store'), {
        onSuccess: () => {
            showAdd.value = false;
            form.label = '';
            form.amount = '';
            form.note = '';
            form.category_id = '';
        },
    });
}

const deleteForm = useForm({});
function deleteExpense(id) {
    if (confirm(t('expenses.confirmDelete'))) {
        deleteForm.delete(route('expenses.destroy', id));
    }
}
</script>

<template>
    <Head :title="`Budget — ${budget.label ?? budget.type}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('budgets.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">← {{ $t('budgets.title') }}</Link>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mt-1 capitalize">
                        Budget {{ budget.type }}
                        {{ budget.type === 'mensuel' ? moisCourts[budget.month] + ' ' : '' }}{{ budget.year }}
                        <span v-if="budget.label" class="text-gray-500 font-normal text-base">— {{ budget.label }}</span>
                    </h2>
                </div>
                <PrimaryButton @click="showAdd = true">{{ $t('expenses.new') }}</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <div v-if="success" class="rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">{{ success }}</div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <BudgetProgress :prevu="budget.planned_amount" :depense="budget.expense_amount" />
                    <div class="mt-4 grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('budgets.planned') }}</p>
                            <p class="font-bold text-gray-800 dark:text-gray-100">{{ format(budget.planned_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('budgets.spent') }}</p>
                            <p class="font-bold text-red-600 dark:text-red-400">{{ format(budget.expense_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('budgets.balance') }}</p>
                            <p class="font-bold" :class="budget.balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ format(budget.balance) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ $t('common.label') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.category') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.date') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.amount') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!budget.expenses.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">{{ $t('budgets.noExpenses') }}</td>
                            </tr>
                            <tr v-for="d in budget.expenses" :key="d.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ d.label }}</td>
                                <td class="px-6 py-3">
                                    <AppBadge v-if="d.category" :label="d.category.name" :couleur="d.category.color" />
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(d.expense_date) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-red-600 dark:text-red-400">{{ format(d.amount) }}</td>
                                <td class="px-6 py-3 text-right">
                                    <button @click="deleteExpense(d.id)" class="text-red-600 dark:text-red-400 hover:underline text-xs">{{ $t('common.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <AppModal :show="showAdd" :title="$t('expenses.createTitle')" @close="showAdd = false">
        <form @submit.prevent="submitAdd" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.label')" />
                <TextInput v-model="form.label" class="mt-1 block w-full" />
                <InputError :message="form.errors.label" />
            </div>
            <div>
                <InputLabel :value="`${$t('common.category')} ${$t('common.optional')}`" />
                <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">{{ $t('common.none') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError :message="form.errors.category_id" />
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
                <textarea v-model="form.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showAdd = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="form.processing">{{ $t('common.add') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
