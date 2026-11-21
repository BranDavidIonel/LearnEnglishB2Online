<template>
    <span
        class="chip-tag"
        :class="kind || undefined"
        :style="color ? { '--c': color } : undefined"
    >
        <slot />
    </span>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/** Small rounded label: `ok`, `warn`, `bad`, `now` (current), `lvl` (filled with `color`) or plain. */
export default defineComponent({
    name: 'ChipTag',

    props: {
        kind: { type: String, default: '' },
        color: { type: String as PropType<string | null>, default: null },
    },
});
</script>
