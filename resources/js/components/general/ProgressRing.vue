<template>
    <div class="ring" :style="ringStyle">
        <span>{{ label ?? `${percent}%` }}</span>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/** Round progress gauge with a label in the middle (the percentage unless one is given). */
export default defineComponent({
    name: 'ProgressRing',

    props: {
        /** From 0 to 1. */
        value: { type: Number, required: true },
        /** Fill colour; the inherited --c (the accent) when left out. */
        color: { type: String as PropType<string | null>, default: null },
        label: {
            type: [String, Number] as PropType<string | number | null>,
            default: null,
        },
        /** Diameter in pixels. */
        size: { type: Number, default: 64 },
    },

    computed: {
        percent(): number {
            return Math.round(this.value * 100);
        },

        ringStyle(): Record<string, string> {
            const style: Record<string, string> = {
                '--p': String(this.percent),
                '--s': `${this.size}px`,
            };

            if (this.color) {
                style['--c'] = this.color;
            }

            return style;
        },
    },
});
</script>
