<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    label:        { type: String, required: true },
    labelAnnuel:  { type: String, default: null },
    // Single-value mode (no toggle)
    value:        { type: String, default: null },
    // Dual-value mode (with toggle)
    valueMensuel: { type: String, default: null },
    valueAnnuel:  { type: String, default: null },
    // Colors — colorAnnuel overrides color when in annuel mode
    color:        { type: String, default: 'blue' },
    colorAnnuel:  { type: String, default: null },
    // External sync (from global toggle)
    periode:      { type: String, default: null },
});

const emit = defineEmits(['update:periode']);

const hasToggle = computed(() => props.valueMensuel !== null && props.valueAnnuel !== null);

const local = ref(props.periode ?? 'mensuel');
watch(() => props.periode, v => { if (v != null) local.value = v; });

const displayValue = computed(() =>
    hasToggle.value
        ? (local.value === 'mensuel' ? props.valueMensuel : props.valueAnnuel)
        : props.value
);

const displayLabel = computed(() =>
    local.value === 'annuel' && props.labelAnnuel ? props.labelAnnuel : props.label
);

const displayColor = computed(() =>
    local.value === 'annuel' && props.colorAnnuel ? props.colorAnnuel : props.color
);

function setToggle(v) {
    local.value = v;
    emit('update:periode', v);
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 flex flex-col gap-1 border border-gray-100 dark:border-gray-700">
        <!-- Mini pricing-style toggle -->
        <div v-if="hasToggle" class="flex justify-end -mt-1 mb-0.5">
            <div class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 p-0.5">
                <button
                    type="button"
                    @click="setToggle('mensuel')"
                    :class="local === 'mensuel'
                        ? 'bg-white dark:bg-gray-600 shadow text-gray-900 dark:text-gray-100'
                        : 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300'"
                    class="px-2 py-0.5 rounded text-xs font-medium transition-all"
                >M</button>
                <button
                    type="button"
                    @click="setToggle('annuel')"
                    :class="local === 'annuel'
                        ? 'bg-white dark:bg-gray-600 shadow text-gray-900 dark:text-gray-100'
                        : 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300'"
                    class="px-2 py-0.5 rounded text-xs font-medium transition-all"
                >A</button>
            </div>
        </div>

        <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ displayLabel }}</span>
        <span
            class="text-2xl font-bold"
            :class="{
                'text-blue-600 dark:text-blue-400':    displayColor === 'blue',
                'text-green-600 dark:text-green-400':  displayColor === 'green',
                'text-red-600 dark:text-red-400':      displayColor === 'red',
                'text-yellow-600 dark:text-yellow-400': displayColor === 'yellow',
            }"
        >{{ displayValue }}</span>
    </div>
</template>
