<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String },
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const clear = () => form.reset();
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 text-center">Connexion</h1>

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 px-4 py-3 text-sm font-medium text-green-700 dark:text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Mot de passe" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-gray-500 dark:text-gray-400 underline hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
                    >
                        Mot de passe oublié ?
                    </Link>
                </div>
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <label class="flex items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-gray-600 dark:text-gray-300">Se souvenir de moi</span>
                </label>
            </div>

            <!-- Actions -->
            <div class="pt-2 flex flex-col gap-3">
                <PrimaryButton
                    class="w-full justify-center py-3 text-base"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Se connecter
                </PrimaryButton>

                <div class="flex items-center justify-between text-sm">
                    <Link
                        :href="route('home')"
                        class="text-gray-500 dark:text-gray-400 underline hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
                    >
                        ← Accueil
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
            Pas encore de compte ?
            <Link :href="route('register')" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                Créer un compte
            </Link>
        </p>
    </GuestLayout>
</template>
