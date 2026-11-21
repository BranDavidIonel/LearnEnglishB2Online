<template>
    <Head :title="$t('Log in')" />

    <BrandMark />

    <form class="card" novalidate @submit.prevent="submit">
        <h2>{{ $t('Sign in to English Levels') }}</h2>
        <p class="muted center">
            {{ $t('Learn English step by step, from A1 to B2.') }}
        </p>

        <div class="mt">
            <label class="field">
                <span>{{ $t('Email') }}</span>
                <input
                    v-model="form.email"
                    v-focus
                    type="email"
                    class="input"
                    :class="{ wrong: form.errors.email }"
                    autocomplete="username"
                    required
                />
                <span v-if="form.errors.email" class="field-error">
                    {{ form.errors.email }}
                </span>
            </label>

            <label class="field">
                <span>{{ $t('Password') }}</span>
                <span class="field-box">
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="input"
                        :class="{ wrong: form.errors.password }"
                        autocomplete="current-password"
                        required
                    />
                    <button
                        type="button"
                        class="icon-btn"
                        :title="
                            showPassword
                                ? $t('Hide password')
                                : $t('Show password')
                        "
                        :aria-label="
                            showPassword
                                ? $t('Hide password')
                                : $t('Show password')
                        "
                        @click="showPassword = !showPassword"
                    >
                        {{ showPassword ? '🙈' : '👁️' }}
                    </button>
                </span>
                <span v-if="form.errors.password" class="field-error">
                    {{ form.errors.password }}
                </span>
            </label>

            <label class="check">
                <input v-model="form.remember" type="checkbox" />
                {{ $t('Remember me') }}
            </label>
        </div>

        <button
            type="submit"
            class="btn primary big mt"
            :disabled="form.processing"
        >
            {{ $t('Log in') }}
        </button>
    </form>
</template>

<script lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import BrandMark from '@/components/layout/BrandMark.vue';
import loginRoutes from '@/routes/login';

/** The sign-in form of the single account of the app. */
export default defineComponent({
    name: 'AuthLogin',

    components: { BrandMark, Head },

    data() {
        return {
            showPassword: false,
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
