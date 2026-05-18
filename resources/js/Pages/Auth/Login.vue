<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { Field, FieldLabel, FieldError } from '@/packages/ui/src/field';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const page = usePage<{
    flash: {
        message: string;
    };
}>();
</script>

<template>
    <Head title="Log in" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <template #actions>
            <Link
                class="py-8 text-text-secondary text-sm font-medium opacity-90 hover:opacity-100 transition"
                :href="route('register')">
                No account yet? <span class="text-text-primary">Register here!</span>
            </Link>
        </template>

        <div v-if="status" class="mb-4 font-medium text-sm text-green-400">
            {{ status }}
        </div>
        <div
            v-if="page.props.flash?.message"
            class="bg-red-400 text-black text-center w-full px-3 py-1 mb-4 rounded-lg">
            {{ page.props.flash?.message }}
        </div>

        <form @submit.prevent="submit">
            <Field>
                <FieldLabel for="email">Email</FieldLabel>
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="block w-full"
                    required
                    autofocus
                    autocomplete="username" />
                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
            </Field>

            <Field class="mt-4">
                <FieldLabel for="password">Password</FieldLabel>
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="block w-full"
                    required
                    autocomplete="current-password" />
                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
            </Field>

            <div class="flex items-center justify-end mt-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-text-secondary hover:text-text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Forgot your password?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing">
                    Log in
                </PrimaryButton>
            </div>
        </form>

        <div class="relative my-6" role="separator" aria-label="Alternative sign-in">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-border" />
            </div>
            <div class="relative flex justify-center text-xs font-medium uppercase tracking-wide">
                <span class="bg-card-background px-2 text-text-secondary">or</span>
            </div>
        </div>

        <a
            class="w-full border border-border bg-surface rounded-lg h-10 inline-flex items-center justify-center gap-2 text-sm font-medium text-text-primary hover:opacity-90 transition"
            :href="route('auth.google.redirect')">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path
                    fill="#34A853"
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path
                    fill="#FBBC05"
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                <path
                    fill="#EA4335"
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
            </svg>
            Continue with Google
        </a>
    </AuthenticationCard>
</template>
