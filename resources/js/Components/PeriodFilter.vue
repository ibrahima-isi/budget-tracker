<script setup>
import { ref, watch } from 'vue';
import { useLocale }   from '@/composables/useLocale';
import { useCurrency } from '@/composables/useCurrency';
import { useI18n }     from 'vue-i18n';

const props = defineProps({
    mois:      { type: Number, default: null },
    annee:     { type: Number, default: null },
    currency:  { type: String, default: '' },
    showMonth: { type: Boolean, default: true },
});

const emit = defineEmits(['change']);

const { t }         = useI18n();
const { moisLongs } = useLocale();
const { currencies } = useCurrency();

// Year range: 5 years back to 1 year forward
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 7 }, (_, i) => currentYear - 5 + i);

const localMois     = ref(props.mois     ?? '');
const localAnnee    = ref(props.annee    ?? '');
const localCurrency = ref(props.currency ?? '');

watch([localMois, localAnnee, localCurrency], () => {
    emit('change', {
        mois:     localMois.value     || undefined,
        annee:    localAnnee.value    || undefined,
        currency: localCurrency.value || undefined,
    });
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 sm:px-6 py-4 flex flex-col sm:flex-row flex-wrap gap-4 items-start sm:items-end">
        <!-- Month -->
        <div v-if="showMonth" class="w-full sm:w-auto">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.month') }}</label>
            <select
                v-model="localMois"
                class="block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm"
            >
                <option :value="0">{{ $t('common.allMonths') }}</option>
                <option v-for="(name, i) in moisLongs.slice(1)" :key="i + 1" :value="i + 1">
                    {{ name }}
                </option>
            </select>
        </div>

        <!-- Year -->
        <div class="w-full sm:w-auto">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.year') }}</label>
            <select
                v-model="localAnnee"
                class="block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm"
            >
                <option :value="0">{{ $t('common.allYears') }}</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>

        <!-- Currency -->
        <div class="w-full sm:w-auto">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('common.currency') }}</label>
            <select
                v-model="localCurrency"
                class="block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm"
            >
                <option value="all">{{ $t('common.allCurrencies') }}</option>
                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
            </select>
        </div>
    </div>
</template>
