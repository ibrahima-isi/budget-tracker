<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal     from '@/Components/AppModal.vue';
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

const props = defineProps({ revenues: Object, filters: Object });

const { t } = useI18n();
const { format, formatWithCode, currencies, currentCode } = useCurrency();
const { success } = useFlash();
const { moisCourts, formatDate } = useLocale();

const isAllCurrencies = computed(() => props.filters?.currency === 'all');

function applyFilters({ mois, annee, currency }) {
    router.get(route('revenues.index'), {
        month:    mois     ?? undefined,
        year:     annee    ?? undefined,
        currency: currency ?? undefined,
    }, { preserveState: false, replace: true });
}

const showCreate = ref(false);
const form = useForm({ source: '', amount: '', revenue_date: new Date().toISOString().slice(0, 10), note: '', currency_code: currentCode.value });

function submitCreate() {
    form.post(route('revenues.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

const showEdit = ref(false);
const editForm = useForm({ source: '', amount: '', revenue_date: '', note: '', currency_code: currentCode.value });
let editId = null;

function openEdit(r) {
    editId                  = r.id;
    editForm.source         = r.source;
    editForm.amount         = r.amount;
    editForm.revenue_date   = r.revenue_date?.slice(0, 10) ?? '';
    editForm.note           = r.note ?? '';
    editForm.currency_code  = r.currency_code ?? currentCode.value;
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('revenues.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteRevenue(id) {
    if (confirm(t('revenues.confirmDelete'))) {
        deleteForm.delete(route('revenues.destroy', id));
    }
}
</script>

<template>
    <Head :title="$t('revenues.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('revenues.title') }}</h2>
                <PrimaryButton @click="showCreate = true">{{ $t('revenues.new') }}</PrimaryButton>
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

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ $t('revenues.source') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('revenues.period') }}</th>
                                <th class="px-6 py-3 text-left">{{ $t('common.date') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.amount') }}</th>
                                <th class="px-6 py-3 text-right">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!revenues.data.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">{{ $t('revenues.noData') }}</td>
                            </tr>
                            <tr v-for="r in revenues.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ r.source }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ moisCourts[r.month] }} {{ r.year }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(r.revenue_date) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-green-600 dark:text-green-400">{{ isAllCurrencies ? formatWithCode(r.amount, r.currency_code) : format(r.amount) }}</td>
                                <td class="px-6 py-3 text-right space-x-2">
                                    <button @click="openEdit(r)" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">{{ $t('common.edit') }}</button>
                                    <button @click="deleteRevenue(r.id)" class="text-red-600 dark:text-red-400 hover:underline text-xs">{{ $t('common.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <div v-if="revenues.last_page > 1" class="px-6 py-4 flex gap-2 border-t border-gray-100 dark:border-gray-700">
                        <template v-for="link in revenues.links" :key="link.label">
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
    <AppModal :show="showCreate" :title="$t('revenues.createTitle')" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="form.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="form.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('revenues.source')" />
                <TextInput v-model="form.source" :placeholder="$t('revenues.sourcePlaceholder')" class="mt-1 block w-full" />
                <InputError :message="form.errors.source" />
            </div>
            <div>
                <InputLabel :value="$t('revenues.amountLabel')" />
                <TextInput v-model="form.amount" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="form.errors.amount" />
            </div>
            <div>
                <InputLabel :value="$t('common.date')" />
                <TextInput v-model="form.revenue_date" type="date" class="mt-1 block w-full" />
                <InputError :message="form.errors.revenue_date" />
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
    <AppModal :show="showEdit" :title="$t('revenues.editTitle')" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel :value="$t('common.currency')" />
                <select v-model="editForm.currency_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm">
                    <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                </select>
                <InputError :message="editForm.errors.currency_code" />
            </div>
            <div>
                <InputLabel :value="$t('revenues.source')" />
                <TextInput v-model="editForm.source" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.source" />
            </div>
            <div>
                <InputLabel :value="$t('revenues.amountLabel')" />
                <TextInput v-model="editForm.amount" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.amount" />
            </div>
            <div>
                <InputLabel :value="$t('common.date')" />
                <TextInput v-model="editForm.revenue_date" type="date" class="mt-1 block w-full" />
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
