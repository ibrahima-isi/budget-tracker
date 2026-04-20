<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useFlash } from '@/composables/useFlash';

const props = defineProps({ categories: Array });
const { success } = useFlash();

// Create
const showCreate = ref(false);
const form = useForm({ nom: '', couleur: '#3b82f6', icone: 'shopping-cart' });

function submitCreate() {
    form.post(route('categories.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

// Edit
const showEdit = ref(false);
const editForm = useForm({ nom: '', couleur: '#3b82f6', icone: '' });
let editId = null;

function openEdit(c) {
    editId          = c.id;
    editForm.nom    = c.nom;
    editForm.couleur = c.couleur;
    editForm.icone  = c.icone;
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('categories.update', editId), {
        onSuccess: () => { showEdit.value = false; },
    });
}

const deleteForm = useForm({});
function deleteCategorie(id) {
    if (confirm('Supprimer cette catégorie ? Les dépenses liées ne seront pas supprimées.')) {
        deleteForm.delete(route('categories.destroy', id));
    }
}
</script>

<template>
    <Head title="Catégories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Catégories</h2>
                <PrimaryButton @click="showCreate = true">+ Nouvelle catégorie</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-green-700 text-sm">{{ success }}</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="c in categories"
                        :key="c.id"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                :style="{ backgroundColor: c.couleur }"
                            >
                                {{ c.nom.charAt(0).toUpperCase() }}
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">{{ c.nom }}</p>
                                <p class="text-xs text-gray-400">{{ c.depenses_count }} dépense{{ c.depenses_count !== 1 ? 's' : '' }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <button @click="openEdit(c)" class="text-yellow-600 hover:underline">Modifier</button>
                            <button @click="deleteCategorie(c.id)" class="text-red-600 hover:underline">Supprimer</button>
                        </div>
                    </div>
                    <div v-if="!categories.length" class="col-span-3 text-center text-gray-400 py-12">
                        Aucune catégorie. Créez-en une !
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Create Modal -->
    <AppModal :show="showCreate" title="Nouvelle catégorie" max-width="lg" @close="showCreate = false">
        <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
                <InputLabel value="Nom" />
                <TextInput v-model="form.nom" class="mt-1 block w-full" />
                <InputError :message="form.errors.nom" />
            </div>
            <div>
                <InputLabel value="Couleur" />
                <div class="mt-1 flex items-center gap-3">
                    <input v-model="form.couleur" type="color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer" />
                    <TextInput v-model="form.couleur" placeholder="#3b82f6" class="block w-full" />
                </div>
                <InputError :message="form.errors.couleur" />
            </div>
            <div>
                <InputLabel value="Icône (nom Heroicon)" />
                <TextInput v-model="form.icone" placeholder="shopping-cart" class="mt-1 block w-full" />
                <InputError :message="form.errors.icone" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showCreate = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Créer</PrimaryButton>
            </div>
        </form>
    </AppModal>

    <!-- Edit Modal -->
    <AppModal :show="showEdit" title="Modifier la catégorie" max-width="lg" @close="showEdit = false">
        <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
                <InputLabel value="Nom" />
                <TextInput v-model="editForm.nom" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.nom" />
            </div>
            <div>
                <InputLabel value="Couleur" />
                <div class="mt-1 flex items-center gap-3">
                    <input v-model="editForm.couleur" type="color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer" />
                    <TextInput v-model="editForm.couleur" class="block w-full" />
                </div>
                <InputError :message="editForm.errors.couleur" />
            </div>
            <div>
                <InputLabel value="Icône (nom Heroicon)" />
                <TextInput v-model="editForm.icone" class="mt-1 block w-full" />
                <InputError :message="editForm.errors.icone" />
            </div>
            <div class="flex justify-end gap-3 mt-2">
                <SecondaryButton type="button" @click="showEdit = false">Annuler</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
            </div>
        </form>
    </AppModal>
</template>
