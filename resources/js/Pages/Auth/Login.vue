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

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
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
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Se souvenir de moi</span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between gap-2">
                <!-- Left: back to home + forgot password -->
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('home')"
                        class="rounded-md text-sm text-gray-500 hover:text-gray-700 underline focus:outline-none"
                    >
                        ← Accueil
                    </Link>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none"
                    >
                        Mot de passe oublié ?
                    </Link>
                </div>

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
                        Se connecter
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
