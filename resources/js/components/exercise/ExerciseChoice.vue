<template>
    <div class="ex-q" v-html="gap(ex.q)" />
    <div class="opts">
        <button
            v-for="(option, n) in order"
            :key="option"
            type="button"
            class="opt"
            :class="{
                right: picked !== null && option === ex.answer,
                wrong: picked === n && option !== ex.answer,
            }"
            :disabled="picked !== null"
            @click="choose(n)"
        >
            <span class="opt-n">{{ n + 1 }}</span>
            <span>{{ ex.options[option] }}</span>
        </button>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { McExercise } from '@/types';
import { shuffle } from '@/utils/random';
import { gap } from '@/utils/text';
import type { AnswerOutcome } from './types';

/** Multiple choice: the options in random order, keys 1–9 pick one. Answered with the first click. */
export default defineComponent({
    name: 'ExerciseChoice',

    props: {
        ex: { type: Object as PropType<McExercise>, required: true },
    },

    emits: ['answer'],

    data() {
        return {
            // Indexes of ex.options, in the order they are shown.
            order: shuffle(this.ex.options.map((_, i) => i)),
            // Position (in `order`) of the option the learner chose.
            picked: null as number | null,
        };
    },

    methods: {
        gap,

        choose(n: number): void {
            if (this.picked !== null) {
                return;
            }

            this.picked = n;
            const outcome: AnswerOutcome = {
                correct: this.order[n] === this.ex.answer,
                answer: this.ex.options[this.ex.answer],
            };
            this.$emit('answer', outcome);
        },

        onKey(event: KeyboardEvent): void {
            const n = Number(event.key);

            if (n >= 1 && n <= this.order.length) {
                this.choose(n - 1);
            }
        },
    },
});
</script>
