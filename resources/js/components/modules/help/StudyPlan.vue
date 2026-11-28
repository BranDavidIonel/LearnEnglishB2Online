<template>
    <div class="card notice">
        <p class="help-intro" v-html="md(plan.introRo)"></p>
    </div>
    <section class="card">
        <h2>
            {{ $t('Every day') }}
            <small class="muted">{{
                $t('about :n minutes', { n: minutes })
            }}</small>
        </h2>
        <div class="plan-list">
            <Link
                v-for="(step, index) in plan.daily"
                :key="step.titleRo"
                :href="url(step)"
                class="plan-row"
            >
                <span class="plan-n">{{ index + 1 }}</span>
                <span class="plan-emoji">{{ step.emoji }}</span>
                <span class="plan-body">
                    <strong>{{ step.titleRo }}</strong>
                    <span v-html="md(step.textRo)"></span>
                </span>
                <ChipTag v-if="step.minutes">{{
                    $t(':n min', { n: step.minutes })
                }}</ChipTag>
            </Link>
        </div>
        <p class="hint-box" v-html="md(plan.shortDayRo)"></p>
    </section>
    <section v-if="plan.goals.length" class="card">
        <h2>{{ $t('Where you are now') }}</h2>
        <div class="to-goals">
            <div v-for="goal in plan.goals" :key="goal.level.id">
                <VocabGoal :stats="goal.vocab" :level="goal.level" />
                <p class="muted small plan-goal">{{ pace(goal) }}</p>
            </div>
        </div>
    </section>
    <section class="card">
        <h2>{{ $t('Every week') }}</h2>
        <div class="plan-list">
            <Link
                v-for="step in plan.weekly"
                :key="step.titleRo"
                :href="url(step)"
                class="plan-row"
            >
                <span class="plan-emoji">{{ step.emoji }}</span>
                <span class="plan-body">
                    <strong>{{ step.titleRo }}</strong>
                    <span v-html="md(step.textRo)"></span>
                </span>
            </Link>
        </div>
    </section>
    <section class="card">
        <h2>{{ $t('Along the way') }}</h2>
        <div class="plan-list">
            <Link
                v-for="step in plan.milestones"
                :key="step.titleRo"
                :href="url(step)"
                class="plan-row"
            >
                <span class="plan-emoji">{{ step.emoji }}</span>
                <span class="plan-body">
                    <strong>{{ step.titleRo }}</strong>
                    <span v-html="md(step.textRo)"></span>
                </span>
            </Link>
        </div>
    </section>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import VocabGoal from '@/components/general/VocabGoal.vue';
import type {
    PlanGoal,
    PlanLink,
    PlanStep,
    StudyPlanData,
} from '@/components/modules/help/types';
import {
    cambridge,
    flashcards,
    grammar,
    learn,
    mistakes,
    progress,
    speaking,
    words,
} from '@/routes';
import { md } from '@/utils/text';

/** Where each line of the plan sends the learner. */
const LINKS: Record<PlanLink, () => string> = {
    flashcards: () => flashcards().url,
    words: () => words().url,
    learn: () => learn().url,
    speaking: () => speaking().url,
    mistakes: () => mistakes().url,
    cambridge: () => cambridge().url,
    grammar: () => grammar().url,
    progress: () => progress().url,
};

/** The daily plan: what to do every day, every week and along the way, each line a link to its part of the app. */
export default defineComponent({
    name: 'StudyPlan',

    components: { ChipTag, Link, VocabGoal },

    props: {
        plan: { type: Object as PropType<StudyPlanData>, required: true },
    },

    computed: {
        /** The minutes of a whole day of the plan. */
        minutes(): number {
            return this.plan.daily.reduce(
                (sum, step) => sum + (step.minutes ?? 0),
                0,
            );
        },
    },

    methods: {
        md,

        url(step: PlanStep): string {
            return (LINKS[step.link] ?? LINKS.learn)();
        },

        pace(goal: PlanGoal): string {
            if (goal.days === 0) {
                return this.$t(
                    'You have met every :level word. Keep reviewing them.',
                    { level: goal.level.name },
                );
            }

            return this.$t(
                'With :batch new words a day: about :days more days until you have met every :level word.',
                {
                    batch: this.plan.batch,
                    days: goal.days,
                    level: goal.level.name,
                },
            );
        },
    },
});
</script>
