<template>
    <div class="listen-box">
        <button type="button" class="btn round primary" @click="play(false)">
            🔊
        </button>
        <button
            type="button"
            class="btn round"
            :title="$t('Slower')"
            @click="play(true)"
        >
            🐢
        </button>
        <span class="muted">{{ $t('Tap to listen again') }}</span>
    </div>
    <textarea
        ref="input"
        v-model="value"
        class="type-input"
        :class="result"
        rows="2"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        :placeholder="$t('Type what you hear in English…')"
        :disabled="result !== ''"
        @keydown.enter.prevent
    />
    <div v-if="result && ex.ro" class="muted mt">🇷🇴 {{ ex.ro }}</div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { ListenExercise } from '@/types';
import { speak } from '@/utils/speech';
import { checkText } from '@/utils/text';
import type { AnswerOutcome } from './types';

/** Dictation: the sentence is read aloud (again, or slower, on demand) and the learner types it. */
export default defineComponent({
    name: 'ExerciseListen',

    props: {
        ex: { type: Object as PropType<ListenExercise>, required: true },
    },

    emits: ['ready', 'answer'],

    data() {
        return {
            value: '',
            result: '' as '' | 'right' | 'wrong',
            timers: [] as ReturnType<typeof setTimeout>[],
        };
    },

    watch: {
        value(value: string): void {
            this.$emit('ready', !!value.trim());
        },
    },

    mounted() {
        // Read the sentence once the exercise is on screen, and let the learner type at once.
        this.timers.push(
            setTimeout(() => speak(this.ex.audio), 350),
            setTimeout(() => this.focus(), 50),
        );
    },

    beforeUnmount() {
        this.timers.forEach(clearTimeout);
    },

    methods: {
        play(slow: boolean): void {
            speak(this.ex.audio, { slow });
            this.focus();
        },

        focus(): void {
            (this.$refs.input as HTMLTextAreaElement | undefined)?.focus();
        },

        check(): void {
            const { ok, typo } = checkText(this.value, this.ex.answers, true);
            this.result = ok ? 'right' : 'wrong';
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
