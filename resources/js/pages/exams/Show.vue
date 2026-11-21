<template>
    <Head :title="$t(':level level exam', { level: level.name })" />

    <EmptyState
        v-if="state === 'unavailable'"
        icon="🤷"
        :title="$t('Exam not available')"
    />

    <template v-else>
        <PageHeader
            :title="$t('🎓 :level level exam', { level: level.name })"
            :sub="level.title"
            :back="learn().url"
        />
        <div>
            <EmptyState
                v-if="state === 'locked'"
                icon="🔒"
                :title="$t('Level locked')"
                :text="
                    $t(
                        'First pass the :level level exam or take the placement test.',
                        { level: previous ?? '' },
                    )
                "
            >
                <template #actions>
                    <Link class="btn primary" :href="placement().url">
                        {{ $t('Placement test') }}
                    </Link>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="state === 'review'"
                icon="📚"
                :title="$t('A little review first')"
                :text="
                    $t(
                        'On your last attempt you got questions wrong from the units below. Pass each unit test again and the exam unlocks.',
                    )
                "
            >
                <template #actions>
                    <Link
                        v-for="unit in reviewUnits"
                        :key="unit.id"
                        class="btn primary"
                        :href="unitRoute(unit.id).url"
                    >
                        {{
                            $t(':emoji Unit :n: :title', {
                                emoji: unit.emoji,
                                n: unit.order,
                                title: unit.title,
                            })
                        }}
                    </Link>
                </template>
            </EmptyState>

            <template v-else-if="phase === 'intro'">
                <EmptyState
                    icon="🎓"
                    :title="$t(':level level exam', { level: level.name })"
                    :text="
                        $t(
                            ':count questions from all units (3 from each). You need at least :pct%. If you don\'t pass, I\'ll show you exactly which units to review.',
                            { count: questions, pct: percent(passMark) },
                        )
                    "
                >
                    <template #actions>
                        <button
                            type="button"
                            class="btn primary big"
                            @click="start"
                        >
                            {{
                                passed
                                    ? $t('🔁 Take the exam again')
                                    : $t('▶ Start the exam')
                            }}
                        </button>
                    </template>
                </EmptyState>
                <div v-if="notPassed && !passed" class="alert warn">
                    {{
                        $t(
                            'You still have 1 unit not passed at this level. You can take the exam anyway if you already know the material — if you pass, you jump straight to the next level.|You still have :count units not passed at this level. You can take the exam anyway if you already know the material — if you pass, you jump straight to the next level.',
                            { count: notPassed },
                        )
                    }}
                </div>
                <p v-if="exam" class="muted center">
                    {{
                        $t(
                            'Best result: :pct% · 1 attempt|Best result: :pct% · :count attempts',
                            { pct: percent(exam.best), count: exam.tries },
                        )
                    }}
                </p>
            </template>

            <ExerciseSession
                v-else-if="phase === 'session'"
                :key="run"
                :items="shuffled"
                mode="test"
                :title="$t(':level level exam', { level: level.name })"
                @finish="finish"
                @exit="leave"
            />

            <SessionSave
                v-else-if="phase === 'result' && save"
                :key="run"
                :save="save"
                @saved="celebrate"
            >
                <ExamResult
                    v-if="answer"
                    :answer="answer"
                    :level="level"
                    :units="units"
                    :next="next"
                    :pass-mark="passMark"
                    @retry="retry"
                />
            </SessionSave>
        </div>
    </template>
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { addMistake, recordExam } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import ExamResult from '@/components/modules/units/ExamResult.vue';
import type { ResumableSave } from '@/components/modules/units/resumableSave';
import { resumableSave } from '@/components/modules/units/resumableSave';
import SessionSave from '@/components/modules/units/SessionSave.vue';
import type {
    ExamPageState,
    ExamUnit,
    KeyedItem,
    LevelTag,
} from '@/components/modules/units/types';
import { learn, placement } from '@/routes';
import { show as unitRoute } from '@/routes/units';
import type { ExamAnswer, ExamState, ExerciseResult } from '@/types';
import { confetti } from '@/utils/confetti';
import { shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';

/**
 * The final exam of a level: three questions from every unit. Failed, the units answered
 * badly go back to review; passed, the next level opens.
 */
export default defineComponent({
    name: 'ExamShowPage',

    components: {
        Head,
        Link,
        PageHeader,
        EmptyState,
        ExerciseSession,
        ExamResult,
        SessionSave,
    },

    props: {
        state: { type: String as PropType<ExamPageState>, required: true },
        level: { type: Object as PropType<LevelTag>, required: true },
        /** Name of the level before ("A1"), null for the first level. */
        previous: { type: String as PropType<string | null>, default: null },
        units: { type: Array as PropType<ExamUnit[]>, required: true },
        /** Units in review (a failed exam sent them back). */
        review: { type: Array as PropType<string[]>, required: true },
        exam: { type: Object as PropType<ExamState | null>, default: null },
        /** The exam of this level is passed. */
        passed: { type: Boolean, required: true },
        /** Units of the level not passed yet. */
        notPassed: { type: Number, required: true },
        /** Questions in the exam. */
        questions: { type: Number, required: true },
        items: { type: Array as PropType<KeyedItem[]>, required: true },
        /** The next level: its name and first unit (null after the last level). */
        next: {
            type: Object as PropType<{
                name: string;
                firstUnit: string | null;
            } | null>,
            default: null,
        },
        /** 0..1 */
        passMark: { type: Number, required: true },
    },

    data() {
        return {
            phase: 'intro' as 'intro' | 'session' | 'result',
            run: 0,
            shuffled: [] as KeyedItem[],
            answer: null as ExamAnswer | null,
            save: null as ResumableSave | null,
        };
    },

    computed: {
        reviewUnits(): ExamUnit[] {
            return this.units.filter((unit) => this.review.includes(unit.id));
        },
    },

    methods: {
        learn,
        placement,
        unitRoute,

        percent(value: number): number {
            return Math.round(value * 100);
        },

        start(): void {
            this.shuffled = shuffle(this.items);
            this.answer = null;
            this.run++;
            this.phase = 'session';
        },

        /** A new try gets new questions from the server. */
        retry(): void {
            router.reload({
                only: ['items', 'exam', 'passed'],
                onSuccess: this.start,
            });
        },

        leave(): void {
            router.visit(learn().url);
        },

        /** Wrong answers into "My mistakes", then the exam result. */
        finish(results: ExerciseResult[]): void {
            const wrong = results.flatMap((result) =>
                !result.correct && result.key ? [result.key] : [],
            );
            const answers = results.map((result) => ({
                unit: result.unit ?? '',
                correct: result.correct,
            }));

            this.save = markRaw(
                resumableSave([
                    ...(wrong.length ? [() => addMistake(wrong)] : []),
                    async () => {
                        this.answer = await recordExam(this.level.id, answers);
                    },
                ]),
            );
            this.phase = 'result';
        },

        celebrate(): void {
            if (this.answer?.passed) {
                confetti(3500);
                sfx('win');
            }
        },
    },
});
</script>
