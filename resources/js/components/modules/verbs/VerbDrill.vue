<template>
    <div class="session">
        <div class="sess-top">
            <button class="icon-btn" :title="$t('Exit')" @click="end(true)">
                ✕
            </button>
            <ProgressBar :value="progress" />
            <span class="sess-count">{{ counter }}</span>
        </div>
        <div v-if="title" class="sess-title">{{ title }}</div>
        <div>
            <div v-if="item" :key="pos" class="card verb-drill fade-in">
                <div class="muted small">
                    {{
                        $t(
                            'Type the past simple (V2) and the past participle (V3)',
                        )
                    }}
                </div>
                <div class="vword">
                    <span>{{ item.verb.v1 }}</span>
                    <SpeakButton :text="item.verb.v1" />
                </div>
                <div class="vro">{{ item.verb.ro || '' }}</div>
                <div class="verb-inputs">
                    <label>
                        <span class="muted small">{{
                            $t('V2 · past simple')
                        }}</span>
                        <input
                            ref="past"
                            v-model="past"
                            class="gap-input verb-input"
                            :class="inputClass(pastOk)"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            autocapitalize="off"
                            :readonly="answered"
                            @keydown.enter.prevent="onPastEnter"
                        />
                    </label>
                    <label>
                        <span class="muted small">{{
                            $t('V3 · past participle')
                        }}</span>
                        <input
                            ref="participle"
                            v-model="participle"
                            class="gap-input verb-input"
                            :class="inputClass(participleOk)"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            autocapitalize="off"
                            :readonly="answered"
                            @keydown.enter.prevent="check"
                        />
                    </label>
                </div>
                <div class="row center mt">
                    <button
                        v-if="!answered"
                        class="btn primary big"
                        @click="check"
                    >
                        {{ $t('Check') }}
                    </button>
                    <button
                        v-else
                        ref="continue"
                        class="btn big"
                        :class="ok ? 'success' : 'danger'"
                        @click="next"
                    >
                        {{ $t('Continue →') }}
                    </button>
                </div>
                <div class="mt">
                    <div
                        v-if="answered"
                        class="feedback"
                        :class="ok ? 'good' : 'bad'"
                    >
                        <div class="fb-head">
                            {{ ok ? $t('Correct! 🎉') : $t('Not quite… ❌') }}
                        </div>
                        <div class="fb-answer">
                            <strong>{{ forms(item.verb) }}</strong>
                            {{ ' ' }}
                            <SpeakButton :text="spokenForms(item.verb)" />
                        </div>
                        <div v-if="!ok && !item.retry" class="fb-explain">
                            {{ $t('It comes back once more at the end.') }}
                        </div>
                        <div
                            v-if="item.verb.ex3"
                            class="fb-explain tap"
                            v-html="
                                tapWords(mark(item.verb.ex3, item.verb.alts3))
                            "
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { sfx } from '@/utils/sound';
import { stopSpeak } from '@/utils/speech';
import { tapWords } from '@/utils/tapWords';
import { checkText } from '@/utils/text';
import type { DrillResult, VerbItem } from './verbs';
import { forms, mark, sayForms, spokenForms } from './verbs';

interface QueueItem {
    verb: VerbItem;
    /** A verb missed the first time comes back once at the end; that second try is not counted. */
    retry: boolean;
}

// The past field gets the focus a moment after a verb appears (the card fades in).
const FOCUS_DELAY = 50;

/**
 * Typing drill: for each verb the learner types the past simple and the past participle.
 * Emits `answer(verb, ok)` once per verb (first attempt) and `done(results, early)` at the end
 * or on ✕ (early). Wrong verbs come back once at the end.
 */
export default defineComponent({
    name: 'VerbDrill',

    components: { ProgressBar, SpeakButton },

    props: {
        verbs: { type: Array as PropType<VerbItem[]>, required: true },
        title: { type: String, default: '' },
    },

    emits: {
        answer: (verb: VerbItem, ok: boolean) =>
            !!verb && typeof ok === 'boolean',
        done: (results: DrillResult[], early: boolean) =>
            Array.isArray(results) && typeof early === 'boolean',
    },

    data() {
        return {
            queue: this.verbs.map((verb): QueueItem => ({
                verb,
                retry: false,
            })),
            results: [] as DrillResult[],
            pos: 0,
            ended: false,
            answered: false,
            past: '',
            participle: '',
            pastOk: null as boolean | null,
            participleOk: null as boolean | null,
            focusTimer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    computed: {
        item(): QueueItem | null {
            return this.queue[this.pos] ?? null;
        },

        ok(): boolean {
            return this.pastOk === true && this.participleOk === true;
        },

        // Verbs counted before the one on screen: the bar and the counter move on when the next verb appears.
        counted(): number {
            return this.answered && !this.item?.retry
                ? this.results.length - 1
                : this.results.length;
        },

        progress(): number {
            return this.counted / this.verbs.length;
        },

        counter(): string {
            return this.item?.retry
                ? this.$t('Once more')
                : `${this.counted + 1} / ${this.verbs.length}`;
        },
    },

    mounted() {
        this.focusPast();
    },

    beforeUnmount() {
        this.ended = true;
        clearTimeout(this.focusTimer);
        stopSpeak();
    },

    methods: {
        forms,
        // The example comes from verbs.json; mark() escapes it before adding the bold, and it is
        // shown only once the verb is answered, so its words may be tapped.
        mark,
        tapWords,
        spokenForms,

        inputClass(right: boolean | null): string {
            return right === null ? '' : right ? 'right' : 'wrong';
        },

        focusPast(): void {
            clearTimeout(this.focusTimer);
            this.focusTimer = setTimeout(
                () =>
                    (this.$refs.past as HTMLInputElement | undefined)?.focus(),
                FOCUS_DELAY,
            );
        },

        onPastEnter(): void {
            if (!this.answered) {
                (
                    this.$refs.participle as HTMLInputElement | undefined
                )?.focus();
            }
        },

        check(): void {
            const item = this.item;

            if (this.answered || !item) {
                return;
            }

            if (!this.past.trim()) {
                (this.$refs.past as HTMLInputElement | undefined)?.focus();

                return;
            }

            if (!this.participle.trim()) {
                (
                    this.$refs.participle as HTMLInputElement | undefined
                )?.focus();

                return;
            }

            this.answered = true;
            this.pastOk = checkText(this.past, item.verb.answers2).ok;
            this.participleOk = checkText(
                this.participle,
                item.verb.answers3,
            ).ok;
            sfx(this.ok ? 'ok' : 'bad');
            sayForms(item.verb);

            if (!item.retry) {
                this.results.push({ verb: item.verb, ok: this.ok });
                this.$emit('answer', item.verb, this.ok);

                if (!this.ok) {
                    this.queue.push({ verb: item.verb, retry: true });
                }
            }

            this.$nextTick(() =>
                (this.$refs.continue as HTMLButtonElement | undefined)?.focus(),
            );
        },

        next(): void {
            this.pos++;

            if (!this.item) {
                this.end(false);

                return;
            }

            this.answered = false;
            this.past = '';
            this.participle = '';
            this.pastOk = null;
            this.participleOk = null;
            this.focusPast();
        },

        end(early: boolean): void {
            if (this.ended) {
                return;
            }

            this.ended = true;
            stopSpeak();
            this.$emit('done', this.results, early);
        },
    },
});
</script>
