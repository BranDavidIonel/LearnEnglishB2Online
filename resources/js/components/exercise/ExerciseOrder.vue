<template>
    <div class="ex-q">🇷🇴 {{ ex.ro }}</div>
    <div class="order-line" :class="result">
        <button
            v-for="(tile, n) in chosen"
            :key="tile"
            type="button"
            class="chip"
            @click="remove(n)"
        >
            {{ bank[tile] }}
        </button>
        <span v-if="!chosen.length" class="muted">
            {{ $t('Tap the words in the right order') }}
        </span>
    </div>
    <div class="order-bank">
        <button
            v-for="(word, tile) in bank"
            :key="tile"
            type="button"
            class="chip"
            :class="{ used: chosen.includes(tile) }"
            :disabled="chosen.includes(tile)"
            @click="add(tile)"
        >
            {{ word }}
        </button>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { OrderExercise } from '@/types';
import { shuffle } from '@/utils/random';
import { speak } from '@/utils/speech';
import { norm } from '@/utils/text';
import type { AnswerOutcome } from './types';

/** Shuffled words in a bank; the learner taps them into a line. Read aloud when right. */
export default defineComponent({
    name: 'ExerciseOrder',

    props: {
        ex: { type: Object as PropType<OrderExercise>, required: true },
    },

    emits: ['ready', 'answer'],

    data() {
        return {
            // The words in the order they are shown in the bank.
            bank: [] as string[],
            // Positions in `bank`, in the order the learner tapped them (a word may appear twice).
            chosen: [] as number[],
            result: '' as '' | 'right' | 'wrong',
        };
    },

    computed: {
        sentence(): string {
            return this.ex.words.join(' ');
        },
    },

    watch: {
        chosen: {
            deep: true,
            handler(chosen: number[]): void {
                this.$emit('ready', chosen.length === this.ex.words.length);
            },
        },
    },

    created() {
        let order = shuffle(this.ex.words.map((_, i) => i));

        // Never show the sentence already in order.
        if (order.every((original, i) => original === i)) {
            order = order.reverse();
        }

        this.bank = order.map((i) => this.ex.words[i]);
    },

    methods: {
        add(tile: number): void {
            if (!this.result) {
                this.chosen.push(tile);
            }
        },

        remove(n: number): void {
            if (!this.result) {
                this.chosen.splice(n, 1);
            }
        },

        check(): void {
            // Contractions are compared as written: the words are the learner's own tiles.
            const got = norm(
                this.chosen.map((tile) => this.bank[tile]).join(' '),
                false,
            );
            const targets = [this.ex.words, ...(this.ex.alt || [])].map(
                (words) => norm(words.join(' '), false),
            );
            const ok = targets.includes(got);
            this.result = ok ? 'right' : 'wrong';

            if (ok) {
                speak(this.sentence);
            }

            const outcome: AnswerOutcome = {
                correct: ok,
                answer: this.sentence,
            };
            this.$emit('answer', outcome);
        },
    },
});
</script>
