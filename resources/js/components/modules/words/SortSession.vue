<template>
    <div v-if="phase === 'cards'" class="session">
        <div class="sess-top">
            <button
                type="button"
                class="icon-btn"
                :title="$t('Exit')"
                @click="finish(0)"
            >
                ✕
            </button>
            <div class="bar">
                <div class="bar-fill" :style="{ width: `${progress}%` }"></div>
            </div>
            <span class="sess-count">{{ counter }}</span>
        </div>
        <div v-if="title" class="sess-title">{{ title }}</div>
        <div class="fc-stage">
            <div
                v-if="word"
                :key="index"
                class="fc fade-in"
                :class="{ flipped: shown }"
                @click="reveal"
            >
                <div class="fc-face fc-front">
                    <WordPicture :word="word" />
                    <div class="fc-word">
                        {{ word.en }} <SpeakButton :text="word.en" />
                    </div>
                    <div class="vipa">
                        {{ word.ipa }} · <em>{{ word.pos }}</em>
                    </div>
                    <div class="fc-hint mt">
                        {{
                            $t('What does it mean? Think, then turn the card.')
                        }}
                    </div>
                </div>
                <div class="fc-face fc-back">
                    <div class="fc-word">{{ word.ro }}</div>
                    <div class="muted">
                        {{ word.en }} <SpeakButton :text="word.en" />
                    </div>
                    <div class="vex">
                        <SpeakButton :text="word.ex" />
                        <div>
                            <div class="en">{{ word.ex }}</div>
                            <div class="ro">{{ word.exRo }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="fc-buttons">
            <template v-if="shown">
                <button
                    type="button"
                    class="btn success big"
                    @click="answer(true)"
                >
                    {{ $t('✓ I knew it') }}<small>1</small>
                </button>
                <button
                    type="button"
                    class="btn warn big"
                    @click="answer(false)"
                >
                    {{ $t('🧠 New for me') }}<small>2</small>
                </button>
            </template>
            <button
                v-else
                type="button"
                class="btn primary big"
                @click="reveal"
            >
                {{ $t('Show the meaning (Space)') }}
            </button>
        </div>
    </div>

    <ExerciseSession
        v-else-if="phase === 'quiz'"
        :items="quiz"
        mode="practice"
        :title="$t('The new words: do you remember what they mean?')"
        @finish="quizDone"
        @exit="finish(0)"
    />

    <EmptyState
        v-else-if="phase === 'done'"
        icon="🗂️"
        :title="$t('Batch done!')"
        :text="summary"
    >
        <template #actions>
            <slot name="actions" />
        </template>
    </EmptyState>

    <EmptyState
        v-else-if="phase === 'failed'"
        icon="⚠️"
        :title="$t('Not everything was saved')"
        :text="
            $t(
                'The words that were not saved stay as they were. Check the connection and try again.',
            )
        "
    >
        <template #actions>
            <slot name="actions" />
        </template>
    </EmptyState>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, gradeWord, learnWord, sortedKnown } from '@/api/progress';
import WordPicture from '@/components/content/WordPicture.vue';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { ExerciseItem, ExerciseResult } from '@/types';
import { confetti } from '@/utils/confetti';
import { shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';
import { speak, stopSpeak } from '@/utils/speech';
import { reportNotSaved } from './saving';
import type { SortWord } from './types';

type Phase = 'cards' | 'quiz' | 'done' | 'failed';

/** XP for each word "I knew it", and for each new word right first time in the quiz. */
const KNOWN_XP = 1;
const QUIZ_XP = 3;

/** From this many words sorted, the end of the batch is celebrated. */
const CELEBRATE_FROM = 10;

/**
 * The quick sort of a batch (the original "sortSession"). Each card shows the English word;
 * the learner thinks of the meaning, turns the card, then says "I knew it" (learned, back on
 * the flashcards in a week as a check) or "New for me" (into the flashcards). The new words
 * then get one "what does it mean?" question each; the ones right first time move one box up.
 * XP at the end: 1 per word known + 3 per new word right first time.
 *
 * Emits "exit" when the learner leaves before sorting any word, and "saved" once everything
 * of the batch is saved (the page then reloads what it shows). The summary shows the
 * "actions" slot. A save that fails ends the batch at once with a warning instead of the
 * summary, so nothing pretends to be saved.
 */
export default defineComponent({
    name: 'SortSession',

    components: { EmptyState, ExerciseSession, SpeakButton, WordPicture },

    props: {
        words: { type: Array as PropType<SortWord[]>, required: true },
        title: { type: String, default: '' },
    },

    emits: ['exit', 'saved'],

    data() {
        return {
            phase: 'cards' as Phase,
            index: 0,
            shown: false,
            known: [] as SortWord[],
            fresh: [] as SortWord[],
            quiz: [] as ExerciseItem[],
            right: 0,
            quizFinished: false,
            ended: false,
            // Every save of the batch, so "saved" waits for all of them.
            saves: [] as Promise<unknown>[],
            settling: false,
        };
    },

    computed: {
        word(): SortWord | null {
            return this.words[this.index] ?? null;
        },

        progress(): number {
            return (this.index / this.words.length) * 100;
        },

        counter(): string {
            return `${Math.min(this.index + 1, this.words.length)} / ${this.words.length}`;
        },

        xp(): number {
            return this.known.length * KNOWN_XP + this.right * QUIZ_XP;
        },

        summary(): string {
            const parts: string[] = [];

            if (this.known.length) {
                parts.push(
                    this.$t(
                        '✓ 1 word you knew — it comes back once on the flashcards in a week, as a check.|✓ :count words you knew — they come back once on the flashcards in a week, as a check.',
                        { count: this.known.length },
                    ),
                );
            }

            if (this.fresh.length) {
                parts.push(
                    this.quizFinished
                        ? this.$t(
                              '🧠 1 new word went into your flashcards (:ok right first time).|🧠 :count new words went into your flashcards (:ok right first time).',
                              { count: this.fresh.length, ok: this.right },
                          )
                        : this.$t(
                              '🧠 1 new word went into your flashcards.|🧠 :count new words went into your flashcards.',
                              { count: this.fresh.length },
                          ),
                );
            }

            if (this.xp) {
                parts.push(`+${this.xp} XP ⚡`);
            }

            return parts.join(' · ');
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKey);
        this.showCard();
    },

    beforeUnmount() {
        this.stopKeys();
    },

    methods: {
        onKey(event: KeyboardEvent): void {
            if (!this.shown && (event.key === ' ' || event.key === 'Enter')) {
                event.preventDefault();
                this.reveal();
            } else if (this.shown && event.key === '1') {
                this.answer(true);
            } else if (this.shown && event.key === '2') {
                this.answer(false);
            }
        },

        stopKeys(): void {
            document.removeEventListener('keydown', this.onKey);
            stopSpeak();
        },

        showCard(): void {
            if (!this.word) {
                this.stopKeys();
                this.startQuiz();

                return;
            }

            this.shown = false;
            speak(this.word.en);
        },

        reveal(): void {
            if (this.shown || !this.word) {
                return;
            }

            this.shown = true;
            sfx('flip');
        },

        answer(knew: boolean): void {
            if (!this.shown || !this.word) {
                return;
            }

            const word = this.word;

            if (knew) {
                this.save(sortedKnown(word.en));
                this.known.push(word);
            } else {
                this.save(learnWord(word.en));
                this.fresh.push(word);
            }

            this.index++;
            this.showCard();
        },

        // The words that were new get one "what does it mean?" question each.
        startQuiz(): void {
            if (!this.fresh.length) {
                this.finish(0);

                return;
            }

            this.quiz = shuffle(this.fresh).map((word): ExerciseItem => ({
                key: word.en,
                ex: {
                    type: 'mc',
                    q: this.$t('What does **:en** mean?', { en: word.en }),
                    options: word.options,
                    answer: word.options.indexOf(word.ro),
                    explainRo: `**${word.en}** = ${word.ro}. ${word.ex}`,
                },
            }));
            this.phase = 'quiz';
        },

        quizDone(results: ExerciseResult[]): void {
            const right = results
                .filter((result) => result.correct)
                .map((result) => String(result.key));

            // Right first time: next review tomorrow; wrong: it stays for today.
            if (right.length) {
                this.save(gradeWord(right, 2));
            }

            this.finish(right.length, true);
        },

        finish(right: number, quizFinished = false): void {
            if (this.ended) {
                return;
            }

            this.ended = true;
            this.stopKeys();
            this.right = right;
            this.quizFinished = quizFinished;

            if (this.xp) {
                this.save(addXp(this.xp));
            }

            if (!this.known.length && !this.fresh.length) {
                this.$emit('exit');

                return;
            }

            if (this.known.length + this.fresh.length >= CELEBRATE_FROM) {
                confetti(1200);
                sfx('win');
            }

            this.phase = 'done';
            this.settle();
        },

        save(request: Promise<unknown>): void {
            this.saves.push(
                request.catch((error: unknown) => this.fail(error)),
            );
        },

        /** The first save that fails stops the batch: the learner sees what happened. */
        fail(error: unknown): void {
            if (this.phase === 'failed') {
                return;
            }

            reportNotSaved(error);
            this.ended = true;
            this.stopKeys();
            this.phase = 'failed';
            this.settle();
        },

        /** Once every save has an answer, the page reloads what it shows. */
        settle(): void {
            if (this.settling) {
                return;
            }

            this.settling = true;
            void Promise.allSettled(this.saves).then(() => this.$emit('saved'));
        },
    },
});
</script>
