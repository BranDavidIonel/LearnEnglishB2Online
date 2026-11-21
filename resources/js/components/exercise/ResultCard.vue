<template>
    <div class="result fade-in">
        <div
            class="result-ring"
            :class="{ fail: passed === false }"
            :style="{ '--p': percent }"
        >
            <span>{{ percent }}%</span>
        </div>
        <h2>{{ title }}</h2>
        <p v-if="passMark" class="muted">
            {{ $t('Pass mark: :pct%', { pct: Math.round(passMark * 100) }) }}
        </p>
        <slot />
        <p v-for="(line, i) in lines" :key="i" v-html="md(line)" />
        <div class="row center wrap">
            <slot name="actions" />
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { md } from '@/utils/text';

/**
 * The summary after a session: score ring, title, pass mark, lines of text (markdown), and the
 * buttons of the `actions` slot. The default slot goes before the lines (e.g. the units of an exam).
 */
export default defineComponent({
    name: 'ResultCard',

    props: {
        /** 0..1 */
        score: { type: Number, required: true },
        title: { type: String, required: true },
        /** false paints the ring red; leave it out when there is no pass mark. */
        passed: { type: Boolean as PropType<boolean | null>, default: null },
        /** 0..1, shown under the title. */
        passMark: { type: Number, default: 0 },
        /** Markdown lines (**bold**). */
        lines: { type: Array as PropType<string[]>, default: () => [] },
    },

    computed: {
        percent(): number {
            return Math.round(this.score * 100);
        },
    },

    methods: {
        md,
    },
});
</script>
