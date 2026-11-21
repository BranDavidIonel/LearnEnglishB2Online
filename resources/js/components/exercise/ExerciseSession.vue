<template>
    <div class="session">
        <div class="sess-top">
            <button
                type="button"
                class="icon-btn"
                :title="$t('Exit')"
                @click="exit"
            >
                ✕
            </button>
            <div class="bar">
                <div class="bar-fill" :style="{ width: `${progress}%` }" />
            </div>
            <span class="sess-count">{{ counter }}</span>
        </div>
        <div v-if="title" class="sess-title">{{ title }}</div>
        <div class="stage">
            <div v-if="current" :key="pos" class="ex fade-in">
                <div class="ex-type">
                    {{ current.retry ? `${$t('🔁 One more time:')} ` : ''
                    }}{{ typeLabel }}
                </div>
                <div class="ex-body">
                    <component
                        :is="component"
                        v-if="component"
                        ref="exercise"
                        :ex="current.ex"
                        @ready="ready = $event"
                        @answer="answer"
                    />
                    <p v-else>{{ $t('Unknown exercise type.') }}</p>
                </div>
                <div class="ex-foot">
                    <ExerciseFeedback
                        v-if="outcome"
                        :outcome="outcome"
                        :explain="explain"
                        @next="next"
                    />
                    <button
                        v-else-if="checkable"
                        type="button"
                        class="btn primary big"
                        :disabled="!canCheck"
                        @click="check"
                    >
                        {{ $t('Check') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import type { Component, PropType } from 'vue';
import { defineComponent } from 'vue';
import type { ExerciseItem, ExerciseResult, SessionMode } from '@/types';
import { sfx } from '@/utils/sound';
import { stopSpeak } from '@/utils/speech';
import ExerciseChoice from './ExerciseChoice.vue';
import ExerciseFeedback from './ExerciseFeedback.vue';
import ExerciseFill from './ExerciseFill.vue';
import ExerciseListen from './ExerciseListen.vue';
import ExerciseMatch from './ExerciseMatch.vue';
import ExerciseOrder from './ExerciseOrder.vue';
import ExerciseTranslate from './ExerciseTranslate.vue';
import type { AnswerOutcome, ExerciseControls } from './types';

/** A question waiting in the session; `retry` marks a wrong one asked again at the end. */
interface QueuedItem extends ExerciseItem {
    retry: boolean;
}

// Keyed by any string: content may hold a type this version does not know.
const COMPONENTS: Partial<Record<string, Component>> = {
    mc: ExerciseChoice,
    fill: ExerciseFill,
    order: ExerciseOrder,
    translate: ExerciseTranslate,
    listen: ExerciseListen,
    match: ExerciseMatch,
};

const TYPE_LABELS: Partial<Record<string, string>> = {
    mc: 'Choose the correct answer',
    fill: 'Fill in the gap',
    order: 'Put the words in order',
    translate: 'Translate into English',
    listen: 'Listen and type what you hear',
    match: 'Match the pairs',
};

// Answered with a click: no Check button, and Enter does not check them.
const ANSWERED_BY_CLICK: string[] = ['mc', 'match'];

/**
 * Runs a series of exercises, one at a time, with feedback after each answer.
 * practice: the explanation after each answer, and the wrong ones are asked again at the end.
 * test: only right / wrong, no repeats.
 * Emits 'finish' with the first-attempt results and 'exit' when the learner leaves (after confirming).
 */
export default defineComponent({
    name: 'ExerciseSession',

    components: { ExerciseFeedback },

    props: {
        items: { type: Array as PropType<ExerciseItem[]>, required: true },
        mode: { type: String as PropType<SessionMode>, default: 'practice' },
        /** Shown under the progress bar. */
        title: { type: String, default: '' },
    },

    emits: ['finish', 'exit'],

    data() {
        return {
            // Read once: changing `items` later does not restart the session (give it a new :key).
            queue: this.items.map((item): QueuedItem => ({
                ...item,
                retry: false,
            })),
            results: [] as ExerciseResult[],
            pos: 0,
            // Answers given when the current question appeared: the progress bar moves on the next one.
            doneBefore: 0,
            // The Check button can be used.
            ready: false,
            outcome: null as AnswerOutcome | null,
            finished: false,
        };
    },

    computed: {
        current(): QueuedItem | null {
            return this.finished ? null : (this.queue[this.pos] ?? null);
        },

        component(): Component | null {
            return this.current
                ? (COMPONENTS[this.current.ex.type] ?? null)
                : null;
        },

        typeLabel(): string {
            const label = this.current && TYPE_LABELS[this.current.ex.type];

            return label ? this.$t(label) : '';
        },

        checkable(): boolean {
            return (
                !!this.current &&
                !ANSWERED_BY_CLICK.includes(this.current.ex.type)
            );
        },

        canCheck(): boolean {
            return this.component ? this.ready : true;
        },

        explain(): string {
            return this.mode === 'practice' && this.current?.ex.explainRo
                ? this.current.ex.explainRo
                : '';
        },

        progress(): number {
            return Math.round((this.doneBefore / this.items.length) * 100);
        },

        counter(): string {
            if (this.current?.retry) {
                return this.$t('Retry');
            }

            const total = this.items.length;

            return `${Math.min(this.doneBefore + 1, total)} / ${total}`;
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKey);

        if (!this.queue.length) {
            this.finish();
        }
    },

    beforeUnmount() {
        this.stop();
    },

    methods: {
        controls(): ExerciseControls | undefined {
            return this.$refs.exercise as ExerciseControls | undefined;
        },

        check(): void {
            if (this.component) {
                this.controls()?.check?.();
            } else {
                // An exercise type this version does not know: let the learner go on.
                this.answer({ correct: true, answer: null });
            }
        },

        answer(outcome: AnswerOutcome): void {
            const item = this.current;

            if (!item || this.outcome) {
                return;
            }

            this.outcome = outcome;
            sfx(outcome.correct ? 'ok' : 'bad');

            if (!item.retry) {
                this.results.push({
                    ex: item.ex,
                    unit: item.unit,
                    key: item.key,
                    correct: outcome.correct,
                });
            }

            if (!outcome.correct && this.mode === 'practice' && !item.retry) {
                this.queue.push({ ...item, retry: true });
            }
        },

        next(): void {
            this.pos++;
            this.outcome = null;
            this.ready = false;
            this.doneBefore = this.results.length;

            if (this.pos >= this.queue.length) {
                this.finish();
            }
        },

        finish(): void {
            this.stop();
            this.finished = true;
            this.$emit('finish', this.results);
        },

        exit(): void {
            if (
                window.confirm(
                    this.$t(
                        'Are you sure you want to exit? Your progress in this session will be lost.',
                    ),
                )
            ) {
                this.stop();
                this.$emit('exit');
            }
        },

        stop(): void {
            document.removeEventListener('keydown', this.onKey);
            stopSpeak();
        },

        onKey(event: KeyboardEvent): void {
            if (event.key !== 'Enter') {
                this.controls()?.onKey?.(event);

                return;
            }

            if (this.outcome) {
                event.preventDefault();
                this.next();
            } else if (this.canCheck && this.checkable) {
                event.preventDefault();
                this.check();
            }
        },
    },
});
</script>
