<template>
    <Head :title="`Writing · ${testName}`" />

    <Link class="back" :href="cambridge({ exam: exam.id }).url">
        ← {{ exam.name }}
    </Link>
    <div class="row between wrap">
        <h1>✍️ Writing · {{ testName }}</h1>
        <CountdownTimer :minutes="data.minutes" />
    </div>
    <p class="muted">
        {{
            $t(
                "The app can't mark free writing automatically. Write your text, then compare it honestly with the checklist and the model answer.",
            )
        }}
    </p>

    <div class="tabs">
        <button
            v-for="(item, i) in data.tasks"
            :key="i"
            type="button"
            class="tab"
            :class="{ on: i === idx }"
            @click="idx = i"
        >
            {{ item.title }}{{ revealed[i] ? ' ✓' : '' }}
        </button>
    </div>

    <div :key="idx">
        <div class="card instr">
            <strong>{{ task.title }}</strong>
            <p>{{ task.taskRo }}</p>
        </div>
        <div class="card passage" v-html="md(task.task)" />
        <UsefulPhrases :phrases="task.usefulPhrases" />
        <textarea
            v-model="texts[idx]"
            class="write-area"
            rows="12"
            spellcheck="false"
            :placeholder="$t('Write your answer in English here…')"
            :disabled="revealed[idx]"
            @input="keepDraft(idx)"
        ></textarea>
        <div class="row between wrap">
            <span class="wc" :class="countKind">
                {{
                    $t(
                        '1 word (goal: :min–:max)|:count words (goal: :min–:max)',
                        {
                            count: words,
                            min: task.minWords,
                            max: task.maxWords,
                        },
                    )
                }}
            </span>
            <button
                v-if="!revealed[idx]"
                type="button"
                class="btn primary"
                @click="reveal"
            >
                {{ $t("I'm done — compare with the model") }}
            </button>
        </div>

        <template v-if="revealed[idx]">
            <div class="card">
                <h3>
                    {{ $t('✅ Check your text (tick what you really did)') }}
                </h3>
                <label
                    v-for="(item, ci) in task.checklistRo"
                    :key="ci"
                    class="check block"
                >
                    <input
                        type="checkbox"
                        :checked="checks[idx].includes(ci)"
                        @change="tick(ci, $event)"
                    />
                    {{ item }}
                </label>
            </div>
            <div class="card">
                <h3>{{ $t('⭐ Model answer') }}</h3>
                <p class="model" v-html="md(task.model)" />
                <button type="button" class="btn" @click="speak(task.model)">
                    {{ $t('🔊 Listen to the model') }}
                </button>
            </div>
        </template>
    </div>

    <div
        v-if="done.length"
        class="card ready"
        :class="score >= target ? 'ok' : 'warn'"
    >
        <div class="row between wrap">
            <div>
                <strong>
                    {{ $t('Self-assessment: :pct%', { pct: percent(score) }) }}
                </strong>
                <div class="muted small">
                    {{
                        $t('1 task done|:count tasks done', {
                            count: done.length,
                        })
                    }}{{ $t(' · in the real exam you do 2') }}
                </div>
            </div>
            <button
                type="button"
                class="btn success"
                :disabled="done.length < 2 || saving"
                @click="saveResult"
            >
                {{
                    done.length < 2
                        ? $t('Do 2 tasks to save')
                        : $t('Save result')
                }}
            </button>
        </div>
        <p v-if="failed" class="muted small">
            {{
                $t(
                    'Your result could not be saved. Check the connection and try again — your answers are kept.',
                )
            }}
        </p>
    </div>
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { recordCambridge, saveCambridgeDraft } from '@/api/progress';
import CountdownTimer from '@/components/modules/cambridge/CountdownTimer.vue';
import { percent, testTitle } from '@/components/modules/cambridge/exam';
import type { ExamTag, TestTag } from '@/components/modules/cambridge/types';
import UsefulPhrases from '@/components/modules/cambridge/UsefulPhrases.vue';
import { cambridge } from '@/routes';
import type { WritingTask } from '@/types';
import { speak, stopSpeak } from '@/utils/speech';
import { md } from '@/utils/text';
import { toast } from '@/utils/toast';

/** A draft is sent this long after the last key press (and when the page is left). */
const DRAFT_DELAY = 1500;

/** Fewer words than this is not a text yet: the model answer stays hidden. */
const MIN_WORDS = 20;

function countWords(text: string): number {
    return text.trim() ? text.trim().split(/\s+/).length : 0;
}

/**
 * The Writing paper: write each task (the draft is kept), then compare it with the checklist
 * and the model answer. The ticked share of the checklist is the self-assessment.
 */
export default defineComponent({
    name: 'CambridgeWritingPage',

    components: { Head, Link, CountdownTimer, UsefulPhrases },

    props: {
        exam: { type: Object as PropType<ExamTag>, required: true },
        test: { type: Object as PropType<TestTag>, required: true },
        data: {
            type: Object as PropType<{
                minutes: number;
                tasks: WritingTask[];
            }>,
            required: true,
        },
        /** The text kept for every task ('' when none). */
        drafts: { type: Array as PropType<string[]>, required: true },
        /** The safe target of a paper, 0..1. */
        target: { type: Number, required: true },
    },

    data() {
        return {
            idx: 0,
            texts: this.drafts.map((text) => text),
            // Tasks compared with the model, and the checklist items ticked for each.
            revealed: this.drafts.map(() => false),
            checks: this.drafts.map(() => [] as number[]),
            // Drafts typed but not sent yet, by task.
            pending: {} as Record<number, string>,
            draftTimer: 0,
            saving: false,
            failed: false,
        };
    },

    computed: {
        task(): WritingTask {
            return this.data.tasks[this.idx];
        },

        testName(): string {
            return testTitle(this.test.title);
        },

        words(): number {
            return countWords(this.texts[this.idx]);
        },

        countKind(): string {
            if (
                this.words >= this.task.minWords &&
                this.words <= this.task.maxWords
            ) {
                return 'ok';
            }

            return this.words > this.task.maxWords ? 'bad' : '';
        },

        /** Tasks compared with the model. */
        done(): number[] {
            return this.revealed.flatMap((shown, i) => (shown ? [i] : []));
        },

        /** Average share of the checklist ticked, over the tasks done. */
        score(): number {
            const sum = this.done.reduce(
                (total, i) =>
                    total +
                    this.checks[i].length /
                        this.data.tasks[i].checklistRo.length,
                0,
            );

            return this.done.length ? sum / this.done.length : 0;
        },
    },

    watch: {
        /** Drafts asked for again (the page came back from the history, app.ts): what is typed here wins. */
        drafts(next: string[]): void {
            this.texts = next.map((text, i) => this.pending[i] ?? text);
        },
    },

    beforeUnmount() {
        stopSpeak();
        this.sendDrafts();
    },

    methods: {
        cambridge,
        md,
        percent,
        speak,

        keepDraft(task: number): void {
            this.pending[task] = this.texts[task];
            window.clearTimeout(this.draftTimer);
            this.draftTimer = window.setTimeout(this.sendDrafts, DRAFT_DELAY);
        },

        sendDrafts(): void {
            window.clearTimeout(this.draftTimer);

            for (const [task, text] of Object.entries(this.pending)) {
                // A lost draft is not worth more than the toast send() shows.
                saveCambridgeDraft(
                    this.exam.id,
                    this.test.id,
                    Number(task),
                    text,
                ).catch(() => undefined);
            }

            this.pending = {};
        },

        reveal(): void {
            if (this.words < MIN_WORDS) {
                toast(
                    this.$t(
                        'Write your text first (at least a few sentences).',
                    ),
                    'warn',
                );

                return;
            }

            this.revealed[this.idx] = true;
        },

        tick(item: number, event: Event): void {
            const ticked = (event.target as HTMLInputElement).checked;
            const list = this.checks[this.idx].filter((ci) => ci !== item);

            this.checks[this.idx] = ticked ? [...list, item] : list;
        },

        async saveResult(): Promise<void> {
            this.saving = true;
            this.failed = false;

            try {
                await recordCambridge(this.exam.id, this.test.id, 'writing', {
                    score: this.score,
                });
            } catch {
                this.saving = false;
                this.failed = true;

                return;
            }

            toast(this.$t('Result saved ✓'));
            router.visit(cambridge({ exam: this.exam.id }).url);
        },
    },
});
</script>
