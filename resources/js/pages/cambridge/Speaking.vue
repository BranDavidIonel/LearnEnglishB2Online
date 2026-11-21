<template>
    <Head :title="`Speaking · ${testName}`" />

    <Link class="back" :href="cambridge({ exam: exam.id }).url">
        ← {{ exam.name }}
    </Link>
    <h1>🗣️ Speaking · {{ testName }}</h1>
    <p class="muted">
        {{
            $t(
                'In the real exam this paper takes :minutes minutes and you take it with another candidate. Here you practise each part on your own.',
                { minutes: data.minutes },
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
            {{ item.title }}{{ stars[i] ? ' ✓' : '' }}
        </button>
    </div>

    <div :key="idx">
        <div class="card instr">
            <strong>{{ task.title }}</strong>
            <p>{{ task.infoRo }}</p>
        </div>
        <div v-if="task.wiki || task.sceneRo" class="card">
            <WordPicture
                v-if="task.wiki"
                class="photo"
                :word="{ emoji: '🖼️', wiki: task.wiki, en: task.wiki }"
            />
            <p v-if="task.sceneRo" class="muted">🖼️ {{ task.sceneRo }}</p>
        </div>
        <div class="card">
            <h3>{{ $t('🧑‍🏫 The examiner says:') }}</h3>
            <div v-for="prompt in task.prompts" :key="prompt" class="example">
                <SpeakButton :text="prompt" />
                <div class="en">{{ prompt }}</div>
            </div>
        </div>
        <UsefulPhrases :phrases="task.usefulPhrases" />
        <div class="card">
            <h3>{{ $t('🎙 Your answer') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'Answer out loud, as in the exam. Record yourself and then listen back — it is the best way to hear your mistakes.',
                    )
                }}
            </p>
            <Recorder v-if="recordable" />
            <p v-else class="muted">
                {{
                    $t(
                        "Recording isn't available in this browser; answer out loud.",
                    )
                }}
            </p>
        </div>
        <details class="script">
            <summary>{{ $t('⭐ Show a model answer') }}</summary>
            <p class="model" v-html="md(task.model)" />
            <button type="button" class="btn" @click="speak(task.model)">
                {{ $t('🔊 Listen to the model') }}
            </button>
        </details>
        <div class="card">
            <h3>{{ $t('How did you do?') }}</h3>
            <p class="muted small">
                {{
                    $t(
                        '1 = I got stuck · 3 = I answered, with pauses and mistakes · 5 = fluent, as good as the model',
                    )
                }}
            </p>
            <div class="stars">
                <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    class="star"
                    :class="{ on: stars[idx] >= star }"
                    @click="stars[idx] = star"
                >
                    ★
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="rated.length"
        class="card ready"
        :class="score >= target ? 'ok' : 'warn'"
    >
        <div class="row between wrap">
            <strong>
                {{
                    $t('Self-assessment: :pct% (:done/:total parts)', {
                        pct: percent(score),
                        done: rated.length,
                        total: data.tasks.length,
                    })
                }}
            </strong>
            <button
                type="button"
                class="btn success"
                :disabled="!allRated || saving"
                @click="saveResult"
            >
                {{ allRated ? $t('Save result') : $t('Rate all the parts') }}
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
import { recordCambridge } from '@/api/progress';
import WordPicture from '@/components/content/WordPicture.vue';
import { percent, testTitle } from '@/components/modules/cambridge/exam';
import type { ExamTag, TestTag } from '@/components/modules/cambridge/types';
import UsefulPhrases from '@/components/modules/cambridge/UsefulPhrases.vue';
import Recorder from '@/components/speech/Recorder.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { cambridge } from '@/routes';
import type { SpeakingTask } from '@/types';
import { canRecord } from '@/utils/recording';
import { speak, stopSpeak } from '@/utils/speech';
import { md } from '@/utils/text';
import { toast } from '@/utils/toast';

/**
 * The Speaking paper: each part with what the examiner says, useful phrases, recording
 * yourself and a model answer. The learner rates every part from 1 to 5 stars.
 */
export default defineComponent({
    name: 'CambridgeSpeakingPage',

    components: {
        Head,
        Link,
        WordPicture,
        UsefulPhrases,
        Recorder,
        SpeakButton,
    },

    props: {
        exam: { type: Object as PropType<ExamTag>, required: true },
        test: { type: Object as PropType<TestTag>, required: true },
        data: {
            type: Object as PropType<{
                minutes: number;
                tasks: SpeakingTask[];
            }>,
            required: true,
        },
        /** The safe target of a paper, 0..1. */
        target: { type: Number, required: true },
    },

    data() {
        return {
            idx: 0,
            // Stars given to each part, 0 = not rated yet.
            stars: this.data.tasks.map(() => 0),
            recordable: canRecord(),
            saving: false,
            failed: false,
        };
    },

    computed: {
        task(): SpeakingTask {
            return this.data.tasks[this.idx];
        },

        testName(): string {
            return testTitle(this.test.title);
        },

        rated(): number[] {
            return this.stars.filter((star) => star > 0);
        },

        allRated(): boolean {
            return this.rated.length === this.data.tasks.length;
        },

        score(): number {
            const sum = this.rated.reduce((total, star) => total + star, 0);

            return this.rated.length ? sum / (this.rated.length * 5) : 0;
        },
    },

    beforeUnmount() {
        stopSpeak();
    },

    methods: {
        cambridge,
        md,
        percent,
        speak,

        async saveResult(): Promise<void> {
            this.saving = true;
            this.failed = false;

            try {
                await recordCambridge(this.exam.id, this.test.id, 'speaking', {
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
