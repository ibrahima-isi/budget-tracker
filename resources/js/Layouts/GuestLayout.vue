<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const appSettings = computed(() => usePage().props.appSettings);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gray-100 dark:bg-gray-900 pt-10 sm:justify-center sm:pt-0">
        <!-- Brand -->
        <div class="mb-6">
            <Link href="/">
                <img
                    v-if="appSettings?.logo_url"
                    :src="appSettings.logo_url"
                    :alt="appSettings.business_name"
                    class="h-20 w-auto object-contain"
                />
                <span
                    v-else
                    class="text-2xl font-bold text-gray-800 dark:text-gray-100"
                >{{ appSettings?.business_name ?? 'BudgetTrack' }}</span>
            </Link>
        </div>

        <!-- Card -->
        <div class="w-full overflow-hidden bg-white dark:bg-gray-800 px-8 py-8 shadow-lg sm:max-w-lg sm:rounded-2xl">
            <slot />
        </div>
    </div>
</template>
