<template>
    <div class="feedback" :class="outcome.correct ? 'good' : 'bad'">
        <div class="fb-head">{{ head }}</div>
        <div v-if="!outcome.correct && outcome.answer" class="fb-answer">
            {{ $t('Correct answer:') }} <strong>{{ outcome.answer }}</strong>
        </div>
        <div
            v-if="outcome.correct && outcome.note && outcome.answer"
            class="fb-answer"
        >
            {{ $t('Correct spelling:') }} <strong>{{ outcome.answer }}</strong>
        </div>
        <div v-if="explain" class="fb-explain" v-html="`💡 ${md(explain)}`" />
    </div>
    <button
        ref="next"
        type="button"
        class="btn big"
        :class="outcome.correct ? 'success' : 'danger'"
        @click="$emit('next')"
    >
        {{ $t('Continue →') }}
    </button>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { pick } from '@/utils/random';
import { md } from '@/utils/text';
import type { AnswerOutcome } from './types';

const PRAISE = ['Correct! 🎉', 'Well done! ✅', 'Exactly! 💪', 'Perfect! ⭐'];

/** Right or wrong after an answer, the right answer, the explanation (practice), and "Continue". */
export default defineComponent({
    name: 'ExerciseFeedback',

    props: {
        outcome: { type: Object as PropType<AnswerOutcome>, required: true },
        /** Explanation of the answer (markdown); empty in a test. */
        explain: { type: String, default: '' },
    },

    emits: ['next'],

    data() {
        return {
            // Chosen once, so it does not change when the page re-renders.
            praise: pick(PRAISE),
        };
    },

    computed: {
        head(): string {
            if (!this.outcome.correct) {
                return this.$t('Not quite… ❌');
            }

            return this.outcome.note || this.$t(this.praise);
        },
    },

    mounted() {
        // Enter (or Space) goes on to the next question.
        (this.$refs.next as HTMLButtonElement).focus();
    },

    methods: {
        md,
    },
});
</script>
