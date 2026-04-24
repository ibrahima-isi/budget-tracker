<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useFlash } from '@/composables/useFlash';

const props = defineProps({ categories: Array });
const { t } = useI18n();
const { success } = useFlash();
const authUser = usePage().props.auth.user;

// ── Create ────────────────────────────────────────────────────────────────────
const showCreate = ref(false);
const form = useForm({ name: '', color: '#3b82f6', icon: 'shopping-cart' });

function submitCreate() {
    form.post(route('categories.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

// ── Edit ──────────────────────────────────────────────────────────────────────
const showEdit = ref(false);
const editForm = useForm({ name: '', color: '#3b82f6', icon: '' });
let editId = null;

function openEdit(c) {
    editId          = c.id;
    editForm.name   = c.name;
    editForm.color  = c.color;
    editForm.icon   = c.icon;
    showEdit.value  = true;
}

function submitEdit() {
    editForm.patch(route('categories.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteForm = useForm({});
function deleteCategory(id) {
    if (confirm(t('categories.confirmDelete'))) {
        deleteForm.delete(route('categories.destroy', id));
    }
}

// ── Toggle enabled ────────────────────────────────────────────────────────────
const toggling = ref(new Set());
function toggleEnabled(id) {
    if (toggling.value.has(id)) return;
    toggling.value = new Set([...toggling.value, id]);
    router.post(route('categories.toggleEnabled', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            const next = new Set(toggling.value);
            next.delete(id);
            toggling.value = next;
        },
    });
}

// ── Permissions ───────────────────────────────────────────────────────────────
function canEditOrDelete(c) {
    return authUser.is_admin || c.user_id === authUser.id;
}
</script>

<template>
    <Head :title="$t('categories.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $t('categories.title') }}</h2>
                <PrimaryButton @click="showCreate = true">{{ $t('categories.new') }}</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="success" class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">
                    {{ success }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="c in categories"
                        :key="c.id"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4"
                        :class="{ 'opacity-60': !c.enabled }"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <!-- Icon + name -->
                            <div class="flex items-center gap-3 min-w-0">
                                <span
                                    class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                    :style="{ backgroundColor: c.color }"
                                >
                                    {{ c.name.charAt(0).toUpperCase() }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ c.name }}</p>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $t('categories.expenseCount', c.expenses_count) }}
                                        </p>
                                        <span
                                            v-if="c.user_id === null"
                                            class="text-xs px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400"
                                        >{{ $t('categories.global') }}</span>
                                        <span
                                            v-else
                                            class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                                        >{{ $t('categories.personal') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <!-- Enable / Disable toggle -->
                                <button
                                    type="button"
                                    @click="toggleEnabled(c.id)"
                                    :disabled="toggling.has(c.id)"
                                    :title="c.enabled ? $t('settings.disabled') : $t('settings.enabled')"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                                    :class="c.enabled
                                        ? 'bg-blue-500'
                                        : 'bg-gray-300 dark:bg-gray-600'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200"
                                        :class="c.enabled ? 'translate-x-4' : 'translate-x-0'"
                                    />
                                </button>

                                <!-- Edit / Delete — admin or owner only -->
                                <template v-if="canEditOrDelete(c)">
                                    <button @click="openEdit(c)" class="text-xs text-yellow-600 dark:text-yellow-400 hover:underline">{{ $t('common.edit') }}</button>
                                    <button @click="deleteCategory(c.id)" class="text-xs text-red-600 dark:text-red-400 hover:underline">{{ $t('common.delete') }}</button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-if="!categories.length" class="col-span-3 text-center text-gray-400 dark:text-gray-500 py-12">
                        {{ $t('categories.noData') }}
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Create Modal -->
    <AppModal :show="showCreate" :title="$t('categories.createTitle')" max-width="lg" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel :value="$t('categories.name')" />
                <TextInput v-model="form.name" class="mt-1 block w-full" />
                <InputError :message="form.errors.name" />
            </div>
            <div>
                <InputLabel :value="$t('categories.color')" />
                <div class="mt-1 flex items-center gap-3">
                    <input v-model="form.color" type="color" class="h-10 w-16 rounded border border-gray-300 dark:border-gray-600 cursor-pointer bg-white dark:bg-gray-700" />
                    <TextInput v-model="form.color" placeholder="#3b82f6" class="block w-full" />
                </div>
                <InputError :message="form.errors.color" />
            </div>
            <div>
                <InputLabel :value="$t('categories.icon')" />
                <TextInput v-model="form.icon" :placeholder="$t('categories.iconPlaceholder')" class="mt-1 block w-full" />
                <InputError :message="form.errors.icon" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="form.processing">{{ $t('common.create') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" :title="$t('categories.editTitle')" max-width="lg" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel :value="$t('categories.name')" />
                <TextInput v-model="editForm.name" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.name" />
            </div>
            <div>
                <InputLabel :value="$t('categories.color')" />
                <div class="mt-1 flex items-center gap-3">
                    <input v-model="editForm.color" type="color" class="h-10 w-16 rounded border border-gray-300 dark:border-gray-600 cursor-pointer bg-white dark:bg-gray-700" />
                    <TextInput v-model="editForm.color" class="block w-full" />
                </div>
                <InputError :message="editForm.errors.color" />
            </div>
            <div>
                <InputLabel :value="$t('categories.icon')" />
                <TextInput v-model="editForm.icon" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.icon" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">{{ $t('common.cancel') }}</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">{{ $t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
