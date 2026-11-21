<template>
    <div class="cq" :class="review ? (right ? 'right' : 'wrong') : undefined">
        <div class="cq-n">{{ number }}</div>
        <div class="grow">
            <div v-if="mc?.text" class="cq-text" v-html="md(mc.text)" />
            <template v-if="mc?.audio">
                <ScriptBox v-if="review" :script="mc.audio" />
                <AudioButton
                    v-else
                    :label="$t('Listen')"
                    :plays="plays"
                    :playing="playing"
                    @toggle="$emit('play', mc.audio)"
                />
            </template>

            <template v-if="mc">
                <div v-if="text" class="cq-q" v-html="gap(text)" />
                <div class="cq-opts">
                    <button
                        v-for="(option, oi) in mc.options"
                        :key="oi"
                        type="button"
                        class="cq-opt"
                        :class="{
                            sel: answer === oi,
                            right: review && oi === mc.answer,
                            wrong: review && answer === oi && oi !== mc.answer,
                        }"
                        :disabled="review"
                        @click="$emit('answer', oi)"
                    >
                        <b v-if="!lettered">{{ letter(oi) }}</b>
                        <span v-html="md(option)"></span>
                    </button>
                </div>
            </template>

            <template v-else-if="fill">
                <div class="cq-q fill-q">
                    <span v-html="md(gapParts[0])"></span>
                    <input
                        v-model="typed"
                        type="text"
                        class="gap-input wide"
                        :class="
                            review ? (right ? 'right' : 'wrong') : undefined
                        "
                        :disabled="review"
                        autocomplete="off"
                        spellcheck="false"
                        autocapitalize="off"
                        @input="typedAnswer"
                    />
                    <span v-html="md(gapParts[1])"></span>
                </div>
                <div v-if="review && !right" class="fb-answer">
                    {{ $t('Correct: ')
                    }}<strong>{{ fill.answers.join(' / ') }}</strong>
                </div>
            </template>

            <div
                v-if="review"
                class="fb-explain"
                v-html="`💡 ${md(question.explainRo)}`"
            />
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { CambridgeQuestion, FillExercise, McExercise } from '@/types';
import { gap, md } from '@/utils/text';
import AudioButton from './AudioButton.vue';
import { isLettered, isRight, questionText } from './marking';
import ScriptBox from './ScriptBox.vue';
import type { PaperAnswer } from './types';

/**
 * One question of a Reading or Listening paper: options to choose or a gap to type in, with
 * its own text or recording. After the paper is handed in it shows right / wrong, the
 * accepted answers and the explanation.
 */
export default defineComponent({
    name: 'PaperQuestion',

    components: { AudioButton, ScriptBox },

    props: {
        question: {
            type: Object as PropType<CambridgeQuestion>,
            required: true,
        },
        /** Number of the question in the whole paper. */
        number: { type: Number, required: true },
        answer: {
            type: [Number, String] as PropType<PaperAnswer | undefined>,
            default: undefined,
        },
        review: { type: Boolean, default: false },
        /** Plays of the question's own recording on this part. */
        plays: { type: Number, default: 0 },
        playing: { type: Boolean, default: false },
    },

    emits: ['answer', 'play'],

    data() {
        return {
            // What is in the gap, spaces included (the answer itself is trimmed).
            typed: typeof this.answer === 'string' ? this.answer : '',
        };
    },

    computed: {
        mc(): McExercise | null {
            return this.question.type === 'mc' ? this.question : null;
        },

        fill(): FillExercise | null {
            return this.question.type === 'fill' ? this.question : null;
        },

        text(): string {
            return questionText(this.question);
        },

        lettered(): boolean {
            return this.mc ? isLettered(this.mc.options) : false;
        },

        /** The text before and after the gap of a typed answer. */
        gapParts(): [string, string] {
            const [before, after] = (this.text || this.question.q).split(
                /_{3,}/,
            );

            return [before ?? '', after ?? ''];
        },

        right(): boolean {
            return isRight(this.question, this.answer);
        },
    },

    methods: {
        md,
        gap,

        letter(index: number): string {
            return String.fromCharCode(65 + index);
        },

        typedAnswer(): void {
            const value = this.typed.trim();
            this.$emit('answer', value || undefined);
        },
    },
});
</script>
