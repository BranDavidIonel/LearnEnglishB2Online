<template>
    <slot v-if="ready" />
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { pickLevel, storedLevel } from '@/utils/speaking';

/**
 * Shows a speaking page in the level chosen on this device. The hub's links name the level
 * (?level=); a page opened without it was given the level being worked on, so when this device
 * chose another open level the page is asked for again in that level before anything starts.
 */
export default defineComponent({
    name: 'LevelGate',

    props: {
        /** The level the server sent. */
        level: { type: String, required: true },
        /** The open levels. */
        open: { type: Array as PropType<string[]>, required: true },
    },

    data() {
        return { ready: false };
    },

    mounted() {
        const wanted = pickLevel(storedLevel(), this.open, this.level);

        if (
            new URLSearchParams(window.location.search).has('level') ||
            wanted === this.level
        ) {
            this.ready = true;

            return;
        }

        router.get(
            window.location.pathname,
            { level: wanted },
            { replace: true },
        );
    },
});
</script>
