<template>
    <div class="ex-q">🇷🇴 {{ ex.ro }}</div>
    <textarea
        ref="input"
        v-model="value"
        class="type-input"
        :class="result"
        rows="2"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        :placeholder="$t('Type the English translation…')"
        :disabled="result !== ''"
        @keydown.enter.prevent
    />
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { TranslateExercise } from '@/types';
import { speak } from '@/utils/speech';
import { checkText } from '@/utils/text';
import type { AnswerOutcome } from './types';

/** Translate into English by typing; one small spelling mistake is forgiven in long answers. */
export default defineComponent({
    name: 'ExerciseTranslate',

    props: {
        ex: { type: Object as PropType<TranslateExercise>, required: true },
    },

    emits: ['ready', 'answer'],

    data() {
        return {
            value: '',
            result: '' as '' | 'right' | 'wrong',
            focusTimer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    watch: {
        value(value: string): void {
            this.$emit('ready', !!value.trim());
        },
    },

    mounted() {
        this.focusTimer = setTimeout(
            () =>
                (this.$refs.input as HTMLTextAreaElement | undefined)?.focus(),
            50,
        );
    },

    beforeUnmount() {
        clearTimeout(this.focusTimer);
    },

    methods: {
        check(): void {
            const { ok, typo } = checkText(this.value, this.ex.answers, true);
            this.result = ok ? 'right' : 'wrong';

            if (ok) {
                speak(this.ex.answers[0]);
            }

            const outcome: AnswerOutcome = {
                correct: ok,
                answer: this.ex.answers[0],
                note: typo
                    ? this.$t('Correct, but with a small spelling mistake ✍️')
                    : null,
            };
            this.$emit('answer', outcome);
        },
    },
});
</script>
