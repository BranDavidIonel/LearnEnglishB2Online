<template>
    <template v-if="phase === 'intro'">
        <EmptyState
            icon="🎯"
            :title="$t('Unit :n test', { n: unit.order })"
            :text="introText"
        >
            <template #actions>
                <button type="button" class="btn primary big" @click="start">
                    {{ $t('▶ Start the test') }}
                </button>
            </template>
        </EmptyState>
        <div v-if="test.missing.length" class="alert warn">
            {{
                $t(
                    "Tip: you haven't finished these steps yet: :steps. You can take the test anyway if you already know the material.",
                    { steps: missingNames },
                )
            }}
        </div>
        <p v-if="unit.best" class="muted center">
            {{
                $t(
                    'Best result so far: :pct% (1 attempt)|Best result so far: :pct% (:count attempts)',
                    { pct: percent(unit.best), count: unit.tries },
                )
            }}
        </p>
    </template>

    <ExerciseSession
        v-else-if="phase === 'session'"
        :key="run"
        :items="test.items"
        mode="test"
        :title="$t('🎯 Unit :n test', { n: unit.order })"
        @finish="finish"
        @exit="backToUnit"
    />

    <SessionSave
        v-else-if="phase === 'result' && save"
        :key="run"
        :save="save"
        @saved="celebrate"
    >
        <ResultCard
            v-if="answer"
            :score="score"
            :passed="answer.passed"
            :pass-mark="passMark"
            :title="
                answer.passed
                    ? $t('Unit passed! ⭐')
                    : $t('Not yet — practise a bit more')
            "
            :lines="lines"
        >
            <template #actions>
                <template v-if="answer.passed">
                    <Link class="btn primary big" :href="nextLink.href">
                        {{ nextLink.label }}
                    </Link>
                    <Link class="btn" :href="home().url">{{ $t('Home') }}</Link>
                </template>
                <template v-else>
                    <Link
                        class="btn primary"
                        :href="
                            stepRoute({ unit: unit.id, step: 'grammar' }).url
                        "
                    >
                        {{ $t('📐 Grammar') }}
                    </Link>
                    <Link
                        class="btn primary"
                        :href="
                            stepRoute({ unit: unit.id, step: 'practice' }).url
                        "
                    >
                        {{ $t('✏️ Exercises') }}
                    </Link>
                    <Link class="btn" :href="mistakes().url">
                        {{ $t('🩹 My mistakes') }}
                    </Link>
                    <button type="button" class="btn" @click="retry">
                        {{ $t('🔁 Try again') }}
                    </button>
                </template>
            </template>
        </ResultCard>
    </SessionSave>
</template>

<script lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { recordUnitTest, updateMistakes } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import { home, mistakes } from '@/routes';
import { show as examRoute } from '@/routes/exams';
import { show as unitRoute, step as stepRoute } from '@/routes/units';
import type { ExerciseResult, UnitTestAnswer } from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import type { ResumableSave } from './resumableSave';
import { resumableSave } from './resumableSave';
import SessionSave from './SessionSave.vue';
import { STEP_INFO } from './steps';
import type { LevelTag, UnitHead, UnitTestData } from './types';

/**
 * Step 6: the unit test. The server puts it together (half new questions, half from the
 * unit's exercises) and decides the result; a new try asks it for a new set of questions.
 */
export default defineComponent({
    name: 'StepTest',

    components: { Link, EmptyState, ExerciseSession, ResultCard, SessionSave },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        level: { type: Object as PropType<LevelTag>, required: true },
        test: { type: Object as PropType<UnitTestData>, required: true },
        /** 0..1 */
        passMark: { type: Number, required: true },
    },

    data() {
        return {
            phase: 'intro' as 'intro' | 'session' | 'result',
            run: 0,
            results: [] as ExerciseResult[],
            answer: null as UnitTestAnswer | null,
            save: null as ResumableSave | null,
        };
    },

    computed: {
        introText(): string {
            const values = {
                size: this.test.size,
                pct: this.percent(this.passMark),
            };

            return this.test.hasNew
                ? this.$t(
                      ":size questions: half from the unit's exercises, half new — the same grammar and words in sentences you haven't seen before. That shows whether you understood, not just remembered. You need at least :pct% to pass. There are no explanations after each question in the test.",
                      values,
                  )
                : this.$t(
                      ':size questions on everything you learned in this unit. You need at least :pct% to pass. There are no explanations after each question in the test.',
                      values,
                  );
        },

        missingNames(): string {
            return this.test.missing
                .map((step) => this.$t(STEP_INFO[step].name))
                .join(', ');
        },

        score(): number {
            return this.part(() => true).ok / this.results.length;
        },

        lines(): string[] {
            const fresh = new Set(
                this.test.items
                    .filter((item) => item.fresh)
                    .map((item) => item.key),
            );
            const isNew = (result: ExerciseResult): boolean =>
                fresh.has(result.key ?? '');
            const lines: string[] = [];

            if (fresh.size) {
                lines.push(
                    this.$t(
                        "New questions: **:new** · from the unit's exercises: **:seen**",
                        {
                            new: this.part(isNew).text,
                            seen: this.part((result) => !isNew(result)).text,
                        },
                    ),
                );
            }

            lines.push(
                this.answer?.passed
                    ? this.$t("You've unlocked the next step.")
                    : this.$t(
                          "No worries, that's how you learn. Go back to the **grammar** and **exercises**, then try again. The questions you got wrong are saved in “My mistakes”.",
                      ),
            );

            return lines;
        },

        /** Where a passed test leads: the next unit to review, the next unit, or the level exam. */
        nextLink(): { href: string; label: string } {
            const review = this.answer?.review[0];
            const reviewUnit = this.test.units.find((u) => u.id === review);

            if (reviewUnit) {
                return {
                    href: unitRoute(reviewUnit.id).url,
                    label: this.$t('Next to review: Unit :n →', {
                        n: reviewUnit.order,
                    }),
                };
            }

            const next = this.answer?.next;

            if (next) {
                return {
                    href: unitRoute(next.id).url,
                    label: this.$t('Unit :n: :title →', {
                        n: next.order,
                        title: next.title,
                    }),
                };
            }

            return {
                href: examRoute(this.level.id).url,
                label: this.$t('🎓 :level level exam →', {
                    level: this.level.name,
                }),
            };
        },
    },

    methods: {
        home,
        mistakes,
        stepRoute,

        percent(value: number): number {
            return Math.round(value * 100);
        },

        part(pick: (result: ExerciseResult) => boolean): {
            ok: number;
            text: string;
        } {
            const picked = this.results.filter(pick);
            const ok = picked.filter((result) => result.correct).length;

            return { ok, text: `${ok}/${picked.length}` };
        },

        start(): void {
            this.answer = null;
            this.results = [];
            this.run++;
            this.phase = 'session';
        },

        /** A new try gets a new set of questions from the server. */
        retry(): void {
            router.reload({ only: ['unit', 'test'], onSuccess: this.start });
        },

        /** Wrong answers into "My mistakes", right ones out, then the test result. */
        finish(results: ExerciseResult[]): void {
            this.results = results;

            const keys = (correct: boolean): string[] =>
                results.flatMap((result) =>
                    result.correct === correct && result.key
                        ? [result.key]
                        : [],
                );

            const score = this.score;
            this.save = markRaw(
                resumableSave([
                    () =>
                        updateMistakes({ add: keys(false), clear: keys(true) }),
                    async () => {
                        this.answer = await recordUnitTest(this.unit.id, score);
                    },
                ]),
            );
            this.phase = 'result';
        },

        celebrate(): void {
            if (this.answer?.passed) {
                confetti();
                sfx('win');
            }
        },

        backToUnit(): void {
            router.visit(unitRoute(this.unit.id).url);
        },
    },
});
</script>
