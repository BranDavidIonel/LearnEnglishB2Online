<template>
    <component :is="tag" :class="{ tap: !plain }" v-html="html" />
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { tapWords } from '@/utils/tapWords';
import { esc, md } from '@/utils/text';

/**
 * English text whose every word can be tapped to open the word window (the layout listens for
 * the taps). Never use it where a tap would give an answer away (docs/frontend-ui.md).
 */
export default defineComponent({
    name: 'TapText',

    props: {
        text: { type: String, required: true },
        /** The text has the tiny markdown of the lessons (**bold**, *italic*, new lines). */
        markdown: { type: Boolean, default: false },
        tag: { type: String, default: 'span' },
        /** Shown as plain text for now (a card side not turned yet, an answer not given yet). */
        plain: { type: Boolean, default: false },
    },

    computed: {
        // The text is escaped (or md() escapes it) before the words are marked: safe for v-html.
        html(): string {
            const safe = this.markdown ? md(this.text) : esc(this.text);

            return this.plain ? safe : tapWords(safe);
        },
    },
});
</script>
