<template>
    <AppModal open @close="$emit('close')">
        <template v-if="view === 'details'">
            <WordCard :word="current" />
            <div class="center mt">
                <ChipTag :kind="status.kind">{{ $t(status.label) }}</ChipTag>
                <span v-if="current.known" class="muted small">
                    {{ ' ' }}{{ $t('· marked as known by you') }}
                </span>
            </div>
            <div class="row center wrap mt">
                <template v-if="current.status !== 'learned'">
                    <button
                        type="button"
                        class="btn primary"
                        @click="startLesson"
                    >
                        {{ $t('🧠 Learn it now') }}
                    </button>
                    <button
                        type="button"
                        class="btn success"
                        @click="change(markKnown)"
                    >
                        {{ $t('✓ I already know it') }}
                    </button>
                </template>
                <button
                    v-if="current.status === 'new'"
                    type="button"
                    class="btn"
                    @click="addToFlashcards"
                >
                    {{ $t('➕ Add to flashcards') }}
                </button>
                <button
                    v-if="current.status === 'learned'"
                    type="button"
                    class="btn"
                    @click="change(learnWord)"
                >
                    {{ $t('↺ Practise it again') }}
                </button>
                <button
                    v-if="current.status !== 'new'"
                    type="button"
                    class="btn"
                    @click="change(forgetWord)"
                >
                    {{ $t('Remove from flashcards') }}
                </button>
            </div>
            <p v-if="unit" class="muted small center">
                {{ $t('From:') }}
                <Link :href="unitUrl" @click="$emit('close')">
                    {{
                        $t(':level · Unit :order · :title', {
                            level: unit.level,
                            order: unit.order,
                            title: unit.title,
                        })
                    }}
                </Link>
            </p>
        </template>

        <template v-else-if="view === 'learned'">
            <WordCard :word="current" />
            <div class="feedback good mt">
                <div class="fb-head">{{ $t('Learned! +3 XP ⚡') }}</div>
                <div>
                    {{
                        $t(
                            'The word is now in your flashcards and comes back tomorrow, so you do not forget it.',
                        )
                    }}
                </div>
            </div>
            <div class="row center mt">
                <button
                    type="button"
                    class="btn primary"
                    @click="$emit('close')"
                >
                    {{ $t('Done') }}
                </button>
            </div>
        </template>

        <template v-else>
            <div class="muted small center">
                {{ $t('Step :n of 3', { n: step }) }}
            </div>
            <template v-if="view === 'look'">
                <h3 class="center">
                    {{ $t('Look, listen and repeat aloud') }}
                </h3>
                <div>
                    <WordCard :word="current" />
                    <div class="row center mt">
                        <button type="button" class="btn primary" @click="pick">
                            {{ $t('Got it →') }}
                        </button>
                    </div>
                </div>
            </template>
            <template v-else-if="view === 'pick'">
                <h3 class="center">{{ $t('What does it mean?') }}</h3>
                <div>
                    <div class="vword">
                        <span>{{ current.en }}</span>
                        <SpeakButton :text="current.en" />
                    </div>
                    <div class="opts mt">
                        <button
                            v-for="(option, i) in options"
                            :key="i"
                            type="button"
                            class="opt"
                            :class="picked[i]"
                            :disabled="picked[i] === 'wrong'"
                            @click="choose(i)"
                        >
                            <span>{{ option }}</span>
                        </button>
                    </div>
                </div>
            </template>
            <template v-else>
                <h3 class="center">{{ $t('Type the word in English') }}</h3>
                <div class="center">
                    <div class="vro">{{ current.emoji }} {{ current.ro }}</div>
                    <input
                        ref="input"
                        v-model="typed"
                        class="gap-input wide"
                        :class="typedResult"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        autocapitalize="off"
                        placeholder="…"
                        @keydown.enter="check"
                    />
                    <div class="row center mt">
                        <button
                            type="button"
                            class="btn primary"
                            :disabled="saving"
                            @click="check"
                        >
                            {{ $t('Check') }}
                        </button>
                    </div>
                    <div class="mt">
                        <p v-if="typedResult === 'wrong'" class="muted">
                            {{ $t('Not quite. It is spelled:') }}
                            <strong>{{ current.en }}</strong>
                            {{ $t('— try once more.') }}
                        </p>
                    </div>
                </div>
            </template>
        </template>
    </AppModal>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import {
    addXp,
    forgetWord,
    gradeWord,
    learnWord,
    markKnown,
} from '@/api/progress';
import ChipTag from '@/components/general/ChipTag.vue';
import AppModal from '@/components/modals/AppModal.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { show } from '@/routes/units';
import type { WordsAnswer } from '@/types';
import { shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';
import { speak } from '@/utils/speech';
import { checkText } from '@/utils/text';
import { toast } from '@/utils/toast';
import { reportNotSaved } from './saving';
import { STATUS } from './status';
import type { ModalWord, UnitLabel } from './types';
import WordCard from './WordCard.vue';

type View = 'details' | 'look' | 'pick' | 'type' | 'learned';

/** XP for a word learned in the mini lesson. */
const LESSON_XP = 3;

/** Wait after the right meaning is picked, so the learner sees it turn green. */
const PICKED_PAUSE = 450;

/**
 * The details of a word (status, the actions of the original "word modal") and its three-step
 * mini lesson: look and listen → pick the meaning → type the word. With `lesson` it opens
 * straight into the lesson. Every change is saved at once and reported ("changed").
 */
export default defineComponent({
    name: 'WordModal',

    components: { AppModal, ChipTag, Link, SpeakButton, WordCard },

    props: {
        word: { type: Object as PropType<ModalWord>, required: true },
        /** The unit that teaches the word ("From: A1 · Unit 3 · …"). */
        unit: {
            type: Object as PropType<(UnitLabel & { id: string }) | null>,
            default: null,
        },
        /** Three wrong meanings for the "what does it mean?" step. */
        choices: { type: Array as PropType<string[]>, default: () => [] },
        lesson: { type: Boolean, default: false },
    },

    emits: ['close', 'changed'],

    data() {
        return {
            view: 'details' as View,
            current: { ...this.word },
            options: [] as string[],
            picked: [] as ('right' | 'wrong' | '')[],
            typed: '',
            typedResult: '' as 'right' | 'wrong' | '',
            // While the learned word is being saved (the Check button waits).
            saving: false,
            timer: 0,
        };
    },

    computed: {
        status(): { label: string; kind: string } {
            return STATUS[this.current.status];
        },

        step(): number {
            return this.view === 'look' ? 1 : this.view === 'pick' ? 2 : 3;
        },

        unitUrl(): string {
            return this.unit ? show(this.unit.id).url : '';
        },
    },

    mounted() {
        if (this.lesson) {
            this.startLesson();
        } else {
            this.showDetails();
        }
    },

    beforeUnmount() {
        window.clearTimeout(this.timer);
    },

    methods: {
        markKnown,
        learnWord,
        forgetWord,

        showDetails(): void {
            this.view = 'details';
            speak(this.current.en);
        },

        /**
         * Saves a change of status, then shows the details again with the new state. Returns
         * false when it could not be saved (the details stay as they were).
         */
        async change(
            save: (word: string) => Promise<WordsAnswer>,
        ): Promise<boolean> {
            try {
                this.updateFrom(await save(this.current.en));
            } catch (error) {
                reportNotSaved(error);

                return false;
            }

            this.showDetails();

            return true;
        },

        async addToFlashcards(): Promise<void> {
            if (await this.change(learnWord)) {
                toast(this.$t('Added to your flashcards'));
            }
        },

        updateFrom(answer: WordsAnswer): void {
            const fresh = answer.words.find(
                (word) => word.en === this.current.en,
            );

            if (fresh) {
                this.current = {
                    ...this.current,
                    status: fresh.status,
                    box: fresh.box,
                    known: fresh.known,
                };
            }

            this.$emit('changed', answer.words);
        },

        // ---------- the mini lesson ----------

        startLesson(): void {
            this.view = 'look';
            speak(this.current.en);
        },

        pick(): void {
            this.options = shuffle([this.current.ro, ...this.choices]);
            this.picked = this.options.map(() => '');
            this.view = 'pick';
            speak(this.current.en);
        },

        choose(i: number): void {
            if (this.options[i] !== this.current.ro) {
                sfx('bad');
                this.picked[i] = 'wrong';

                return;
            }

            sfx('ok');
            this.picked[i] = 'right';
            this.timer = window.setTimeout(this.typeIt, PICKED_PAUSE);
        },

        typeIt(): void {
            this.view = 'type';
            this.typed = '';
            this.typedResult = '';
            this.timer = window.setTimeout(() => this.input()?.focus(), 50);
        },

        input(): HTMLInputElement | null {
            return (this.$refs.input as HTMLInputElement | undefined) ?? null;
        },

        async check(): Promise<void> {
            if (!this.typed.trim() || this.view !== 'type' || this.saving) {
                return;
            }

            const ok = checkText(this.typed, [this.current.en]).ok;
            sfx(ok ? 'ok' : 'bad');
            this.typedResult = ok ? 'right' : 'wrong';
            speak(this.current.en);

            if (!ok) {
                this.input()?.select();

                return;
            }

            // "Learned! +3 XP" only once it is saved; if it is not, the learner can check again.
            this.saving = true;

            try {
                this.updateFrom(await learnWord(this.current.en));
                this.updateFrom(await gradeWord(this.current.en, 2));
                await addXp(LESSON_XP);
                this.view = 'learned';
            } catch (error) {
                reportNotSaved(error);
            } finally {
                this.saving = false;
            }
        },
    },
});
</script>
