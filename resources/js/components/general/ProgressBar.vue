<template>
    <div
        class="bar"
        :class="{ target: target !== null }"
        :style="target !== null ? { '--t': `${target * 100}%` } : undefined"
    >
        <div class="bar-fill" :style="fillStyle"></div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/** Horizontal progress bar; `target` draws the line of a goal (the Cambridge pass mark). */
export default defineComponent({
    name: 'ProgressBar',

    props: {
        /** From 0 to 1. */
        value: { type: Number, required: true },
        /** Fill colour; the inherited --c, else the accent, when left out. */
        color: { type: String as PropType<string | null>, default: null },
        /** From 0 to 1: where the goal line stands. */
        target: { type: Number as PropType<number | null>, default: null },
    },

    computed: {
        fillStyle(): Record<string, string> {
            const style: Record<string, string> = {
                width: `${Math.round(this.value * 100)}%`,
            };

            if (this.color) {
                style.background = this.color;
            }

            return style;
        },
    },
});
</script>
