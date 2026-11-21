<template>
    <div class="ex-q fill-q">
        <span v-html="md(parts.before)" />
        <input
            ref="input"
            v-model="value"
            type="text"
            class="gap-input"
            :class="result"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            placeholder="…"
            :disabled="result !== ''"
        />
        <span v-html="md(parts.after)" />
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { FillExercise } from '@/types';
import { checkText, md } from '@/utils/text';
import type { AnswerOutcome } from './types';

/** Fill in the gap: the "___" of the question becomes a text box. Checked exactly (no typo tolerance). */
export default defineComponent({
    name: 'ExerciseFill',

    props: {
        ex: { type: Object as PropType<FillExercise>, required: true },
    },

    emits: ['ready', 'answer'],

    data() {
        return {
            value: '',
            // '' until checked, then 'right' or 'wrong'.
            result: '' as '' | 'right' | 'wrong',
            focusTimer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    computed: {
        parts(): { before: string; after: string } {
            const [before, after] = this.ex.q.split(/_{3,}/);

            return { before, after: after || '' };
        },
    },

    watch: {
        value(value: string): void {
            this.$emit('ready', !!value.trim());
        },
    },

    mounted() {
        this.focusTimer = setTimeout(
            () => (this.$refs.input as HTMLInputElement | undefined)?.focus(),
            50,
        );
    },

    beforeUnmount() {
        clearTimeout(this.focusTimer);
    },

    methods: {
        md,

        check(): void {
            const { ok } = checkText(this.value, this.ex.answers);
            this.result = ok ? 'right' : 'wrong';
            const outcome: AnswerOutcome = {
                correct: ok,
                answer: this.ex.answers[0],
            };
            this.$emit('answer', outcome);
        },
    },
});
</script>
