<template>
    <Head :title="$t('Settings')" />
    <PageHeader
        :title="$t('⚙️ Settings')"
        :sub="$t('Saved automatically.')"
        :back="home().url"
    />

    <div class="card">
        <h3>{{ $t('🎨 Colour theme') }}</h3>
        <ThemePicker />
    </div>

    <div class="card">
        <h3>{{ $t('🌐 App language') }}</h3>
        <p class="muted">
            {{
                $t(
                    'Menus, buttons and messages. Grammar explanations and the translations inside lessons stay in Romanian, so you are sure to understand them.',
                )
            }}
        </p>
        <div class="row wrap">
            <button
                v-for="language in LANGUAGES"
                :key="language.code"
                class="btn"
                :class="{ primary: language.code === $page.props.locale }"
                @click="setLanguage(language.code)"
            >
                {{ language.label }}
            </button>
        </div>
    </div>

    <div class="card">
        <h3>{{ $t('🎯 Learning') }}</h3>
        <div class="settings">
            <label>
                {{ $t('Daily goal (XP)') }}
                <select @change="changeGoal">
                    <option
                        v-for="goal in GOALS"
                        :key="goal.xp"
                        :value="goal.xp"
                        :selected="goal.xp === $learner.goal"
                    >
                        {{ goal.xp }} XP · {{ $t(goal.name) }}
                    </option>
                </select>
            </label>
            <label>
                {{ $t('Sounds') }}
                <select @change="changeSound">
                    <option value="1" :selected="$settings.sound">
                        {{ $t('On') }}
                    </option>
                    <option value="0" :selected="!$settings.sound">
                        {{ $t('Off') }}
                    </option>
                </select>
            </label>
        </div>
    </div>

    <div class="card">
        <h3>{{ $t('🔊 Voice and microphone') }}</h3>
        <div class="settings">
            <label>
                {{ $t('The voice that reads English') }}
                <select @change="changeVoice">
                    <option value="">{{ $t('Automatic') }}</option>
                    <option
                        v-for="voice in voices"
                        :key="voice.name"
                        :value="voice.name"
                        :selected="$settings.voice === voice.name"
                    >
                        {{ voice.name }} ({{ voice.lang }})
                    </option>
                </select>
            </label>
            <label>
                {{ $t('Voice speed') }}
                <select @change="changeRate">
                    <option
                        v-for="rate in RATES"
                        :key="rate.value"
                        :value="rate.value"
                        :selected="$settings.rate === rate.value"
                    >
                        {{ $t(rate.name) }}
                    </option>
                </select>
            </label>
        </div>
        <MicTest v-if="canListen" />
        <p v-else class="muted mt">
            {{
                $t(
                    'This browser has no speech recognition. For the speaking exercises use Chrome or Edge.',
                )
            }}
        </p>
    </div>

    <ProgressBackup />
    <AccountCard />
</template>

<script lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import { setGoal, updateSettings } from '@/api/progress';
import PageHeader from '@/components/layout/PageHeader.vue';
import AccountCard from '@/components/modules/settings/AccountCard.vue';
import MicTest from '@/components/modules/settings/MicTest.vue';
import ProgressBackup from '@/components/modules/settings/ProgressBackup.vue';
import ThemePicker from '@/components/modules/settings/ThemePicker.vue';
import { home } from '@/routes';
import localeRoutes from '@/routes/locale';
import type { Settings } from '@/types';
import { setSettings } from '@/utils/learner';
import { canListen } from '@/utils/recognition';
import { englishVoices, speak, stopSpeak } from '@/utils/speech';

// Each language is named in itself.
const LANGUAGES = [
    { code: 'en', label: '🇬🇧 English' },
    { code: 'ro', label: '🇷🇴 Română' },
];

// The daily goals the server accepts, with their names (translation keys).
const GOALS = [
    { xp: 20, name: 'easy' },
    { xp: 50, name: 'regular' },
    { xp: 100, name: 'serious' },
    { xp: 150, name: 'intense' },
];

const RATES = [
    { value: 0.7, name: 'Slow' },
    { value: 0.9, name: 'Normal' },
    { value: 1.05, name: 'Fast' },
];

/**
 * Settings: colour theme, interface language, daily goal, sounds, the voice and its speed, a
 * microphone test, the learner's data (download, restore, delete) and the account. Every change
 * applies at once and is saved.
 */
export default defineComponent({
    name: 'SettingsPage',

    components: {
        AccountCard,
        Head,
        MicTest,
        PageHeader,
        ProgressBackup,
        ThemePicker,
    },

    data() {
        return {
            LANGUAGES,
            GOALS,
            RATES,
            canListen: canListen(),
            voices: englishVoices(),
        };
    },

    mounted() {
        // The browser may fill its voice list after the page has opened.
        window.speechSynthesis?.addEventListener(
            'voiceschanged',
            this.loadVoices,
        );
    },

    beforeUnmount() {
        window.speechSynthesis?.removeEventListener(
            'voiceschanged',
            this.loadVoices,
        );
        stopSpeak();
    },

    methods: {
        home,

        loadVoices(): void {
            this.voices = englishVoices();
        },

        /** Applies a setting at once (the voice and the speed are heard right away) and saves it. */
        change(patch: Partial<Settings>): void {
            setSettings({ ...this.$settings, ...patch });
            void updateSettings(patch).catch(() => null);
        },

        setLanguage(locale: string): void {
            router.put(
                localeRoutes.update().url,
                { locale },
                { preserveScroll: true },
            );
        },

        changeGoal(event: Event): void {
            void setGoal(
                Number((event.target as HTMLSelectElement).value),
            ).catch(() => null);
        },

        changeSound(event: Event): void {
            this.change({
                sound: (event.target as HTMLSelectElement).value === '1',
            });
        },

        changeVoice(event: Event): void {
            this.change({
                voice: (event.target as HTMLSelectElement).value || null,
            });
            speak('Hello! This is my voice.');
        },

        changeRate(event: Event): void {
            this.change({
                rate: Number((event.target as HTMLSelectElement).value),
            });
            speak('This is how fast I speak.');
        },
    },
});
</script>
