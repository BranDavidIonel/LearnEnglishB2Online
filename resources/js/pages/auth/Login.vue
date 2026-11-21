<template>
    <Head :title="$t('Log in')" />

    <div class="mb-6 flex justify-center">
        <BrandMark />
    </div>

    <div
        class="rounded-2xl border border-[#2a3560] bg-[#151c36] p-7 shadow-[0_10px_30px_rgba(0,0,0,0.35)] sm:p-8"
    >
        <div class="text-center">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ $t('Sign in to English Levels') }}
            </h1>
            <p class="mt-1.5 text-sm text-[#97a3c9]">
                {{ $t('Learn English step by step, from A1 to B2.') }}
            </p>
        </div>

        <form class="mt-7 space-y-5" @submit.prevent="submit">
            <div>
                <label for="login-email" :class="labelClass">
                    {{ $t('Email') }}
                </label>
                <div class="relative">
                    <Mail :class="iconClass" />
                    <input
                        id="login-email"
                        v-model="form.email"
                        v-focus
                        type="email"
                        autocomplete="username"
                        required
                        class="pr-3"
                        :class="[
                            inputClass,
                            form.errors.email ? errorInputClass : '',
                        ]"
                        placeholder="you@example.com"
                    />
                </div>
                <p v-if="form.errors.email" :class="errorClass">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="login-password" :class="labelClass">
                    {{ $t('Password') }}
                </label>
                <div class="relative">
                    <LockKeyhole :class="iconClass" />
                    <input
                        id="login-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        class="pr-10"
                        :class="[
                            inputClass,
                            form.errors.password ? errorInputClass : '',
                        ]"
                    />
                    <button
                        type="button"
                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-[#97a3c9] transition hover:text-white"
                        :aria-label="
                            showPassword
                                ? $t('Hide password')
                                : $t('Show password')
                        "
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" :class="errorClass">
                    {{ form.errors.password }}
                </p>
            </div>

            <label
                class="flex cursor-pointer items-center gap-2.5 text-sm text-[#97a3c9] select-none"
            >
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="size-4 rounded accent-[#6366f1]"
                />
                {{ $t('Remember me') }}
            </label>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#6366f1] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#6366f1]/25 transition hover:bg-[#818cf8] disabled:cursor-wait disabled:opacity-70"
                :disabled="form.processing"
            >
                <LogIn class="size-4" />
                {{ $t('Log in') }}
            </button>
        </form>
    </div>
</template>

<script lang="ts">
import { Eye, EyeOff, LockKeyhole, LogIn, Mail } from '@lucide/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import BrandMark from '@/components/layout/BrandMark.vue';
import loginRoutes from '@/routes/login';

/** Sign-in form on the dark auth layout (restyled later with the shared CSS). */
export default defineComponent({
    name: 'AuthLogin',

    components: { BrandMark, Eye, EyeOff, Head, LockKeyhole, LogIn, Mail },

    data() {
        return {
            showPassword: false,
            labelClass: 'mb-1.5 block text-sm font-medium text-[#e9edfb]',
            iconClass:
                'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#97a3c9]',
            inputClass:
                'block w-full rounded-xl border border-[#2a3560] bg-[#1c2547] py-2.5 pl-9 text-sm text-[#e9edfb] transition outline-none placeholder:text-[#97a3c9]/60 focus:border-[#818cf8] focus:ring-2 focus:ring-[#6366f1]/30',
            errorInputClass:
                'border-[#ef4444]/70 focus:border-[#ef4444] focus:ring-[#ef4444]/30',
            errorClass: 'mt-1.5 text-xs font-medium text-[#f87171]',
            form: useForm({
                email: '',
                password: '',
                remember: false,
            }),
        };
    },

    methods: {
        submit(): void {
            this.form.submit(loginRoutes.store(), {
                onFinish: () => this.form.reset('password'),
            });
        },
    },
});
</script>
