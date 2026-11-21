<template>
    <div v-if="stage === 'ready'">
        <EmptyState icon="⚡" :title="$t('Ready?')" :text="readyText">
            <template #actions>
                <button type="button" class="btn primary big" @click="start">
                    {{ $t('▶ Start') }}
                </button>
            </template>
        </EmptyState>
    </div>
    <div v-else-if="stage === 'running'">
        <div class="sprint-top">
            <div class="sprint-time" :class="{ low: left <= 10 }">
                {{ left }}
            </div>
            <div class="sprint-score">
                {{ $t('1 word|:count words', { count: score }) }}
            </div>
        </div>
        <div class="ex">
            <div class="sprint-word">
                <template v-if="shown"
                    >{{ shown.emoji }} {{ shown.ro }}</template
                >
            </div>
            <div class="sprint-hint" :class="{ shown: revealed }">
                {{ hint }}
            </div>
            <div class="mic-status">{{ heard }}</div>
            <div class="row center mt">
                <button type="button" class="btn" @click="reveal">
                    {{ $t("I don't know — show me") }}
                </button>
            </div>
        </div>
    </div>
    <div v-else>
        <SessionSave :save="saves" @saved="celebrate">
            <div class="result">
                <div class="big-level">{{ score }}</div>
                <h2>{{ endTitle }}</h2>
                <p class="muted">{{ endText }}</p>
                <div class="row center">
                    <button
                        type="button"
                        class="btn primary big"
                        @click="start"
                    >
                        {{ $t('🔁 Once more') }}
                    </button>
                    <Link class="btn" :href="back">{{ $t('Back') }}</Link>
                </div>
            </div>
        </SessionSave>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, speakRecord } from '@/api/progress';
import EmptyState from '@/components/general/EmptyState.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import { confetti } from '@/utils/confetti';
import { shuffle } from '@/utils/random';
import type { ListeningStream, StreamUpdate } from '@/utils/recognition';
import { listenStream, speechEngine } from '@/utils/recognition';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { sfx } from '@/utils/sound';
import type { SprintWord } from '@/utils/speaking';
import { letterHint, saidWord } from '@/utils/speaking';
import { speak } from '@/utils/speech';

const SECONDS = 60;
// The most XP a sprint gives (2 a word).
const MAX_XP = 20;

/**
 * The 60-second voice sprint: a word in Romanian, the learner says it in English while the
 * microphone listens the whole time. The score is kept as a record. Needs speech recognition.
 */
export default defineComponent({
    name: 'VoiceSprint',

    components: { EmptyState, Link, SessionSave },

    props: {
        words: { type: Array as PropType<SprintWord[]>, required: true },
        /** Where "Back" leads. */
        back: { type: String, required: true },
    },

    data() {
        return {
            stage: 'ready' as 'ready' | 'running' | 'over',
            pool: [] as SprintWord[],
            drawn: 0,
            // The word to say; null while a revealed word is shown.
            current: null as SprintWord | null,
            // The word on screen (stays while it is revealed).
            shown: null as SprintWord | null,
            hint: '',
            revealed: false,
            heard: '',
            left: SECONDS,
            score: 0,
            // Set when the microphone stopped the game.
            error: '',
            record: false,
            xp: 0,
            // The saves of the last game.
            saves: saveQueue() as SaveQueue,
            stream: null as ListeningStream | null,
            timer: undefined as ReturnType<typeof setInterval> | undefined,
            revealTimer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    computed: {
        readyText(): string {
            return this.$t(
                'You have 60 seconds. Recognition engine: :engine.',
                {
                    engine:
                        speechEngine() === 'local'
                            ? this.$t('on this computer')
                            : this.$t('online'),
                },
            );
        },

        endTitle(): string {
            if (this.error) {
                return this.$t('The microphone stopped');
            }

            return this.record && this.score
                ? this.$t('New record! 🏆')
                : this.$t('Time is up!');
        },

        endText(): string {
            return (
                this.error ||
                this.$t(
                    '1 word said · +:xp XP ⚡|:count words said · +:xp XP ⚡',
                    { count: this.score, xp: this.xp },
                )
            );
        },
    },

    beforeUnmount() {
        this.stop();
        clearTimeout(this.revealTimer);
    },

    methods: {
        start(): void {
            this.stop();
            this.pool = shuffle(this.words);
            this.drawn = 0;
            this.left = SECONDS;
            this.score = 0;
            this.error = '';
            this.record = false;
            this.heard = '';
            this.stage = 'running';
            this.stream = listenStream(this.onText, this.onStream);
            this.nextWord();
            this.timer = setInterval(this.tick, 1000);
        },

        /** Stops the clock and the microphone. */
        stop(): void {
            clearInterval(this.timer);
            this.stream?.stop();
            this.stream = null;
        },

        tick(): void {
            this.left--;

            if (this.left <= 0) {
                this.end('');
            }
        },

        nextWord(): void {
            const word = this.pool[this.drawn++ % this.pool.length] ?? null;
            this.current = word;
            this.shown = word;
            this.revealed = false;
            this.hint = word ? letterHint(word.en) : '';
            this.stream?.reset();
        },

        onText(text: string): void {
            this.heard = text ? `„${text}”` : '';

            if (this.current && saidWord(text, this.current.en)) {
                this.score++;
                sfx('ok');
                this.heard = `✅ ${this.current.en}`;
                this.nextWord();
            }
        },

        onStream(...update: StreamUpdate): void {
            if (update[0] === 'error') {
                const error = update[1];
                this.end(
                    this.$t(':text (code: :code)', {
                        text: error.text,
                        code: error.code,
                    }),
                );
            } else if (update[0] === 'info') {
                this.heard = update[1];
            }
        },

        /** "I don't know": show and say the word, so the round still teaches something, then move on. */
        reveal(): void {
            const word = this.current;

            if (!word) {
                return;
            }

            this.current = null;
            this.revealed = true;
            this.hint = word.en;
            speak(word.en);
            this.revealTimer = setTimeout(() => {
                if (this.stream) {
                    this.nextWord();
                }
            }, 1500);
        },

        end(error: string): void {
            this.stop();
            const score = this.score;
            this.xp = Math.min(MAX_XP, score * 2);
            this.error = error;
            this.record = false;
            this.saves = saveQueue();

            if (!error) {
                this.saves.add(async () => {
                    this.record = (await speakRecord('sprint', score)).record;
                });
            }

            if (this.xp) {
                const xp = this.xp;
                this.saves.add(() => addXp(xp));
            }

            this.stage = 'over';
        },

        /** The game is saved: celebrate a new record. */
        celebrate(): void {
            if (this.record && this.score) {
                confetti();
                sfx('win');
            }
        },
    },
});
</script>
