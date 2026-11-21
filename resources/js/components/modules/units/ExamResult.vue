<template>
    <ResultCard
        :score="answer.score"
        :passed="answer.passed"
        :pass-mark="passMark"
        :title="
            answer.passed
                ? $t('Congratulations! You passed level :level! 🏆', {
                      level: level.name,
                  })
                : $t('Not passed yet — but now you know exactly what to review')
        "
        :lines="[line]"
    >
        <div class="exam-units">
            <Link
                v-for="unit in units"
                :key="unit.id"
                class="exam-unit"
                :class="{ weak: answer.weak.includes(unit.id) }"
                :href="unitRoute(unit.id).url"
            >
                <span>{{ unit.emoji }} U{{ unit.order }}</span>
                <strong>{{ tally(unit.id) }}</strong>
            </Link>
        </div>

        <template #actions>
            <template v-if="answer.passed">
                <Link
                    v-if="next && next.firstUnit"
                    class="btn primary big"
                    :href="unitRoute(next.firstUnit).url"
                >
                    {{ $t('Start :level →', { level: next.name }) }}
                </Link>
                <Link v-else class="btn primary big" :href="home().url">
                    {{ $t('Home') }}
                </Link>
                <Link class="btn" :href="cambridge().url">
                    {{ $t('📝 Cambridge practice tests') }}
                </Link>
            </template>
            <template v-else>
                <Link
                    v-if="firstWeak"
                    class="btn primary big"
                    :href="unitRoute(firstWeak.id).url"
                >
                    {{ $t('Review Unit :n →', { n: firstWeak.order }) }}
                </Link>
                <button
                    v-else
                    type="button"
                    class="btn primary big"
                    @click="$emit('retry')"
                >
                    {{ $t('🔁 Try again') }}
                </button>
                <Link class="btn" :href="mistakes().url">
                    {{ $t('🩹 My mistakes') }}
                </Link>
            </template>
        </template>
    </ResultCard>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import { cambridge, home, mistakes } from '@/routes';
import { show as unitRoute } from '@/routes/units';
import type { ExamAnswer } from '@/types';
import type { ExamUnit, LevelTag } from './types';

/**
 * The result of a level exam: the score, every unit with its right answers (the weak ones in
 * red), and where to go next — the next level, or the units to review.
 */
export default defineComponent({
    name: 'ExamResult',

    components: { Link, ResultCard },

    props: {
        answer: { type: Object as PropType<ExamAnswer>, required: true },
        level: { type: Object as PropType<LevelTag>, required: true },
        units: { type: Array as PropType<ExamUnit[]>, required: true },
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

    emits: ['retry'],

    computed: {
        firstWeak(): ExamUnit | null {
            return (
                this.units.find((unit) => unit.id === this.answer.weak[0]) ??
                null
            );
        },

        line(): string {
            if (this.answer.passed) {
                return this.next?.firstUnit
                    ? this.$t('Level **:level** is now unlocked.', {
                          level: this.next.name,
                      })
                    : this.$t("You've finished the whole course!");
            }

            return this.answer.weak.length
                ? this.$t(
                      'Back to learning: review **1 unit** (marked in red) and pass their tests again. Then you can retake the exam.|Back to learning: review **:count units** (marked in red) and pass their tests again. Then you can retake the exam.',
                      { count: this.answer.weak.length },
                  )
                : this.$t(
                      'You were close. Go over your mistakes and try again.',
                  );
        },
    },

    methods: {
        cambridge,
        home,
        mistakes,
        unitRoute,

        tally(unitId: string): string {
            const answers = this.answer.byUnit[unitId] ?? { ok: 0, n: 0 };

            return `${answers.ok}/${answers.n}`;
        },
    },
});
</script>
