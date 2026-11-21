<template>
    <div class="card">
        <h3>{{ $t('👤 Your account') }}</h3>
        <div class="grid2">
            <form novalidate @submit.prevent="saveProfile">
                <label class="field">
                    <span>{{ $t('Name') }}</span>
                    <input
                        v-model="profile.name"
                        class="input"
                        :class="{ wrong: profile.errors.name }"
                        autocomplete="name"
                        required
                    />
                    <span v-if="profile.errors.name" class="field-error">
                        {{ profile.errors.name }}
                    </span>
                </label>
                <label class="field">
                    <span>{{ $t('Email') }}</span>
                    <input
                        v-model="profile.email"
                        type="email"
                        class="input"
                        :class="{ wrong: profile.errors.email }"
                        autocomplete="email"
                        required
                    />
                    <span v-if="profile.errors.email" class="field-error">
                        {{ profile.errors.email }}
                    </span>
                </label>
                <p class="muted small">
                    {{ $t('You log in with this address.') }}
                </p>
                <button
                    type="submit"
                    class="btn primary"
                    :disabled="profile.processing || !profile.isDirty"
                >
                    {{ $t('Save') }}
                </button>
            </form>

            <form novalidate @submit.prevent="savePassword">
                <label class="field">
                    <span>{{ $t('Current password') }}</span>
                    <input
                        v-model="password.current_password"
                        type="password"
                        class="input"
                        :class="{ wrong: password.errors.current_password }"
                        autocomplete="current-password"
                        required
                    />
                    <span
                        v-if="password.errors.current_password"
                        class="field-error"
                    >
                        {{ password.errors.current_password }}
                    </span>
                </label>
                <label class="field">
                    <span>{{ $t('New password') }}</span>
                    <input
                        v-model="password.password"
                        type="password"
                        class="input"
                        :class="{ wrong: password.errors.password }"
                        autocomplete="new-password"
                        required
                    />
                    <span v-if="password.errors.password" class="field-error">
                        {{ password.errors.password }}
                    </span>
                </label>
                <label class="field">
                    <span>{{ $t('Repeat the new password') }}</span>
                    <input
                        v-model="password.password_confirmation"
                        type="password"
                        class="input"
                        :class="{ wrong: mismatch }"
                        autocomplete="new-password"
                        required
                    />
                    <span v-if="mismatch" class="field-error">{{
                        mismatch
                    }}</span>
                </label>
                <button
                    type="submit"
                    class="btn primary"
                    :disabled="password.processing"
                >
                    {{ $t('Change password') }}
                </button>
            </form>
        </div>
    </div>
</template>

<script lang="ts">
import { useForm } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import account from '@/routes/account';
import { toast } from '@/utils/toast';

/** The signed-in account: name and email (the login), and a new password (the current one is asked first). */
export default defineComponent({
    name: 'AccountCard',

    data() {
        const user = this.$page.props.auth.user;

        return {
            profile: useForm({
                name: user?.name ?? '',
                email: user?.email ?? '',
            }),
            password: useForm({
                current_password: '',
                password: '',
                password_confirmation: '',
            }),
        };
    },

    computed: {
        /** Said at once, before the server does. */
        mismatch(): string {
            const { password, password_confirmation } = this.password;

            return password_confirmation && password !== password_confirmation
                ? this.$t('The two passwords are not the same.')
                : '';
        },
    },

    methods: {
        saveProfile(): void {
            this.profile.submit(account.profile(), {
                preserveScroll: true,
                onSuccess: () => {
                    // The saved values become the "unchanged" state of the form.
                    this.profile.defaults();
                    toast(this.$t('Your account was saved.'));
                },
            });
        },

        savePassword(): void {
            this.password.submit(account.password(), {
                preserveScroll: true,
                onSuccess: () => {
                    this.password.reset();
                    toast(this.$t('Your password was changed.'));
                },
                onError: (errors) => {
                    if (errors.password) {
                        this.password.reset(
                            'password',
                            'password_confirmation',
                        );
                    }

                    if (errors.current_password) {
                        this.password.reset('current_password');
                    }
                },
            });
        },
    },
});
</script>
