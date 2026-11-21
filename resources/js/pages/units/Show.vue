<template>
    <Head :title="unit.title" />

    <div class="unit-hero" :style="{ '--c': level.color }">
        <Link class="back" :href="learn().url">{{ $t('← All lessons') }}</Link>
        <div class="unit-hero-row">
            <div class="unit-hero-emoji">{{ unit.emoji }}</div>
            <div>
                <div class="muted">
                    {{
                        $t(':level · Unit :n', {
                            level: level.name,
                            n: unit.order,
                        })
                    }}
                </div>
                <h1>{{ unit.title }}</h1>
                <p class="muted">{{ unit.titleRo }}</p>
            </div>
        </div>
        <p class="goal">🎯 {{ unit.goalRo }}</p>
        <div v-if="unit.review" class="alert warn">
            {{
                $t(
                    '⚠️ You got some questions from this unit wrong in the exam. Review it and pass the unit test again.',
                )
            }}
        </div>
    </div>

    <div class="steps">
        <template v-for="(step, i) in steps" :key="step">
            <Link
                class="step-row"
                :class="{ done: isDone(step) }"
                :href="stepRoute({ unit: unit.id, step }).url"
            >
                <span class="step-icon">{{ info[step].icon }}</span>
                <span class="grow">
                    <strong>{{ i + 1 }}. {{ $t(info[step].name) }}</strong>
                    <small class="muted">{{ description(step) }}</small>
                </span>
                <ChipTag
                    v-if="step === 'test' && unit.best"
                    :kind="unit.passed ? 'ok' : 'warn'"
                >
                    {{ $t('best: :pct%', { pct: percent(unit.best) }) }}
                </ChipTag>
                <span class="step-check">{{ isDone(step) ? '✅' : '▶' }}</span>
            </Link>
            <Link
                v-if="step === 'vocab' && bank"
                class="step-row bank-row"
                :class="{ done: bank.new === 0 }"
                :href="bankRoute(unit.id).url"
                :style="{ '--c': level.color }"
            >
                <span class="step-icon">📚</span>
                <span class="grow">
                    <strong>
                        {{
                            $t('1+. Word bank: :count more words', {
                                count: bank.total,
                            })
                        }}
                    </strong>
                    <small class="muted">
                        {{
                            $t(
                                'Common :level words, 20 at a time — this is how you reach the real vocabulary of the level',
                                { level: level.name },
                            )
                        }}
                    </small>
                    <ProgressBar
                        :value="
                            (bank.learned + bank.learning * 0.5) / bank.total
                        "
                        :color="level.color"
                    />
                </span>
                <ChipTag :kind="bankChip">
                    {{ bank.learned }} / {{ bank.total }}
                </ChipTag>
                <span class="step-check">{{
                    bank.new === 0 ? '✅' : '▶'
                }}</span>
            </Link>
        </template>
        <Link
            class="step-row extra"
            :href="speaking({ mode: 'role', arg: unit.id }).url"
        >
            <span class="step-icon">🎭</span>
            <span class="grow">
                <strong>{{ $t('Bonus: voice role-play') }}</strong>
                <small class="muted">
                    {{
                        $t(
                            'Play one character of the dialogue: the app says the other lines, you say yours',
                        )
                    }}
                </small>
            </span>
            <ChipTag v-if="rolePlay !== null" kind="ok">
                {{ percent(rolePlay) }}%
            </ChipTag>
            <span class="step-check">🎙</span>
        </Link>
    </div>

    <VideoCard v-if="unit.video" :video="unit.video" />
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import VideoCard from '@/components/content/VideoCard.vue';
import ChipTag from '@/components/general/ChipTag.vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import { STEP_INFO, UNIT_STEPS } from '@/components/modules/units/steps';
import type {
    LevelTag,
    UnitOverview,
    WordCounts,
} from '@/components/modules/units/types';
import { learn, speaking } from '@/routes';
import { bank as bankRoute, step as stepRoute } from '@/routes/units';
import type { UnitStep } from '@/types';

/** A unit's overview: the six steps, the word bank, the role-play bonus and the video. */
export default defineComponent({
    name: 'UnitShowPage',

    components: { Head, Link, ChipTag, ProgressBar, VideoCard },

    props: {
        unit: { type: Object as PropType<UnitOverview>, required: true },
        level: { type: Object as PropType<LevelTag>, required: true },
        /** The word bank's counts (null when the unit has none). */
        bank: { type: Object as PropType<WordCounts | null>, default: null },
        /** Best score of the unit's role-play, 0..1 (null when never played). */
        rolePlay: { type: Number as PropType<number | null>, default: null },
        /** Pass mark of the unit test, 0..1. */
        passMark: { type: Number, required: true },
    },

    data() {
        return {
            steps: UNIT_STEPS,
            info: STEP_INFO,
        };
    },

    computed: {
        bankChip(): string {
            if (!this.bank) {
                return '';
            }

            if (this.bank.learned === this.bank.total) {
                return 'ok';
            }

            return this.bank.learned + this.bank.learning ? 'now' : 'lock';
        },
    },

    methods: {
        learn,
        speaking,
        bankRoute,
        stepRoute,

        isDone(step: UnitStep): boolean {
            return step === 'test' ? this.unit.passed : !!this.unit.steps[step];
        },

        description(step: UnitStep): string {
            if (step === 'grammar') {
                return this.unit.grammarTitle;
            }

            return this.$t(this.info[step].desc, {
                pct: this.percent(this.passMark),
            });
        },

        percent(value: number): number {
            return Math.round(value * 100);
        },
    },
});
</script>
