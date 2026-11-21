<template>
    <SessionShell
        :title="title"
        :done="progress"
        :total="items.length"
        :back="back"
    >
        <RoundSave v-if="finished" :save="saves" @saved="celebrate">
            <ResultCard
                :score="good / items.length"
                :title="$t('Round finished')"
                :lines="[resultLine]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="$emit('again')"
                    >
                        {{ $t('🔁 More questions') }}
                    </button>
                    <Link class="btn" :href="back">{{ $t('Back') }}</Link>
                </template>
            </ResultCard>
        </RoundSave>
        <div v-else-if="question" :key="index" class="ex fade-in">
            <div class="ex-type">{{ $t('Answer aloud') }}</div>
            <div class="ex-q">
                <SpeakButton :text="question.q" /> {{ question.q }}
            </div>
            <div class="muted">🇷🇴 {{ question.qRo }}</div>
            <div class="starters">
                <span class="muted small">{{ $t('You can start with:') }}</span>
                <span
                    v-for="starter in question.starters"
                    :key="starter"
                    class="chip-tag now"
                >
                    {{ starter }}
                </span>
            </div>
            <MicPanel @heard="onHeard" />
            <div>
                <div
                    v-if="answer"
                    class="feedback"
                    :class="answer.fine ? 'good' : 'bad'"
                >
                    <div class="fb-head">
                        {{
                            answer.fine
                                ? $t('I understood what you said ✅')
                                : $t('Too short — say a full sentence')
                        }}
                    </div>
                    <div v-if="answer.text">
                        {{ $t('I heard:') }} <em>„{{ answer.text }}”</em> ·
                        {{ $t('1 word|:count words', { count: answer.words }) }}
                    </div>
                </div>
            </div>
            <div>
                <div v-if="sampleShown" class="example">
                    <SpeakButton :text="question.sample" />
                    <div>
                        <div class="muted small">
                            {{ $t('A good answer:') }}
                        </div>
                        <div class="en">{{ question.sample }}</div>
                    </div>
                </div>
            </div>
            <div class="row center wrap mt">
                <button type="button" class="btn" @click="sampleShown = true">
                    {{ $t('👁 Show a good answer') }}
                </button>
                <button type="button" class="btn primary" @click="nextQuestion">
                    {{ $t('Next question →') }}
                </button>
            </div>
        </div>
    </SessionShell>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { countSpoken } from '@/api/progress';
import ResultCard from '@/components/exercise/ResultCard.vue';
import MicPanel from '@/components/modules/speaking/MicPanel.vue';
import RoundSave from '@/components/modules/speaking/RoundSave.vue';
import SessionShell from '@/components/modules/speaking/SessionShell.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { SpeakingQuestion } from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import { sayAsync, saveQueue, wordCount } from '@/utils/speaking';
import { stopSpeak } from '@/utils/speech';

/** An answer of 4 words or more counts as a real answer. */
const MIN_WORDS = 4;

/**
 * A round of open questions, answered aloud. An answer long enough counts once per question
 * (3 XP); the model answer is shown after each try. Emits `again` for "More questions".
 */
export default defineComponent({
    name: 'QuestionRound',

    components: {
        Link,
        MicPanel,
        ResultCard,
        RoundSave,
        SessionShell,
        SpeakButton,
    },

    props: {
        items: { type: Array as PropType<SpeakingQuestion[]>, required: true },
        title: { type: String, required: true },
        /** Where ✕ and "Back" lead. */
        back: { type: String, required: true },
    },

    emits: ['again'],

    data() {
        return {
            index: 0,
            progress: 0,
            good: 0,
            counted: false,
            answer: null as {
                fine: boolean;
                text: string;
                words: number;
            } | null,
            sampleShown: false,
            finished: false,
            // XP of the answers, as the server gave it.
            xp: 0,
            saves: saveQueue(),
        };
    },

    computed: {
        question(): SpeakingQuestion | null {
            return this.items[this.index] ?? null;
        },

        resultLine(): string {
            return this.$t(
                'You answered :good of :total questions · +:xp XP ⚡',
                { good: this.good, total: this.items.length, xp: this.xp },
            );
        },
    },

    mounted() {
        this.show();
    },

    beforeUnmount() {
        stopSpeak();
    },

    methods: {
        show(): void {
            const question = this.question;

            if (!question) {
                this.finished = true;

                return;
            }

            this.progress = this.index;
            this.counted = false;
            this.answer = null;
            this.sampleShown = false;
            void sayAsync(question.q);
        },

        onHeard(heard: string[] | null): void {
            const text = heard === null ? '' : heard[0];
            const words = wordCount(text);
            const fine = heard === null || words >= MIN_WORDS;

            if (fine && !this.counted) {
                this.counted = true;
                this.good++;
                this.saves.add(async () => {
                    this.xp += (await countSpoken(1, 3)).xp;
                });
                sfx('ok');
            }

            this.answer = { fine, text, words };
            this.sampleShown = true;
        },

        nextQuestion(): void {
            this.index++;
            this.show();
        },

        /** The round is saved: celebrate a good one. */
        celebrate(): void {
            if (this.good >= 4) {
                confetti(1500);
                sfx('win');
            }
        },
    },
});
</script>
