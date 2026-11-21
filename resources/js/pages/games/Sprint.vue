<template>
    <Head title="Sprint 60" />
    <PageHeader
        title="⚡ Sprint 60"
        :sub="
            $t(
                'Pick the right translation. Streaks of correct answers earn bonus points.',
            )
        "
        :back="games().url"
    />
    <div>
        <EmptyState
            v-if="state === 'ready'"
            icon="⚡"
            :title="$t('Ready?')"
            :text="$t('You have 60 seconds.')"
        >
            <template #actions>
                <button class="btn primary big" @click="start">
                    {{ $t('▶ Start') }}
                </button>
            </template>
        </EmptyState>
        <template v-else-if="state === 'playing'">
            <div class="sprint-top">
                <div class="sprint-time" :class="{ low: left <= LOW_TIME }">
                    {{ left }}
                </div>
                <div class="sprint-score">{{ scoreText }}</div>
            </div>
            <div>
                <div v-if="question" :key="asked" class="ex fade-in">
                    <div class="sprint-word">
                        {{ question.word.emoji }} {{ question.word.en }}
                    </div>
                    <div class="opts">
                        <button
                            v-for="(option, i) in question.options"
                            :key="i"
                            class="opt"
                            :class="{ wrong: wrongPicks.includes(i) }"
                            @click="choose(option, i)"
                        >
                            <span>{{ option.ro }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <GameResult
            v-else-if="state === 'over'"
            :title="record && score ? $t('New record! 🏆') : $t('Time\'s up!')"
            :line="
                $t(
                    ':count correct answer · +:xp XP ⚡|:count correct answers · +:xp XP ⚡',
                    {
                        count: right,
                        xp,
                    },
                )
            "
            :again="$t('🔁 Play again')"
            @again="start"
        >
            <div class="big-level">{{ score }}</div>
        </GameResult>
    </div>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, gameRecord } from '@/api/progress';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import GameResult from '@/components/modules/games/GameResult.vue';
import type { GameWord } from '@/components/modules/games/types';
import { games } from '@/routes';
import { confetti } from '@/utils/confetti';
import { pick, sample, shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';

interface Question {
    word: GameWord;
    options: GameWord[];
}

const SECONDS = 60;
const LOW_TIME = 10;
const WRONG_OPTIONS = 3;
// Every 5 right answers in a row add one bonus point per answer.
const STREAK = 5;
const MAX_XP = 20;
// The next word comes at once after a right answer, a little later after a wrong one (to see it).
const NEXT_AFTER_RIGHT_MS = 80;
const NEXT_AFTER_WRONG_MS = 450;

/** Sprint 60: pick the Romanian translation of as many words as possible in 60 seconds. */
export default defineComponent({
    name: 'GamesSprint',

    components: { EmptyState, GameResult, Head, PageHeader },

    props: {
        /** The words the learner has met. */
        words: { type: Array as PropType<GameWord[]>, required: true },
    },

    data() {
        return {
            LOW_TIME,
            alive: true,
            state: 'ready' as 'ready' | 'playing' | 'over',
            left: SECONDS,
            score: 0,
            combo: 0,
            right: 0,
            xp: 0,
            record: false,
            question: null as Question | null,
            // A new number for every word, so it fades in.
            asked: 0,
            wrongPicks: [] as number[],
            // Set after an answer until the next word comes, so one word is never scored twice.
            waiting: false,
            timer: undefined as ReturnType<typeof setInterval> | undefined,
            nextTimer: undefined as ReturnType<typeof setTimeout> | undefined,
            endConfetti: null as null | (() => void),
        };
    },

    computed: {
        scoreText(): string {
            const points = this.$t(':count point|:count points', {
                count: this.score,
            });

            return this.combo >= STREAK
                ? `${points}${this.$t(' · 🔥 streak :n', { n: this.combo })}`
                : points;
        },
    },

    beforeUnmount() {
        this.alive = false;
        this.stopTimers();
        this.endConfetti?.();
    },

    methods: {
        games,

        start(): void {
            this.stopTimers();
            this.left = SECONDS;
            this.score = 0;
            this.combo = 0;
            this.right = 0;
            this.state = 'playing';
            this.ask();
            this.timer = setInterval(this.tick, 1000);
        },

        ask(): void {
            const word = pick(this.words);
            const others = sample(
                this.words.filter((other) => other.ro !== word.ro),
                WRONG_OPTIONS,
            );
            this.question = { word, options: shuffle([word, ...others]) };
            this.wrongPicks = [];
            this.waiting = false;
            this.asked++;
        },

        choose(option: GameWord, index: number): void {
            if (this.waiting || !this.question) {
                return;
            }

            const ok = option === this.question.word;
            this.waiting = true;

            if (ok) {
                this.combo++;
                this.right++;
                this.score += 1 + Math.floor(this.combo / STREAK);
                sfx('ok');
            } else {
                this.combo = 0;
                sfx('bad');
                this.wrongPicks.push(index);
            }

            this.nextTimer = setTimeout(
                this.ask,
                ok ? NEXT_AFTER_RIGHT_MS : NEXT_AFTER_WRONG_MS,
            );
        },

        tick(): void {
            this.left--;

            if (this.left <= 0) {
                void this.finish();
            }
        },

        /** Time is up: the record (more points), the XP (one per right answer, at most 20), the result. */
        async finish(): Promise<void> {
            this.stopTimers();
            this.question = null;
            this.xp = Math.min(MAX_XP, this.right);
            const answer = await gameRecord('sprint', this.score).catch(
                () => null,
            );

            if (this.xp) {
                void addXp(this.xp).catch(() => null);
            }

            if (!this.alive) {
                return;
            }

            this.record = answer?.record ?? false;
            this.state = 'over';

            if (this.record && this.score) {
                this.endConfetti = confetti();
            }

            sfx('win');
        },

        stopTimers(): void {
            clearInterval(this.timer);
            clearTimeout(this.nextTimer);
        },
    },
});
</script>
