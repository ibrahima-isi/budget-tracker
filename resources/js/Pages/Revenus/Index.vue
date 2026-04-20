<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useFormatMoney } from '@/composables/useFormatMoney';
import { useFlash } from '@/composables/useFlash';

const props = defineProps({ revenus: Object });

const { format } = useFormatMoney();
const { success } = useFlash();

const moisLabels = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

// Create
const showCreate = ref(false);
const form = useForm({ source: '', montant: '', date_revenu: new Date().toISOString().slice(0, 10), note: '' });

function submitCreate() {
    form.post(route('revenus.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

// Edit
const showEdit = ref(false);
const editForm = useForm({ source: '', montant: '', date_revenu: '', note: '' });
let editId = null;

function openEdit(r) {
    editId              = r.id;
    editForm.source     = r.source;
    editForm.montant    = r.montant;
    editForm.date_revenu = r.date_revenu?.slice(0, 10) ?? '';
    editForm.note       = r.note ?? '';
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('revenus.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteRevenu(id) {
    if (confirm('Supprimer ce revenu ?')) {
        deleteForm.delete(route('revenus.destroy', id));
    }
}

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR');
</script>

<template>
    <Head title="Revenus" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Revenus</h2>
                <PrimaryButton @click="showCreate = true">+ Nouveau revenu</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-green-700 text-sm">{{ success }}</div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Source</th>
                                <th class="px-6 py-3 text-left">Période</th>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-right">Montant</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="!revenus.data.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400">Aucun revenu trouvé.</td>
                            </tr>
                            <tr v-for="r in revenus.data" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-gray-900 font-medium">{{ r.source }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ moisLabels[r.mois] }} {{ r.annee }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ formatDate(r.date_revenu) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-green-600">{{ format(r.montant) }}</td>
                                <td class="px-6 py-3 text-right space-x-2">
                                    <button @click="openEdit(r)" class="text-yellow-600 hover:underline text-xs">Modifier</button>
                                    <button @click="deleteRevenu(r.id)" class="text-red-600 hover:underline text-xs">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="revenus.last_page > 1" class="px-6 py-4 flex gap-2 border-t border-gray-100">
                        <Link
                            v-for="link in revenus.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="px-3 py-1 rounded text-sm border"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Create Modal -->
    <AppModal :show="showCreate" title="Nouveau revenu" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel value="Source" />
                <TextInput v-model="form.source" placeholder="Salaire, Freelance…" class="mt-1 block w-full" />
                <InputError :message="form.errors.source" />
            </div>
            <div>
                <InputLabel value="Montant (XOF)" />
                <TextInput v-model="form.montant" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="form.errors.montant" />
            </div>
            <div>
                <InputLabel value="Date" />
                <TextInput v-model="form.date_revenu" type="date" class="mt-1 block w-full" />
                <InputError :message="form.errors.date_revenu" />
            </div>
            <div>
                <InputLabel value="Note (optionnel)" />
                <textarea v-model="form.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" title="Modifier le revenu" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel value="Source" />
                <TextInput v-model="editForm.source" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.source" />
            </div>
            <div>
                <InputLabel value="Montant (XOF)" />
                <TextInput v-model="editForm.montant" type="number" step="1" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.montant" />
            </div>
            <div>
                <InputLabel value="Date" />
                <TextInput v-model="editForm.date_revenu" type="date" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.date_revenu" />
            </div>
            <div>
                <InputLabel value="Note (optionnel)" />
                <textarea v-model="editForm.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
