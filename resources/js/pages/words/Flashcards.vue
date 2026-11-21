<template>
    <Head :title="$t('Flashcards')" />
    <PageHeader
        :title="$t('🗂️ Flashcards')"
        :sub="
            $t(
                'Spaced repetition: words you know come back less often, hard ones more often.',
            )
        "
        :back="home().url"
    />

    <div>
        <div v-if="view === 'session' && card" class="session">
            <div class="sess-top">
                <button type="button" class="icon-btn" @click="leave">✕</button>
                <div class="bar">
                    <div
                        class="bar-fill"
                        :style="{ width: `${(done / total) * 100}%` }"
                    ></div>
                </div>
            </div>
            <div class="fc-stage">
                <div
                    :key="turn"
                    class="fc fade-in"
                    :class="{ flipped }"
                    @click="flipped || flip()"
                >
                    <div v-if="reverse" class="fc-face fc-front">
                        <div class="fc-hint">
                            {{ $t('🇷🇴 How do you say it in English?') }}
                        </div>
                        <div class="fc-word">{{ card.ro }}</div>
                    </div>
                    <div v-else class="fc-face fc-front">
                        <WordPicture :word="card" />
                        <div class="fc-word">
                            {{ card.en }} <SpeakButton :text="card.en" />
                        </div>
                        <div class="vipa">{{ card.ipa }}</div>
                    </div>
                    <div class="fc-face fc-back">
                        <div v-if="reverse" class="fc-word">
                            {{ card.en }} <SpeakButton :text="card.en" />
                        </div>
                        <div v-else class="fc-word">{{ card.ro }}</div>
                        <div v-if="reverse" class="vipa">{{ card.ipa }}</div>
                        <div v-else class="muted">{{ card.en }}</div>
                        <div class="vex">
                            <SpeakButton :text="card.ex" />
                            <div>
                                <div class="en">{{ card.ex }}</div>
                                <div class="ro">{{ card.exRo }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="fc-buttons">
                <template v-if="flipped">
                    <button
                        type="button"
                        class="btn danger big"
                        @click="grade(0)"
                    >
                        {{ $t("😕 Didn't know") }}<small>1</small>
                    </button>
                    <button
                        type="button"
                        class="btn warn big"
                        @click="grade(1)"
                    >
                        {{ $t('🤔 Hard') }}<small>2</small>
                    </button>
                    <button
                        type="button"
                        class="btn success big"
                        @click="grade(2)"
                    >
                        {{ $t('😀 Knew it') }}<small>3</small>
                    </button>
                </template>
                <button
                    v-else
                    type="button"
                    class="btn primary big"
                    @click="flip"
                >
                    {{ $t('Flip the card (Space)') }}
                </button>
            </div>
        </div>

        <EmptyState
            v-else-if="view === 'done'"
            icon="🎉"
            :title="$t('Session complete!')"
            :text="
                $t(
                    'You reviewed 1 word · +1 XP ⚡|You reviewed :count words · +:count XP ⚡',
                    { count: total },
                )
            "
        >
            <template #actions>
                <button
                    type="button"
                    class="btn primary big"
                    @click="view = 'intro'"
                >
                    {{ $t('Continue') }}
                </button>
                <Link class="btn" :href="home().url">{{ $t('Home') }}</Link>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="view === 'failed'"
            icon="⚠️"
            :title="$t('Not everything was saved')"
            :text="
                $t(
                    'The cards that were not saved keep their review day. Check the connection and try again.',
                )
            "
        >
            <template #actions>
                <button
                    type="button"
                    class="btn primary big"
                    @click="view = 'intro'"
                >
                    {{ $t('Continue') }}
                </button>
                <Link class="btn" :href="home().url">{{ $t('Home') }}</Link>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!inDeck"
            icon="🗂️"
            :title="$t('No flashcards yet')"
            :text="
                $t(
                    'Words are added here automatically when you finish the “New words” step of a unit.',
                )
            "
        >
            <template #actions>
                <Link class="btn primary" :href="learn().url">
                    {{ $t('Go to lessons') }}
                </Link>
            </template>
        </EmptyState>

        <template v-else>
            <div class="stats">
                <div class="card stat">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-num">{{ dueCount }}</div>
                    <div class="muted">{{ $t('to review today') }}</div>
                </div>
                <div class="card stat">
                    <div class="stat-icon">🌱</div>
                    <div class="stat-num">{{ counts.learning }}</div>
                    <div class="muted">{{ $t('being learned') }}</div>
                </div>
                <div class="card stat">
                    <div class="stat-icon">✅</div>
                    <div class="stat-num">{{ counts.learned }}</div>
                    <div class="muted">{{ $t('learned') }}</div>
                </div>
            </div>
            <EmptyState
                :icon="dueCount ? '⏰' : '🎉'"
                :title="
                    dueCount
                        ? $t(
                              '1 flashcard is waiting for you|:count flashcards are waiting for you',
                              { count: dueCount },
                          )
                        : $t('You\'re done reviewing for today!')
                "
                :text="
                    dueCount
                        ? $t(
                              'Look at the word, say the translation in your head, then flip the card.',
                          )
                        : $t(
                              'Come back tomorrow — or practise some random words.',
                          )
                "
            >
                <template #actions>
                    <button
                        v-if="dueCount"
                        type="button"
                        class="btn primary big"
                        @click="start(due, true)"
                    >
                        {{ $t('▶ Start review') }}
                    </button>
                    <button
                        type="button"
                        class="btn"
                        @click="start(practice, false)"
                    >
                        {{ $t('🎲 Free practice (20 words)') }}
                    </button>
                    <label class="check">
                        <input v-model="reverse" type="checkbox" />
                        {{ $t('Reverse: Romanian → English') }}
                    </label>
                </template>
            </EmptyState>
        </template>
    </div>
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, gradeWord } from '@/api/progress';
import WordPicture from '@/components/content/WordPicture.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import type { WordCounts } from '@/components/modules/words/types';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { home, learn } from '@/routes';
import type { WordGrade, WordWithState } from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import { speak, stopSpeak } from '@/utils/speech';

type View = 'intro' | 'session' | 'done' | 'failed';

/** XP for each card answered "hard" or "knew it". */
const CARD_XP = 1;

/**
 * The flashcards (the original "cardsView"): today's reviews with spaced repetition, or free
 * practice with random words met so far, optionally Romanian → English. A card answered
 * "didn't know" comes back at the end of the session. Space / Enter flips, 1–3 grade.
 * After a session the page reloads its numbers and cards.
 */
export default defineComponent({
    name: 'FlashcardsPage',

    components: {
        EmptyState,
        Head,
        Link,
        PageHeader,
        SpeakButton,
        WordPicture,
    },

    props: {
        /** Words in the deck (also the ones no longer in the course). */
        inDeck: { type: Number, required: true },
        dueCount: { type: Number, required: true },
        counts: { type: Object as PropType<WordCounts>, required: true },
        /** The cards of the next review, most overdue first. */
        due: { type: Array as PropType<WordWithState[]>, required: true },
        /** Random words for free practice. */
        practice: { type: Array as PropType<WordWithState[]>, required: true },
    },

    data() {
        return {
            view: 'intro' as View,
            reverse: false,
            scheduled: false,
            queue: [] as WordWithState[],
            card: null as WordWithState | null,
            total: 0,
            done: 0,
            flipped: false,
            // A new key for every card shown, so it fades in even when the same word comes back.
            turn: 0,
            saves: [] as Promise<unknown>[],
        };
    },

    beforeUnmount() {
        this.stop();
    },

    methods: {
        home,
        learn,

        start(words: WordWithState[], scheduled: boolean): void {
            this.queue = words.slice();
            this.total = words.length;
            this.done = 0;
            this.scheduled = scheduled;
            this.saves = [];
            this.view = 'session';
            document.addEventListener('keydown', this.onKey);
            this.show();
        },

        onKey(event: KeyboardEvent): void {
            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();

                if (!this.flipped) {
                    this.flip();
                }
            } else if (this.flipped && ['1', '2', '3'].includes(event.key)) {
                this.grade((Number(event.key) - 1) as WordGrade);
            }
        },

        stop(): void {
            document.removeEventListener('keydown', this.onKey);
            stopSpeak();
        },

        show(): void {
            const next = this.queue.shift();

            if (!next) {
                this.finish();

                return;
            }

            this.card = next;
            this.flipped = false;
            this.turn++;

            if (!this.reverse) {
                speak(next.en);
            }
        },

        flip(): void {
            if (!this.card) {
                return;
            }

            this.flipped = true;
            sfx('flip');

            if (this.reverse) {
                speak(this.card.en);
            }
        },

        grade(grade: WordGrade): void {
            if (!this.card) {
                return;
            }

            if (this.scheduled) {
                this.save(gradeWord(this.card.en, grade));
            }

            if (grade === 0) {
                this.queue.push(this.card);
            } else {
                this.done++;
                this.save(addXp(CARD_XP));
            }

            this.show();
        },

        finish(): void {
            this.stop();
            confetti(1500);
            sfx('win');
            this.view = 'done';
            this.refresh();
        },

        /** ✕: back to the start of the page; what was graded stays graded. */
        leave(): void {
            this.stop();
            this.view = 'intro';
            this.refresh();
        },

        /** Once everything is saved, the numbers and the cards of the page are read again. */
        refresh(): void {
            this.card = null;
            void Promise.allSettled(this.saves).then(() => router.reload());
        },

        save(request: Promise<unknown>): void {
            this.saves.push(request.catch(() => this.fail()));
        },

        /**
         * A grade that could not be saved ends the session at once, so the cards and the end
         * screen never show progress that was not kept.
         */
        fail(): void {
            if (this.view === 'session' || this.view === 'done') {
                this.stop();
                this.view = 'failed';
                this.refresh();
            }
        },
    },
});
</script>
