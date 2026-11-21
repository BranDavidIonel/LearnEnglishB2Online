<template>
    <ExerciseSession
        v-if="!results"
        :key="run"
        :items="items"
        mode="practice"
        @finish="finish"
        @exit="backToUnit"
    />

    <SessionSave v-else-if="save" :key="run" :save="save">
        <ResultCard :score="score" :title="title" :lines="lines">
            <template #actions>
                <Link
                    class="btn primary big"
                    :href="stepRoute({ unit: unit.id, step: 'reading' }).url"
                >
                    {{ $t('Next: Reading →') }}
                </Link>
                <button type="button" class="btn" @click="redo">
                    {{ $t('🔁 Redo the exercises') }}
                </button>
                <Link
                    v-if="score < 0.6"
                    class="btn"
                    :href="stepRoute({ unit: unit.id, step: 'grammar' }).url"
                >
                    {{ $t('📐 Reread the grammar') }}
                </Link>
            </template>
        </ResultCard>
    </SessionSave>
</template>

<script lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { addXp, completeStep, updateMistakes } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import { show as unitRoute, step as stepRoute } from '@/routes/units';
import type { ExerciseResult } from '@/types';
import { confetti } from '@/utils/confetti';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { ANSWER_XP } from './steps';
import type { KeyedItem, UnitHead } from './types';

/**
 * Step 4: every exercise of the unit, with an explanation after each answer. Answers right
 * on the first try give XP and leave "My mistakes"; wrong ones go there ("match" excepted).
 */
export default defineComponent({
    name: 'StepPractice',

    components: { Link, ExerciseSession, ResultCard, SessionSave },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        items: { type: Array as PropType<KeyedItem[]>, required: true },
    },

    data() {
        return {
            results: null as ExerciseResult[] | null,
            // A new run of the same exercises remounts the session.
            run: 0,
            save: null as SaveQueue | null,
        };
    },

    computed: {
        right(): number {
            return (this.results ?? []).filter((result) => result.correct)
                .length;
        },

        score(): number {
            return this.results ? this.right / this.results.length : 0;
        },

        title(): string {
            if (this.score >= 0.8) {
                return this.$t('Very good! 💪');
            }

            return this.score >= 0.5
                ? this.$t('Not bad! A bit more practice.')
                : this.$t("It's hard at first — that's normal.");
        },

        lines(): string[] {
            return [
                this.$t(':ok of :total right on the first try · +:xp XP ⚡', {
                    ok: this.right,
                    total: this.results?.length ?? 0,
                    xp: this.right * ANSWER_XP,
                }),
                this.score < 0.6
                    ? this.$t(
                          'Tip: reread the grammar and redo the exercises before the test.',
                      )
                    : '',
            ];
        },
    },

    methods: {
        stepRoute,

        finish(results: ExerciseResult[]): void {
            this.results = results;
            this.save = markRaw(saveQueue(this.requests(results)));

            if (this.score >= 0.8) {
                confetti(1500);
            }
        },

        /** Mistakes in and out, the step done (no XP of its own), then the XP of the answers. */
        requests(results: ExerciseResult[]): (() => Promise<unknown>)[] {
            const clear = this.keys(results.filter((result) => result.correct));
            const add = this.keys(
                results.filter(
                    (result) => !result.correct && result.ex.type !== 'match',
                ),
            );
            const xp = this.right * ANSWER_XP;

            return [
                ...(clear.length || add.length
                    ? [() => updateMistakes({ add, clear })]
                    : []),
                () => completeStep(this.unit.id, 'practice'),
                ...(xp ? [() => addXp(xp)] : []),
            ];
        },

        keys(results: ExerciseResult[]): string[] {
            return results.flatMap((result) =>
                result.key ? [result.key] : [],
            );
        },

        redo(): void {
            this.results = null;
            this.save = null;
            this.run++;
        },

        backToUnit(): void {
            router.visit(unitRoute(this.unit.id).url);
        },
    },
});
</script>
