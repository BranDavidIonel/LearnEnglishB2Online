<template>
    <Head :title="$t('Placement test')" />

    <PageHeader
        :title="$t('🧭 Placement test')"
        :sub="$t('Find out where to start')"
        :back="home().url"
    />
    <div>
        <EmptyState
            v-if="!parts.length"
            icon="⏳"
            :title="$t('The test is not available yet')"
            :text="
                $t('The questions are loading. Reload the page in a moment.')
            "
        />

        <template v-else-if="phase === 'intro'">
            <EmptyState
                icon="🧭"
                :title="$t('How good is your English right now?')"
                :text="
                    $t(
                        'The test has up to :count parts of 12 questions each (:levels). If a part is too hard, the test stops there — it takes 5–15 minutes. Answer without a dictionary; if you don\'t know, pick what seems most likely.',
                        { count: parts.length, levels: partNames },
                    )
                "
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="runPart(0)"
                    >
                        {{ $t('▶ Start the test') }}
                    </button>
                </template>
            </EmptyState>
            <p v-if="last" class="muted center">
                {{
                    $t(
                        'Last result: starting level :level (:date). If you retake the test, the new result replaces it.',
                        {
                            level: levels[last.start]?.name ?? '',
                            date: last.date ?? '',
                        },
                    )
                }}
            </p>
        </template>

        <ExerciseSession
            v-else-if="phase === 'session'"
            :key="part"
            :items="parts[part].items"
            mode="test"
            :title="
                $t('Part :n of up to :count · :level level questions', {
                    n: part + 1,
                    count: parts.length,
                    level: partLevel(part).name,
                })
            "
            @finish="finishPart"
            @exit="leave"
        />

        <EmptyState
            v-else-if="phase === 'between'"
            icon="💪"
            :title="
                $t('Very good: :pct% at :level!', {
                    pct: percent(scores[parts[part].level] ?? 0),
                    level: partLevel(part).name,
                })
            "
            :text="
                $t('On to harder questions, at :level level.', {
                    level: partLevel(part + 1).name,
                })
            "
        >
            <template #actions>
                <button
                    type="button"
                    class="btn primary big"
                    @click="runPart(part + 1)"
                >
                    {{ $t('Continue →') }}
                </button>
            </template>
        </EmptyState>

        <SessionSave
            v-else-if="phase === 'result' && save"
            :save="save"
            @saved="celebrate"
        >
            <PlacementResult
                v-if="answer"
                :answer="answer"
                :levels="levels"
                :tested="parts.map((p) => p.level)"
                :scores="scores"
                :pass-mark="passMark"
            />
        </SessionSave>
    </div>
</template>

<script lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { recordPlacement } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PlacementResult from '@/components/modules/units/PlacementResult.vue';
import type { ResumableSave } from '@/components/modules/units/resumableSave';
import { resumableSave } from '@/components/modules/units/resumableSave';
import SessionSave from '@/components/modules/units/SessionSave.vue';
import type {
    PlacementLevel,
    PlacementPart,
} from '@/components/modules/units/types';
import { home } from '@/routes';
import type {
    ExerciseResult,
    LevelId,
    PlacementAnswer,
    PlacementState,
} from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';

/**
 * The placement test: one part of questions per level, from A1 up. A part passed leads to
 * the next, harder one; the first part not passed (or the last part) ends the test, and the
 * server works out where the learner starts.
 */
export default defineComponent({
    name: 'PlacementPage',

    components: {
        Head,
        PageHeader,
        EmptyState,
        ExerciseSession,
        PlacementResult,
        SessionSave,
    },

    props: {
        /** Every level of the course, in order. */
        levels: { type: Array as PropType<PlacementLevel[]>, required: true },
        /** The levels that have placement questions, in order. */
        parts: { type: Array as PropType<PlacementPart[]>, required: true },
        last: {
            type: Object as PropType<PlacementState | null>,
            default: null,
        },
        /** Score from which a level counts as known, 0..1. */
        passMark: { type: Number, required: true },
    },

    data() {
        return {
            phase: 'intro' as 'intro' | 'session' | 'between' | 'result',
            part: 0,
            scores: {} as Partial<Record<LevelId, number>>,
            answer: null as PlacementAnswer | null,
            save: null as ResumableSave | null,
        };
    },

    computed: {
        partNames(): string {
            return this.parts.map((_, i) => this.partLevel(i).name).join(' → ');
        },
    },

    methods: {
        home,

        percent(value: number): number {
            return Math.round(value * 100);
        },

        partLevel(i: number): PlacementLevel {
            const id = this.parts[i]?.level;

            return (
                this.levels.find((level) => level.id === id) ?? this.levels[0]
            );
        },

        runPart(i: number): void {
            this.part = i;
            this.phase = 'session';
        },

        leave(): void {
            router.visit(home().url);
        },

        finishPart(results: ExerciseResult[]): void {
            const level = this.parts[this.part].level;
            const score =
                results.filter((result) => result.correct).length /
                results.length;
            this.scores = { ...this.scores, [level]: score };

            if (score >= this.passMark && this.parts[this.part + 1]) {
                this.phase = 'between';
            } else {
                this.finish();
            }
        },

        finish(): void {
            const scores = this.scores;
            this.save = markRaw(
                resumableSave([
                    async () => {
                        this.answer = await recordPlacement(scores);
                    },
                ]),
            );
            this.phase = 'result';
        },

        celebrate(): void {
            confetti(1800);
            sfx('win');
        },
    },
});
</script>
