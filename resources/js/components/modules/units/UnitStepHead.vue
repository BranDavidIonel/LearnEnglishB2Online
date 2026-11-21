<template>
    <div class="step-head" :style="{ '--c': color }">
        <Link class="back" :href="show(unit.id).url">
            ← {{ unit.emoji }} {{ unit.title }}
        </Link>
        <div class="step-dots">
            <Link
                v-for="step in steps"
                :key="step"
                class="step-dot"
                :class="{ now: step === current, done: isDone(step) }"
                :href="stepRoute({ unit: unit.id, step }).url"
                :title="$t(info[step].name)"
            >
                {{ info[step].icon }}
            </Link>
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { show, step as stepRoute } from '@/routes/units';
import type { UnitStep } from '@/types';
import { STEP_INFO, UNIT_STEPS } from './steps';
import type { StepHeadUnit } from './types';

/**
 * The head of a unit's step pages: "← emoji title" back to the unit, and the six step dots
 * (the step shown is highlighted, the ones done are ticked). `current` null highlights none
 * (the word bank).
 */
export default defineComponent({
    name: 'UnitStepHead',

    components: { Link },

    props: {
        unit: { type: Object as PropType<StepHeadUnit>, required: true },
        /** The level colour. */
        color: { type: String, required: true },
        current: { type: String as PropType<UnitStep | null>, default: null },
    },

    data() {
        return {
            steps: UNIT_STEPS,
            info: STEP_INFO,
        };
    },

    methods: {
        show,
        stepRoute,

        isDone(step: UnitStep): boolean {
            return step === 'test' ? this.unit.passed : !!this.unit.steps[step];
        },
    },
});
</script>
