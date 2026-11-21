<template>
    <Head :title="$t('Dictation')" />
    <PageHeader
        :title="$t('🎧 Dictation')"
        :sub="$t('Listen and type the word. 10 words per round.')"
        :back="games().url"
    />
    <div>
        <GameResult
            v-if="over"
            :title="ok >= GREAT ? $t('Excellent! 🎉') : $t('Round over')"
            :line="`+${ok} XP ⚡`"
            :again="$t('🔁 New round')"
            @again="start"
        >
            <div class="big-level">{{ ok }}/{{ round.length }}</div>
        </GameResult>
        <div v-else-if="word" :key="index" class="ex fade-in">
            <div class="muted center">{{ index + 1 }} / {{ round.length }}</div>
            <div class="listen-box">
                <button class="btn round primary" @click="say(false)">
                    🔊
                </button>
                <button class="btn round" @click="say(true)">🐢</button>
                <span class="muted">
                    {{
                        $t('Hint: :emoji :ro', {
                            emoji: word.emoji,
                            ro: word.ro,
                        })
                    }}
                </span>
            </div>
            <input
                ref="input"
                v-model="typed"
                class="gap-input wide"
                :class="{ right: good === true, wrong: good === false }"
                type="text"
                autocomplete="off"
                spellcheck="false"
                autocapitalize="off"
                :placeholder="$t('Type the word…')"
                :disabled="answered"
                @keydown.enter.prevent.stop="check"
            />
            <div v-if="!answered" class="ex-foot">
                <button class="btn primary big" @click="check">
                    {{ $t('Check') }}
                </button>
            </div>
            <div class="spell-fb">
                <template v-if="answered">
                    <div class="feedback" :class="good ? 'good' : 'bad'">
                        <div class="fb-head">
                            {{ good ? $t('Correct! ✅') : $t('Not quite ❌') }}
                        </div>
                        <div class="fb-answer">
                            <strong>{{ word.en }}</strong> = {{ word.ro }}
                        </div>
                    </div>
                    <button
                        ref="continue"
                        class="btn primary big"
                        @click="check"
                    >
                        {{ $t('Continue →') }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, gameRecord } from '@/api/progress';
import PageHeader from '@/components/layout/PageHeader.vue';
import GameResult from '@/components/modules/games/GameResult.vue';
import type { GameWord } from '@/components/modules/games/types';
import { games } from '@/routes';
import { confetti } from '@/utils/confetti';
import { sample } from '@/utils/random';
import { sfx } from '@/utils/sound';
import { speak, stopSpeak } from '@/utils/speech';

const WORDS_PER_ROUND = 10;
// From this many words right the round is "excellent" (with confetti).
const GREAT = 8;
// The word is read a moment after it appears (the card fades in).
const SAY_DELAY = 200;

/** Dictation: listen to a word and type it, 10 words per round; one XP per word spelled right. */
export default defineComponent({
    name: 'GamesSpell',

    components: { GameResult, Head, PageHeader },

    props: {
        /** The words the learner has met (long phrases left out). */
        words: { type: Array as PropType<GameWord[]>, required: true },
    },

    data() {
        return {
            GREAT,
            round: [] as GameWord[],
            index: 0,
            ok: 0,
            typed: '',
            answered: false,
            good: null as boolean | null,
            over: false,
            sayTimer: undefined as ReturnType<typeof setTimeout> | undefined,
            endConfetti: null as null | (() => void),
        };
    },

    computed: {
        word(): GameWord | null {
            return this.round[this.index] ?? null;
        },
    },

    created() {
        this.start();
    },

    beforeUnmount() {
        clearTimeout(this.sayTimer);
        this.endConfetti?.();
        stopSpeak();
    },

    methods: {
        games,

        start(): void {
            this.round = sample(this.words, WORDS_PER_ROUND);
            this.index = 0;
            this.ok = 0;
            this.over = false;
            this.show();
        },

        show(): void {
            if (!this.word) {
                this.finish();

                return;
            }

            this.typed = '';
            this.answered = false;
            this.good = null;
            clearTimeout(this.sayTimer);
            this.sayTimer = setTimeout(() => this.say(false), SAY_DELAY);
        },

        say(slow: boolean): void {
            if (this.word) {
                speak(this.word.en, { slow });
            }

            (this.$refs.input as HTMLInputElement | undefined)?.focus();
        },

        /** Checks the word, or after the answer moves on to the next one (Enter and the buttons). */
        check(): void {
            const word = this.word;

            if (!word) {
                return;
            }

            if (this.answered) {
                this.index++;
                this.show();

                return;
            }

            if (!this.typed.trim()) {
                return;
            }

            this.answered = true;
            this.good =
                this.typed.trim().toLowerCase() === word.en.toLowerCase();

            if (this.good) {
                this.ok++;
            }

            sfx(this.good ? 'ok' : 'bad');
            this.$nextTick(() =>
                (this.$refs.continue as HTMLButtonElement | undefined)?.focus(),
            );
        },

        /** The record (more words right) and the XP are saved; the result needs neither answer. */
        finish(): void {
            void gameRecord('spell', this.ok).catch(() => null);

            if (this.ok) {
                void addXp(this.ok).catch(() => null);
            }

            this.over = true;

            if (this.ok >= GREAT) {
                this.endConfetti = confetti();
            }

            sfx('win');
        },
    },
});
</script>
