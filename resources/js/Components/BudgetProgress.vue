<script setup>
import { computed } from 'vue';
import { useFormatMoney } from '@/composables/useFormatMoney';

const props = defineProps({
    prevu:   { type: Number, required: true },
    depense: { type: Number, required: true },
});

const { format } = useFormatMoney();

const pct = computed(() => {
    if (!props.prevu) return 0;
    return Math.min(Math.round((props.depense / props.prevu) * 100), 100);
});

const barColor = computed(() => {
    if (pct.value >= 100) return 'bg-red-500';
    if (pct.value >= 80)  return 'bg-yellow-400';
    return 'bg-green-500';
});
</script>

<template>
    <div>
        <div class="flex justify-between text-sm mb-1">
            <span class="text-gray-600">{{ format(depense) }} dépensés</span>
            <span class="font-medium text-gray-800">{{ pct }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
            <div
                class="h-3 rounded-full transition-all duration-500"
                :class="barColor"
                :style="{ width: pct + '%' }"
            />
        </div>
        <p class="text-xs text-gray-500 mt-1">Budget prévu : {{ format(prevu) }}</p>
    </div>
</template>
