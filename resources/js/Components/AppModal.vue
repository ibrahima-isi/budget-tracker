<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show:     { type: Boolean, default: false },
    title:    { type: String,  default: '' },
    maxWidth: { type: String,  default: '2xl' },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="emit('close')">
        <div class="p-6">
            <h2 v-if="title" class="text-lg font-semibold text-gray-900 mb-4">{{ title }}</h2>
            <slot />
        </div>
    </Modal>
</template>
