<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const appSettings = computed(() => usePage().props.appSettings);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
        <div>
            <Link href="/">
                <img
                    v-if="appSettings?.logo_url"
                    :src="appSettings.logo_url"
                    :alt="appSettings.business_name"
                    class="h-20 w-auto object-contain"
                />
                <span
                    v-else
                    class="text-2xl font-bold text-gray-800"
                >{{ appSettings?.business_name ?? 'BudgetTrack' }}</span>
            </Link>
        </div>

        <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
