<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
</script>

<template>
    <GuestLayout>
        <Head title="Inscription" />

        <h1 class="text-xl font-bold text-white mb-1">Créer un compte</h1>
        <p class="text-slate-400 text-sm mb-7">Gratuit. Prenez le contrôle de votre budget dès aujourd'hui.</p>

        <form @submit.prevent="submit" class="space-y-5">

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Nom</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Votre nom"
                    class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    placeholder="vous@exemple.com"
                    class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Mot de passe</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                    />
                    <InputError class="mt-1.5" :message="form.errors.password" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirmer</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                    />
                    <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl py-3 text-sm transition"
            >
                {{ form.processing ? 'Inscription…' : 'Créer mon compte →' }}
            </button>

        </form>

        <div class="mt-5 flex items-center justify-between text-sm">
            <Link :href="route('login')" class="text-slate-500 hover:text-slate-300 transition">← Retour</Link>
            <button type="button" @click="form.reset()" class="text-slate-500 hover:text-slate-300 transition">Effacer</button>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500 border-t border-white/10 pt-5">
            Déjà inscrit ?
            <Link :href="route('login')" class="text-blue-400 hover:text-blue-300 font-medium transition">
                Se connecter
            </Link>
        </p>

    </GuestLayout>
</template>
