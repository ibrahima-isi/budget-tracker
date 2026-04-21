<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const appSettings = computed(() => usePage().props.appSettings);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex flex-col items-center justify-center px-4 py-12">

        <!-- Brand -->
        <Link href="/" class="flex items-center gap-2 mb-8">
            <div v-if="appSettings?.logo_url" class="h-10 w-auto">
                <img :src="appSettings.logo_url" :alt="appSettings.business_name" class="h-10 w-auto object-contain" />
            </div>
            <template v-else>
                <div class="w-9 h-9 rounded-xl bg-blue-500 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <span class="font-bold text-lg tracking-tight text-white">{{ appSettings?.business_name ?? 'BudgetTrack' }}</span>
            </template>
        </Link>

        <!-- Card -->
        <div class="w-full max-w-md bg-white/10 border border-white/20 rounded-2xl p-8 backdrop-blur-md shadow-xl">
            <slot />
        </div>

    </div>
</template>
