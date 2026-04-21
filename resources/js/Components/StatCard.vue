<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    color: { type: String, default: 'blue' }, // blue | green | red | yellow
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 flex flex-col gap-1 border border-gray-100 dark:border-gray-700">
        <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ label }}</span>
        <span
            class="text-2xl font-bold"
            :class="{
                'text-blue-600':   color === 'blue',
                'text-green-600':  color === 'green',
                'text-red-600':    color === 'red',
                'text-yellow-600': color === 'yellow',
            }"
        >{{ value }}</span>
    </div>
</template>
