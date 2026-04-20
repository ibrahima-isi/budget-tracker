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

        <form @submit.prevent="submit">
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
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Mot de passe" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirmer le mot de passe" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-6 flex items-center justify-between gap-2">
                <!-- Left: back to login -->
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none"
                >
                    ← Déjà inscrit ?
                </Link>

                <!-- Right: clear + submit -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="clear"
                        class="rounded-md px-4 py-2 text-sm text-gray-600 border border-gray-300 hover:bg-gray-50 focus:outline-none"
                    >
                        Effacer
                    </button>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        S'inscrire
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
