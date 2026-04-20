<script setup>
defineProps({
    label:  { type: String, required: true },
    couleur: { type: String, default: '#6b7280' },
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
        :style="{ backgroundColor: couleur }"
    >
        {{ label }}
    </span>
</template>
