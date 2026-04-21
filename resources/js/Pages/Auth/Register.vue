<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const clear = () => form.reset();
</script>

<template>
    <GuestLayout>
        <Head title="Inscription" />

        <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 text-center">Créer un compte</h1>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="name" value="Nom" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="password" value="Mot de passe" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Confirmer" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-1" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <!-- Actions -->
            <div class="pt-2 flex flex-col gap-3">
                <PrimaryButton
                    class="w-full justify-center py-3 text-base"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    S'inscrire
                </PrimaryButton>

                <div class="flex items-center justify-between text-sm">
                    <Link
                        :href="route('login')"
                        class="text-gray-500 dark:text-gray-400 underline hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
                    >
                        ← Déjà inscrit ?
                    </Link>
                    <button
                        type="button"
                        @click="clear"
                        class="text-gray-500 dark:text-gray-400 underline hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
                    >
                        Effacer
                    </button>
                </div>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Déjà inscrit ?
            <Link :href="route('login')" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                Se connecter
            </Link>
        </p>
    </GuestLayout>
</template>
