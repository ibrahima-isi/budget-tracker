<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFlash } from '@/composables/useFlash';

defineProps({
    users: Object,
});

const page = usePage();
const { t, locale } = useI18n();
const { success, error } = useFlash();
const currentUser = computed(() => page.props.auth.user);
const actionForm = useForm({});

function approveUser(user) {
    actionForm.patch(route('settings.users.approve', user.id), {
        preserveScroll: true,
    });
}

function revokeApproval(user) {
    if (!confirm(t('users.confirmRevoke'))) return;

    actionForm.patch(route('settings.users.revoke-approval', user.id), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="$t('users.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('users.title') }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">
                <div v-if="success" class="rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">{{ success }}</div>
                <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/30 px-4 py-3 text-red-700 dark:text-red-400 text-sm">{{ error }}</div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ $t('users.user') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('users.role') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('users.status') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('users.createdAt') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-if="!users.data.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                        {{ $t('users.noData') }}
                                    </td>
                                </tr>

                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="user.is_admin
                                                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                        >
                                            {{ user.is_admin ? $t('users.admin') : $t('users.member') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="inline-flex w-fit items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                :class="user.is_approved
                                                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                                                    : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'"
                                            >
                                                {{ user.is_approved ? $t('users.approved') : $t('users.pending') }}
                                            </span>
                                            <span v-if="user.approved_at" class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ formatDate(user.approved_at) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <PrimaryButton
                                            v-if="!user.is_approved"
                                            type="button"
                                            :disabled="actionForm.processing"
                                            @click="approveUser(user)"
                                        >
                                            {{ $t('users.approve') }}
                                        </PrimaryButton>
                                        <SecondaryButton
                                            v-else-if="user.id !== currentUser?.id"
                                            type="button"
                                            :disabled="actionForm.processing"
                                            @click="revokeApproval(user)"
                                        >
                                            {{ $t('users.revoke') }}
                                        </SecondaryButton>
                                        <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.last_page > 1" class="px-6 py-4 flex gap-2 flex-wrap border-t border-gray-100 dark:border-gray-700">
                        <template v-for="link in users.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                class="px-3 py-1 rounded text-sm border"
                                :class="link.active
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 rounded text-sm border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-default"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
