<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h1 class="text-xl font-bold text-white mb-1">Connexion</h1>
        <p class="text-slate-400 text-sm mb-7">Bienvenue ! Entrez vos identifiants pour continuer.</p>

        <div v-if="status" class="mb-5 rounded-xl bg-green-500/10 border border-green-500/20 px-4 py-3 text-sm text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="vous@exemple.com"
                    class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-sm font-medium text-slate-300">Mot de passe</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-blue-400 hover:text-blue-300 transition"
                    >
                        Mot de passe oublié ?
                    </Link>
                </div>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center gap-2.5">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/20 bg-white/10 text-blue-500 focus:ring-blue-500 focus:ring-offset-0"
                />
                <label for="remember" class="text-sm text-slate-400">Se souvenir de moi</label>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl py-3 text-sm transition"
            >
                {{ form.processing ? 'Connexion…' : 'Se connecter' }}
            </button>

        </form>

        <div class="mt-5 flex items-center justify-between text-sm">
            <Link :href="route('home')" class="text-slate-500 hover:text-slate-300 transition">← Accueil</Link>
            <button type="button" @click="form.reset()" class="text-slate-500 hover:text-slate-300 transition">Effacer</button>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500 border-t border-white/10 pt-5">
            Pas encore de compte ?
            <Link :href="route('register')" class="text-blue-400 hover:text-blue-300 font-medium transition">
                Créer un compte
            </Link>
        </p>

    </GuestLayout>
</template>
