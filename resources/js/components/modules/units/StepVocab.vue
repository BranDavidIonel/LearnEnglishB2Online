<template>
    <template v-if="phase === 'cards'">
        <p class="muted center">
            {{
                $t(':count words · ← → to navigate · Space for audio', {
                    count: words.length,
                })
            }}
        </p>
        <div class="vcard">
            <div :key="index" class="vcard-in fade-in">
                <WordPicture class="big" :word="word" />
                <div class="vword">
                    <span>{{ word.en }}</span>
                    <SpeakButton :text="word.en" />
                </div>
                <div class="vipa">
                    {{ word.ipa }} · <em>{{ word.pos }}</em>
                </div>
                <div class="vro">{{ word.ro }}</div>
                <div class="vex">
                    <SpeakButton :text="word.ex" />
                    <div>
                        <div class="en">{{ word.ex }}</div>
                        <div class="ro">{{ word.exRo }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dots">
            <span
                v-for="(item, n) in words"
                :key="item.en"
                class="dot"
                :class="{ now: n === index, done: n < index }"
                @click="show(n)"
            />
        </div>
        <div class="row center">
            <button
                type="button"
                class="btn"
                :disabled="index === 0"
                @click="previous"
            >
                ← {{ $t('Back') }}
            </button>
            <button type="button" class="btn primary" @click="next">
                {{ last ? $t('Quick check →') : $t('Next →') }}
            </button>
        </div>
    </template>

    <ExerciseSession
        v-else-if="phase === 'check'"
        :items="check"
        mode="practice"
        :title="$t('Quick check: do you remember the words?')"
        @finish="finish"
        @exit="backToUnit"
    />

    <SessionSave v-else-if="phase === 'result' && save" :save="save">
        <ResultCard
            :score="score"
            :title="$t('The words are now in your flashcards 🗂️')"
            :lines="[
                first
                    ? $t(
                          '+:xp XP ⚡ · You\'ll review them at growing intervals so you don\'t forget them.',
                          { xp },
                      )
                    : $t('You can review them anytime in “Flashcards”.'),
            ]"
        >
            <template #actions>
                <Link
                    class="btn primary big"
                    :href="stepRoute({ unit: unit.id, step: 'grammar' }).url"
                >
                    {{ $t('Next: Grammar →') }}
                </Link>
                <button type="button" class="btn" @click="backToUnit">
                    {{ $t('Back to unit') }}
                </button>
            </template>
        </ResultCard>
    </SessionSave>
</template>

<script lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { completeStep } from '@/api/progress';
import WordPicture from '@/components/content/WordPicture.vue';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { show as unitRoute, step as stepRoute } from '@/routes/units';
import type { ExerciseItem, ExerciseResult, VocabWord } from '@/types';
import { sample, shuffle } from '@/utils/random';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { speak, stopSpeak } from '@/utils/speech';
import type { UnitHead } from './types';

// Questions of the quick check, and wrong options in each one.
const CHECK_SIZE = 6;
const WRONG_OPTIONS = 3;

/**
 * Step 1: the unit's new words as cards (picture, pronunciation, example, read aloud), then a
 * quick check of what they mean. Finishing it puts the words into the flashcard deck.
 */
export default defineComponent({
    name: 'StepVocab',

    components: {
        Link,
        WordPicture,
        SpeakButton,
        ExerciseSession,
        ResultCard,
        SessionSave,
    },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        words: { type: Array as PropType<VocabWord[]>, required: true },
    },

    data() {
        return {
            phase: 'cards' as 'cards' | 'check' | 'result',
            index: 0,
            check: [] as ExerciseItem[],
            score: 0,
            first: false,
            xp: 0,
            save: null as SaveQueue | null,
        };
    },

    computed: {
        word(): VocabWord {
            return this.words[this.index];
        },

        last(): boolean {
            return this.index === this.words.length - 1;
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKey);
        speak(this.word.en);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.onKey);
        stopSpeak();
    },

    methods: {
        stepRoute,

        show(n: number): void {
            this.index = n;
            speak(this.word.en);
        },

        previous(): void {
            if (this.index > 0) {
                this.show(this.index - 1);
            }
        },

        next(): void {
            if (!this.last) {
                this.show(this.index + 1);
            } else {
                this.startCheck();
            }
        },

        onKey(event: KeyboardEvent): void {
            if (event.key === 'ArrowRight') {
                this.next();
            } else if (event.key === 'ArrowLeft') {
                this.previous();
            } else if (event.key === ' ') {
                event.preventDefault();
                speak(this.word.en);
            }
        },

        startCheck(): void {
            document.removeEventListener('keydown', this.onKey);
            this.check = sample(
                this.words,
                Math.min(CHECK_SIZE, this.words.length),
            ).map((word) => this.question(word));
            this.phase = 'check';
        },

        /** "What does **word** mean?" with the right translation and three others of the unit. */
        question(word: VocabWord): ExerciseItem {
            const others = this.words.filter((other) => other.ro !== word.ro);
            const options = shuffle([
                word.ro,
                ...sample(others, WRONG_OPTIONS).map((other) => other.ro),
            ]);

            return {
                ex: {
                    type: 'mc',
                    q: this.$t('What does **:word** mean?', { word: word.en }),
                    options,
                    answer: options.indexOf(word.ro),
                    explainRo: `**${word.en}** = ${word.ro}. ${word.ex}`,
                },
            };
        },

        finish(results: ExerciseResult[]): void {
            this.score =
                results.filter((result) => result.correct).length /
                results.length;
            this.save = markRaw(
                saveQueue([
                    async () => {
                        const answer = await completeStep(
                            this.unit.id,
                            'vocab',
                        );
                        this.first = answer.first;
                        this.xp = answer.xp;
                    },
                ]),
            );
            this.phase = 'result';
        },

        backToUnit(): void {
            router.visit(unitRoute(this.unit.id).url);
        },
    },
});
</script>
