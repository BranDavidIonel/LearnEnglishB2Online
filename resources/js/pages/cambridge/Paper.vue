<template>
    <Head :title="`${paperTitle} · ${testName}`" />

    <div class="paper-top">
        <Link
            class="back"
            :href="cambridge({ exam: exam.id }).url"
            :on-before="confirmLeave"
        >
            ← {{ exam.name }}
        </Link>
        <div class="row between wrap">
            <h1>{{ paperIcons[paper] }} {{ paperTitle }} · {{ testName }}</h1>
            <CountdownTimer v-if="!result" :minutes="data.minutes" />
        </div>

        <template v-if="result">
            <div class="card ready" :class="resultKind">
                <div class="row wrap gap-lg">
                    <ProgressRing
                        :value="result.score"
                        :color="colors[resultKind]"
                        :label="`${percent(result.score)}%`"
                        :size="88"
                    />
                    <div class="grow">
                        <h2>
                            {{
                                $t(':correct of :total correct', {
                                    correct: result.correct,
                                    total: result.total,
                                })
                            }}
                        </h2>
                        <p>{{ resultText }}</p>
                    </div>
                </div>
            </div>
            <SaveAlert v-if="saveState === 'failed'" @retry="save" />
        </template>

        <div class="tabs">
            <button
                v-for="(item, pi) in data.parts"
                :key="pi"
                type="button"
                class="tab"
                :class="{ on: pi === partIdx }"
                @click="openPart(pi)"
            >
                {{ tabTitle(item) }} <small>{{ tabCount(item, pi) }}</small>
            </button>
        </div>
    </div>

    <div class="paper-body">
        <div class="card instr">
            <strong>{{ part.title }}</strong>
            <p>{{ part.instructionsRo }}</p>
        </div>

        <div class="paper-cols" :class="{ single: !hasSide }">
            <div v-if="hasSide" class="paper-side">
                <div
                    v-if="part.text"
                    class="card passage"
                    v-html="md(part.text)"
                />
                <div v-if="part.audio" class="card">
                    <ScriptBox v-if="result" :script="part.audio" />
                    <template v-else>
                        <AudioButton
                            :label="$t('Listen to the recording')"
                            :plays="plays.part ?? 0"
                            :playing="playing === 'part'"
                            @toggle="play('part', part.audio)"
                        />
                        <p class="muted small">
                            {{
                                $t(
                                    'In the exam you hear each recording twice. Read the questions first!',
                                )
                            }}
                        </p>
                    </template>
                </div>
            </div>
            <div class="cqs">
                <PaperQuestion
                    v-for="(question, qi) in part.questions"
                    :key="`${partIdx}:${qi}`"
                    :question="question"
                    :number="numbers[answerKey(partIdx, qi)]"
                    :answer="answers[answerKey(partIdx, qi)]"
                    :review="result !== null"
                    :plays="plays[`q${qi}`] ?? 0"
                    :playing="playing === `q${qi}`"
                    @answer="setAnswer(qi, $event)"
                    @play="play(`q${qi}`, $event)"
                />
            </div>
        </div>

        <div class="row between mt">
            <button
                v-if="partIdx > 0"
                type="button"
                class="btn"
                @click="step(-1)"
            >
                {{ $t('← Previous part') }}
            </button>
            <span v-else></span>

            <button
                v-if="partIdx < data.parts.length - 1"
                type="button"
                class="btn primary"
                @click="step(1)"
            >
                {{ $t('Next part →') }}
            </button>
            <Link
                v-else-if="result"
                class="btn primary"
                :href="cambridge({ exam: exam.id }).url"
            >
                {{ $t('Back to the exam') }}
            </Link>
            <button
                v-else
                type="button"
                class="btn success big"
                @click="handIn"
            >
                {{ $t('✔ Hand in') }}
            </button>
        </div>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { recordCambridge } from '@/api/progress';
import ProgressRing from '@/components/general/ProgressRing.vue';
import SaveAlert from '@/components/general/SaveAlert.vue';
import AudioButton from '@/components/modules/cambridge/AudioButton.vue';
import CountdownTimer from '@/components/modules/cambridge/CountdownTimer.vue';
import {
    COLORS,
    PAPER_ICONS,
    paperName,
    percent,
    scoreKind,
    testTitle,
} from '@/components/modules/cambridge/exam';
import type { ScoreKind } from '@/components/modules/cambridge/exam';
import {
    answerKey,
    markPaper,
    rightInPart,
} from '@/components/modules/cambridge/marking';
import type { PaperAnswers } from '@/components/modules/cambridge/marking';
import PaperQuestion from '@/components/modules/cambridge/PaperQuestion.vue';
import ScriptBox from '@/components/modules/cambridge/ScriptBox.vue';
import type {
    ExamTag,
    PaperAnswer,
    TestTag,
} from '@/components/modules/cambridge/types';
import { cambridge } from '@/routes';
import type { CambridgePart, CambridgeResultInput, ScriptLine } from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import { speakScript, stopSpeak } from '@/utils/speech';
import { md } from '@/utils/text';

/**
 * A Reading or Listening paper of a mock test: its parts in tabs, the countdown, the
 * recordings read by the app, then the marking and the review with the explanations.
 */
export default defineComponent({
    name: 'CambridgePaperPage',

    components: {
        Head,
        Link,
        ProgressRing,
        SaveAlert,
        AudioButton,
        CountdownTimer,
        PaperQuestion,
        ScriptBox,
    },

    props: {
        exam: { type: Object as PropType<ExamTag>, required: true },
        test: { type: Object as PropType<TestTag>, required: true },
        paper: {
            type: String as PropType<'reading' | 'listening'>,
            required: true,
        },
        data: {
            type: Object as PropType<{
                minutes: number;
                parts: CambridgePart[];
            }>,
            required: true,
        },
        /** The safe target of a paper, 0..1. */
        target: { type: Number, required: true },
        /** The "close" score, 0..1. */
        close: { type: Number, required: true },
    },

    data() {
        return {
            paperIcons: PAPER_ICONS,
            colors: COLORS,
            answers: {} as PaperAnswers,
            partIdx: 0,
            // Set when the paper is handed in: the page then shows the review.
            result: null as Required<CambridgeResultInput> | null,
            saveState: 'idle' as 'idle' | 'saving' | 'saved' | 'failed',
            // Plays of each recording of the part on screen ("part", "q0" …) and the one playing.
            plays: {} as Record<string, number>,
            playing: null as string | null,
            alive: true,
        };
    },

    computed: {
        part(): CambridgePart {
            return this.data.parts[this.partIdx];
        },

        hasSide(): boolean {
            return !!(this.part.text || this.part.audio);
        },

        paperTitle(): string {
            return paperName(this.exam.id, this.paper);
        },

        testName(): string {
            return testTitle(this.test.title);
        },

        /** Number of every question in the whole paper, by "<part>:<question>". */
        numbers(): Record<string, number> {
            const numbers: Record<string, number> = {};
            let n = 0;

            this.data.parts.forEach((part, pi) =>
                part.questions.forEach((_, qi) => {
                    numbers[answerKey(pi, qi)] = ++n;
                }),
            );

            return numbers;
        },

        total(): number {
            return Object.keys(this.numbers).length;
        },

        resultKind(): ScoreKind {
            return scoreKind(this.result?.score ?? 0, this.target, this.close);
        },

        resultText(): string {
            switch (this.resultKind) {
                case 'ok':
                    return this.$t('Above the safe target (70%). Well done!');
                case 'warn':
                    return this.$t(
                        'In the pass zone, but with no margin. Look at the explanations below.',
                    );
                default:
                    return this.$t(
                        "Below the pass mark for now. Go through the explanations below — that's where you learn the most.",
                    );
            }
        },
    },

    beforeUnmount() {
        this.alive = false;
        stopSpeak();
    },

    methods: {
        cambridge,
        md,
        percent,
        answerKey,

        tabTitle(part: CambridgePart): string {
            return part.title.replace(/\s*[—–-].*$/, '');
        },

        tabCount(part: CambridgePart, index: number): string {
            const count = this.result
                ? rightInPart(part, index, this.answers)
                : part.questions.filter(
                      (_, qi) =>
                          this.answers[answerKey(index, qi)] !== undefined,
                  ).length;

            return `${count}/${part.questions.length}`;
        },

        setAnswer(question: number, value: PaperAnswer | undefined): void {
            const key = answerKey(this.partIdx, question);

            if (value === undefined) {
                delete this.answers[key];
            } else {
                this.answers[key] = value;
            }
        },

        /** A new part starts without anything playing and with its recordings not heard yet. */
        openPart(index: number): void {
            stopSpeak();
            this.partIdx = index;
            this.plays = {};
            this.playing = null;
        },

        step(delta: number): void {
            this.openPart(this.partIdx + delta);
            window.scrollTo(0, 0);
        },

        /** Starts a recording (stopping any other), or stops it when it is the one playing. */
        play(id: string, script: ScriptLine[]): void {
            if (this.playing === id) {
                stopSpeak();
                this.playing = null;

                return;
            }

            this.plays[id] = (this.plays[id] ?? 0) + 1;
            this.playing = id;
            speakScript(script, () => {
                if (this.playing === id) {
                    this.playing = null;
                }
            });
        },

        confirmLeave(): boolean {
            const answered = Object.keys(this.answers).length;

            return (
                this.result !== null ||
                !answered ||
                window.confirm(
                    this.$t('Leave this paper? Your answers will be lost.'),
                )
            );
        },

        handIn(): void {
            const answered = Object.keys(this.answers).length;

            if (
                answered < this.total &&
                !window.confirm(
                    this.$t(
                        'You answered :answered of :total questions. Hand in anyway?',
                        { answered, total: this.total },
                    ),
                )
            ) {
                return;
            }

            stopSpeak();
            this.result = markPaper(this.data.parts, this.answers);
            this.partIdx = 0;
            this.plays = {};
            this.playing = null;

            if (this.result.score >= this.target) {
                confetti();
                sfx('win');
            }

            window.scrollTo(0, 0);
            void this.save();
        },

        /** Stores the result; when it fails the review stays and the learner can send it again. */
        async save(): Promise<void> {
            if (!this.result || this.saveState === 'saving') {
                return;
            }

            this.saveState = 'saving';

            try {
                await recordCambridge(
                    this.exam.id,
                    this.test.id,
                    this.paper,
                    this.result,
                );

                if (this.alive) {
                    this.saveState = 'saved';
                }
            } catch {
                if (this.alive) {
                    this.saveState = 'failed';
                }
            }
        },
    },
});
</script>
