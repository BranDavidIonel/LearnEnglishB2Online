<template>
    <Head :title="`${$t(info.name)} · ${unit.title}`" />

    <UnitStepHead :unit="unit" :color="level.color" :current="step" />
    <div class="step-body">
        <StepVocab
            v-if="step === 'vocab' && vocab"
            :key="`${unit.id}-vocab`"
            :unit="unit"
            :words="vocab"
        />
        <StepGrammar
            v-else-if="step === 'grammar' && grammar"
            :key="`${unit.id}-grammar`"
            :unit="unit"
            :grammar="grammar"
        />
        <StepDialogue
            v-else-if="step === 'dialogue' && dialogue"
            :key="`${unit.id}-dialogue`"
            :unit="unit"
            :dialogue="dialogue"
        />
        <StepPractice
            v-else-if="step === 'practice' && exercises"
            :key="`${unit.id}-practice`"
            :unit="unit"
            :items="exercises"
        />
        <StepReading
            v-else-if="step === 'reading' && reading"
            :key="`${unit.id}-reading`"
            :unit="unit"
            :reading="reading"
        />
        <StepTest
            v-else-if="step === 'test' && test"
            :key="`${unit.id}-test`"
            :unit="unit"
            :level="level"
            :test="test"
            :pass-mark="passMark"
        />
    </div>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import StepDialogue from '@/components/modules/units/StepDialogue.vue';
import StepGrammar from '@/components/modules/units/StepGrammar.vue';
import StepPractice from '@/components/modules/units/StepPractice.vue';
import StepReading from '@/components/modules/units/StepReading.vue';
import StepTest from '@/components/modules/units/StepTest.vue';
import StepVocab from '@/components/modules/units/StepVocab.vue';
import type { StepInfo } from '@/components/modules/units/steps';
import { STEP_INFO } from '@/components/modules/units/steps';
import type {
    KeyedItem,
    LevelTag,
    ReadingStep,
    UnitHead,
    UnitTestData,
} from '@/components/modules/units/types';
import UnitStepHead from '@/components/modules/units/UnitStepHead.vue';
import type { Dialogue, Grammar, UnitStep, VocabWord } from '@/types';

/** One step of a unit, under the step head. Only the content of the step shown is sent. */
export default defineComponent({
    name: 'UnitStepPage',

    components: {
        Head,
        UnitStepHead,
        StepVocab,
        StepGrammar,
        StepDialogue,
        StepPractice,
        StepReading,
        StepTest,
    },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        level: { type: Object as PropType<LevelTag>, required: true },
        step: { type: String as PropType<UnitStep>, required: true },
        /** Pass mark of the unit test, 0..1. */
        passMark: { type: Number, required: true },
        vocab: { type: Array as PropType<VocabWord[] | null>, default: null },
        grammar: { type: Object as PropType<Grammar | null>, default: null },
        dialogue: { type: Object as PropType<Dialogue | null>, default: null },
        exercises: {
            type: Array as PropType<KeyedItem[] | null>,
            default: null,
        },
        reading: {
            type: Object as PropType<ReadingStep | null>,
            default: null,
        },
        test: { type: Object as PropType<UnitTestData | null>, default: null },
    },

    computed: {
        info(): StepInfo {
            return STEP_INFO[this.step];
        },
    },
});
</script>
