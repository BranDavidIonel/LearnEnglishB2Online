<template>
    <div class="result fade-in">
        <div class="result-ring" :style="{ '--p': percent }">
            <span>{{ right }}/{{ results.length }}</span>
        </div>
        <h2>{{ title }}</h2>
        <p v-for="(line, i) in lines" :key="i" v-html="md(line)" />
        <div v-if="results.length" class="verb-results">
            <div
                v-for="(result, i) in results"
                :key="i"
                class="verb-result"
                :class="result.ok ? 'ok' : 'bad'"
            >
                {{ result.ok ? '✓ ' : '✗ ' }}{{ forms(result.verb) }}
            </div>
        </div>
        <div class="row center wrap mt">
            <slot name="actions" />
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import { md } from '@/utils/text';
import type { DrillResult } from './verbs';
import { forms } from './verbs';

// A short burst of confetti when every verb was right.
const CONFETTI_MS = 1200;

/** The end of a verbs session: right / total, the verbs right and missed, and what to do next (slot `actions`). */
export default defineComponent({
    name: 'VerbSummary',

    props: {
        results: { type: Array as PropType<DrillResult[]>, required: true },
        title: { type: String, required: true },
        /** Markdown lines. */
        lines: { type: Array as PropType<string[]>, default: () => [] },
    },

    data() {
        return {
            endConfetti: null as null | (() => void),
        };
    },

    computed: {
        right(): number {
            return this.results.filter((result) => result.ok).length;
        },

        percent(): number {
            return this.results.length
                ? Math.round((this.right / this.results.length) * 100)
                : 0;
        },
    },

    mounted() {
        if (this.results.length && this.right === this.results.length) {
            this.endConfetti = confetti(CONFETTI_MS);
            sfx('win');
        }
    },

    beforeUnmount() {
        this.endConfetti?.();
    },

    methods: {
        forms,
        md,
    },
});
</script>
