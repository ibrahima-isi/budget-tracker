<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';

const props = defineProps({
    logs:           Object,
    filters:        Object,
    eventColors:    Object,
    eventOptions:   Array,
    subjectOptions: Array,
});

const { formatDate } = useLocale();

// ── Filters ────────────────────────────────────────────────────────────────────
const search      = ref(props.filters.search       ?? '');
const filterEvent = ref(props.filters.event        ?? '');
const filterType  = ref(props.filters.subject_type ?? '');

function applyFilters() {
    router.get(route('activity-logs.index'), {
        search:       search.value      || undefined,
        event:        filterEvent.value || undefined,
        subject_type: filterType.value  || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    search.value      = '';
    filterEvent.value = '';
    filterType.value  = '';
    router.get(route('activity-logs.index'));
}

// ── Expandable details ─────────────────────────────────────────────────────────
const expanded = ref(new Set());
function toggle(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

// ── Event badge colour ─────────────────────────────────────────────────────────
const badgeClass = {
    green:  'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
    blue:   'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
    red:    'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
    gray:   'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
    teal:   'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
};

function eventBadge(event) {
    const color = props.eventColors[event] ?? 'gray';
    return badgeClass[color] ?? badgeClass.gray;
}

function formatTs(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="$t('nav.activityLogs')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('nav.activityLogs') }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 sm:px-6 py-4">
                    <div class="flex flex-col sm:flex-row gap-3 flex-wrap items-end">
                        <!-- Search -->
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $t('activityLog.search') }}</label>
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                type="text"
                                :placeholder="$t('activityLog.searchPlaceholder')"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Event filter -->
                        <div class="w-full sm:w-40">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $t('activityLog.event') }}</label>
                            <select v-model="filterEvent" @change="applyFilters" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm">
                                <option value="">{{ $t('activityLog.all') }}</option>
                                <option v-for="e in eventOptions" :key="e" :value="e">{{ e }}</option>
                            </select>
                        </div>

                        <!-- Subject type filter -->
                        <div class="w-full sm:w-40">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $t('activityLog.resource') }}</label>
                            <select v-model="filterType" @change="applyFilters" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm">
                                <option value="">{{ $t('activityLog.allTypes') }}</option>
                                <option v-for="t in subjectOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-2">
                            <button @click="applyFilters" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition">
                                {{ $t('activityLog.filter') }}
                            </button>
                            <button @click="resetFilters" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                {{ $t('activityLog.reset') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ $t('common.date') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('activityLog.user') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('activityLog.event') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('activityLog.resource') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('activityLog.ip') }}</th>
                                    <th class="px-4 py-3 text-left">{{ $t('activityLog.details') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-if="!logs.data.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                        {{ $t('activityLog.noActivity') }}
                                    </td>
                                </tr>

                                <template v-for="log in logs.data" :key="log.id">
                                    <!-- Main row -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs">
                                            {{ formatTs(log.created_at) }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200 font-medium">
                                            {{ log.user_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="eventBadge(log.event)">
                                                {{ log.event }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            <span v-if="log.subject_type" class="font-medium">{{ log.subject_type }}</span>
                                            <span v-if="log.subject_label" class="text-gray-500 dark:text-gray-400"> — {{ log.subject_label }}</span>
                                            <span v-if="!log.subject_type" class="text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs font-mono">
                                            {{ log.ip_address ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <button
                                                v-if="log.properties"
                                                @click="toggle(log.id)"
                                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                            >
                                                {{ expanded.has(log.id) ? $t('activityLog.hide') : $t('activityLog.view') }}
                                            </button>
                                            <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                                        </td>
                                    </tr>

                                    <!-- Expandable properties row -->
                                    <tr v-if="log.properties && expanded.has(log.id)" class="bg-gray-50 dark:bg-gray-700/30">
                                        <td colspan="6" class="px-4 py-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
                                                <div v-if="log.properties.old">
                                                    <p class="text-red-600 dark:text-red-400 font-semibold font-sans mb-1">{{ $t('activityLog.before') }}</p>
                                                    <pre class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 overflow-auto text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ JSON.stringify(log.properties.old, null, 2) }}</pre>
                                                </div>
                                                <div v-if="log.properties.new">
                                                    <p class="text-green-600 dark:text-green-400 font-semibold font-sans mb-1">{{ $t('activityLog.after') }}</p>
                                                    <pre class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 overflow-auto text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ JSON.stringify(log.properties.new, null, 2) }}</pre>
                                                </div>
                                                <!-- Auth events have no old/new -->
                                                <div v-if="!log.properties.old && !log.properties.new">
                                                    <pre class="bg-gray-100 dark:bg-gray-800 rounded-lg p-3 overflow-auto text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ JSON.stringify(log.properties, null, 2) }}</pre>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="px-6 py-4 flex gap-2 flex-wrap border-t border-gray-100 dark:border-gray-700">
                        <template v-for="link in logs.links" :key="link.label">
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
