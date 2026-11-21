<template>
    <GrammarLesson :grammar="grammar" />
    <StepDoneBar
        :unit-id="unit.id"
        step="grammar"
        :label="$t('Got it ✓ — next: Dialogue →')"
    />
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import GrammarLesson from '@/components/content/GrammarLesson.vue';
import type { Grammar } from '@/types';
import StepDoneBar from './StepDoneBar.vue';
import type { UnitHead } from './types';

/** Step 2: the grammar lesson of the unit. */
export default defineComponent({
    name: 'StepGrammar',

    components: { GrammarLesson, StepDoneBar },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        grammar: { type: Object as PropType<Grammar>, required: true },
    },
});
</script>
