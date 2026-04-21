<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useFlash } from '@/composables/useFlash';

const props = defineProps({
    settings:   Object,
    currencies: Array,
});

const { success, error } = useFlash();

const tab = ref('general'); // general | currencies

// ── General settings form ─────────────────────────────────────────────────────
const settingsForm = useForm({
    business_name:    props.settings.business_name  ?? '',
    business_email:   props.settings.business_email ?? '',
    phone:            props.settings.phone          ?? '',
    language:         props.settings.language       ?? 'fr',
    default_currency: props.settings.default_currency ?? 'XOF',
    logo:             null,
});

const logoPreview = ref(
    props.settings.logo_path ? route('logo') : null
);

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    settingsForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function submitSettings() {
    settingsForm.post(route('settings.update'), {
        forceFormData:  true,
        preserveScroll: true,
        onSuccess: () => { settingsForm.logo = null; },
    });
}

const deleteLogoForm = useForm({});
function removeLogo() {
    if (confirm('Supprimer le logo ?')) {
        deleteLogoForm.delete(route('settings.logo.destroy'), {
            onSuccess: () => { logoPreview.value = null; },
        });
    }
}

const languages = [
    { code: 'fr', label: 'Français' },
    { code: 'en', label: 'English'  },
    { code: 'es', label: 'Español'  },
];

const activeCurrencies = computed(() => props.currencies.filter(c => c.is_active));

// ── Currency forms ────────────────────────────────────────────────────────────
const showAddCurrency = ref(false);
const currencyForm = useForm({ code: '', name: '', symbol: '' });

function submitCurrency() {
    currencyForm.post(route('currencies.store'), {
        onSuccess: () => { showAddCurrency.value = false; currencyForm.reset(); },
    });
}

const showEditCurrency  = ref(false);
const editCurrencyForm  = useForm({ code: '', name: '', symbol: '' });
let editCurrencyId      = null;

function openEditCurrency(c) {
    editCurrencyId      = c.id;
    editCurrencyForm.code   = c.code;
    editCurrencyForm.name   = c.name;
    editCurrencyForm.symbol = c.symbol;
    showEditCurrency.value  = true;
}

function submitEditCurrency() {
    editCurrencyForm.patch(route('currencies.update', editCurrencyId), {
        onSuccess: () => { showEditCurrency.value = false; },
    });
}

const actionForm = useForm({});

function setDefault(id)  { actionForm.patch(route('currencies.default', id)); }
function toggleCurrency(id) { actionForm.patch(route('currencies.toggle', id)); }
function deleteCurrency(id) {
    if (confirm('Supprimer cette devise ?')) {
        actionForm.delete(route('currencies.destroy', id));
    }
}
</script>

<template>
    <Head title="Paramètres" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Paramètres</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

                <!-- Flash messages -->
                <div v-if="success" class="rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-green-700 dark:text-green-400 text-sm">{{ success }}</div>
                <div v-if="error"   class="rounded-lg bg-red-50 dark:bg-red-900/30 px-4 py-3 text-red-700 dark:text-red-400 text-sm">{{ error }}</div>

                <!-- Tabs -->
                <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 p-1 rounded-xl w-fit">
                    <button
                        @click="tab = 'general'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'general' ? 'bg-white dark:bg-gray-800 shadow text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    >
                        Général
                    </button>
                    <button
                        @click="tab = 'currencies'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition"
                        :class="tab === 'currencies' ? 'bg-white dark:bg-gray-800 shadow text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    >
                        Devises
                    </button>
                </div>

                <!-- ── TAB: General ── -->
                <div v-if="tab === 'general'" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <form @submit.prevent="submitSettings" enctype="multipart/form-data" class="space-y-6">

                        <!-- Logo -->
                        <div>
                            <InputLabel value="Logo de l'entreprise" />
                            <div class="mt-2 flex items-center gap-4">
                                <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-700">
                                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain" />
                                    <svg v-else class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="flex gap-2">
                                    <label class="cursor-pointer px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        Choisir un fichier
                                        <input type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                                    </label>
                                    <DangerButton v-if="logoPreview && settings.logo_path" type="button" @click="removeLogo" class="text-xs">
                                        Supprimer
                                    </DangerButton>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">PNG, JPG, SVG — max 2 Mo</p>
                            <InputError :message="settingsForm.errors.logo" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Nom de l'entreprise *" />
                                <TextInput v-model="settingsForm.business_name" class="mt-1 block w-full" />
                                <InputError :message="settingsForm.errors.business_name" />
                            </div>
                            <div>
                                <InputLabel value="Email professionnel" />
                                <TextInput v-model="settingsForm.business_email" type="email" class="mt-1 block w-full" />
                                <InputError :message="settingsForm.errors.business_email" />
                            </div>
                            <div>
                                <InputLabel value="Téléphone" />
                                <TextInput v-model="settingsForm.phone" type="tel" placeholder="+224 620 000 000" class="mt-1 block w-full" />
                                <InputError :message="settingsForm.errors.phone" />
                            </div>
                            <div>
                                <InputLabel value="Langue" />
                                <select v-model="settingsForm.language" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option v-for="l in languages" :key="l.code" :value="l.code">{{ l.label }}</option>
                                </select>
                                <InputError :message="settingsForm.errors.language" />
                            </div>
                            <div>
                                <InputLabel value="Devise par défaut" />
                                <select v-model="settingsForm.default_currency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">
                                        {{ c.code }} — {{ c.name }} ({{ c.symbol }})
                                    </option>
                                </select>
                                <InputError :message="settingsForm.errors.default_currency" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="settingsForm.processing">Enregistrer les paramètres</PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- ── TAB: Currencies ── -->
                <div v-if="tab === 'currencies'" class="space-y-4">
                    <div class="flex justify-end">
                        <PrimaryButton @click="showAddCurrency = true">+ Ajouter une devise</PrimaryButton>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="min-w-full text-sm dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-6 py-3 text-left">Code</th>
                                    <th class="px-6 py-3 text-left">Nom</th>
                                    <th class="px-6 py-3 text-left">Symbole</th>
                                    <th class="px-6 py-3 text-center">Défaut</th>
                                    <th class="px-6 py-3 text-center">Active</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="c in currencies" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ c.code }}</td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ c.name }}</td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ c.symbol }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <button
                                            v-if="!c.is_default"
                                            @click="setDefault(c.id)"
                                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                        >Définir</button>
                                        <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-0.5 rounded-full">
                                            ✓ Défaut
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <button @click="toggleCurrency(c.id)" class="text-xs" :class="c.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'">
                                            {{ c.is_active ? '● Activée' : '○ Désactivée' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-3 text-right space-x-2">
                                        <button @click="openEditCurrency(c)" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">Modifier</button>
                                        <button
                                            v-if="!c.is_default"
                                            @click="deleteCurrency(c.id)"
                                            class="text-red-600 dark:text-red-400 hover:underline text-xs"
                                        >Supprimer</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Add Currency Modal -->
    <AppModal :show="showAddCurrency" title="Nouvelle devise" max-width="lg" @close="showAddCurrency = false">
        <form @submit.prevent="submitCurrency" class="space-y-4">
            <div>
                <InputLabel value="Code ISO 4217 (ex: EUR)" />
                <TextInput v-model="currencyForm.code" placeholder="XOF" class="mt-1 block w-full uppercase" />
                <InputError :message="currencyForm.errors.code" />
            </div>
            <div>
                <InputLabel value="Nom" />
                <TextInput v-model="currencyForm.name" placeholder="Franc CFA" class="mt-1 block w-full" />
                <InputError :message="currencyForm.errors.name" />
            </div>
            <div>
                <InputLabel value="Symbole" />
                <TextInput v-model="currencyForm.symbol" placeholder="FCFA" class="mt-1 block w-full" />
                <InputError :message="currencyForm.errors.symbol" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showAddCurrency = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="currencyForm.processing">Ajouter</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Currency Modal -->
    <AppModal :show="showEditCurrency" title="Modifier la devise" max-width="lg" @close="showEditCurrency = false">
        <form @submit.prevent="submitEditCurrency" class="space-y-4">
            <div>
                <InputLabel value="Code ISO 4217" />
                <TextInput v-model="editCurrencyForm.code" class="mt-1 block w-full uppercase" />
                <InputError :message="editCurrencyForm.errors.code" />
            </div>
            <div>
                <InputLabel value="Nom" />
                <TextInput v-model="editCurrencyForm.name" class="mt-1 block w-full" />
                <InputError :message="editCurrencyForm.errors.name" />
            </div>
            <div>
                <InputLabel value="Symbole" />
                <TextInput v-model="editCurrencyForm.symbol" class="mt-1 block w-full" />
                <InputError :message="editCurrencyForm.errors.symbol" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEditCurrency = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="editCurrencyForm.processing">Enregistrer</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
