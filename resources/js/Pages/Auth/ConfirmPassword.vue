<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), {
    onFinish: () => form.reset(),
});
</script>

<template>
    <GuestLayout>
        <Head title="Confirmer le mot de passe" />

        <h1 class="text-xl font-bold text-white mb-1">Zone sécurisée</h1>
        <p class="text-slate-300 text-sm mb-7">
            Veuillez confirmer votre mot de passe avant de continuer.
        </p>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Mot de passe</label>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl py-3 text-sm transition"
            >
                {{ form.processing ? 'Vérification…' : 'Confirmer' }}
            </button>
        </form>
    </GuestLayout>
</template>
