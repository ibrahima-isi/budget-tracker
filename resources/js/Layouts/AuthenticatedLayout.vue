<script setup>
import { ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { useLocale } from '@/composables/useLocale';

const page       = usePage();
const appSettings = computed(() => page.props.appSettings);

const { isDark, toggleDark } = useDarkMode();
useLocale(); // keeps i18n locale in sync

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav class="border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <img
                                        v-if="appSettings?.logo_url"
                                        :src="appSettings.logo_url"
                                        :alt="appSettings.business_name"
                                        class="block h-9 w-auto object-contain"
                                    />
                                    <span
                                        v-else
                                        class="text-gray-800 dark:text-gray-100 font-bold text-lg"
                                    >{{ appSettings?.business_name ?? 'BudgetTrack' }}</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    {{ $t('nav.dashboard') }}
                                </NavLink>
                                <NavLink :href="route('budgets.index')" :active="route().current('budgets.*')">
                                    {{ $t('nav.budgets') }}
                                </NavLink>
                                <NavLink :href="route('depenses.index')" :active="route().current('depenses.*')">
                                    {{ $t('nav.expenses') }}
                                </NavLink>
                                <NavLink :href="route('revenus.index')" :active="route().current('revenus.*')">
                                    {{ $t('nav.revenues') }}
                                </NavLink>
                                <NavLink :href="route('categories.index')" :active="route().current('categories.*')">
                                    {{ $t('nav.categories') }}
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user?.is_admin"
                                    :href="route('settings.index')"
                                    :active="route().current('settings.*')"
                                >
                                    {{ $t('nav.settings') }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
                            <!-- Dark mode toggle -->
                            <button
                                type="button"
                                @click="toggleDark"
                                :title="$t('darkMode')"
                                class="rounded-full p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            >
                                <!-- Sun icon (shown in dark mode) -->
                                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10A5 5 0 0012 7z" />
                                </svg>
                                <!-- Moon icon (shown in light mode) -->
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium leading-4 text-gray-500 dark:text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            {{ $t('nav.profile') }}
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            {{ $t('nav.logout') }}
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-500 focus:outline-none transition"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            {{ $t('nav.dashboard') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('budgets.index')" :active="route().current('budgets.*')">
                            {{ $t('nav.budgets') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('depenses.index')" :active="route().current('depenses.*')">
                            {{ $t('nav.expenses') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('revenus.index')" :active="route().current('revenus.*')">
                            {{ $t('nav.revenues') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('categories.index')" :active="route().current('categories.*')">
                            {{ $t('nav.categories') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user?.is_admin"
                            :href="route('settings.index')"
                            :active="route().current('settings.*')"
                        >
                            {{ $t('nav.settings') }}
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800 dark:text-gray-100">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <!-- Dark mode toggle in mobile menu -->
                            <button
                                type="button"
                                @click="toggleDark"
                                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10A5 5 0 0012 7z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                {{ $t('darkMode') }}
                            </button>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                {{ $t('nav.profile') }}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                {{ $t('nav.logout') }}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white dark:bg-gray-800 shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
