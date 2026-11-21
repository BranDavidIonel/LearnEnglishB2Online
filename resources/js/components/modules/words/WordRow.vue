<template>
    <div class="word-row" @click="$emit('open')">
        <span class="word-emoji">{{ word.emoji }}</span>
        <span class="word-en">
            <strong>{{ word.en }}</strong>
            <small class="muted">{{ word.ipa }}</small>
        </span>
        <span class="word-ro">{{ word.ro }}</span>
        <LevelChip :level="word.levelId" />
        <ChipTag :kind="status.kind">{{ $t(status.label) }}</ChipTag>
        <span class="word-box" :title="$t('How well you know it')">
            <i v-for="n in 4" :key="n" :class="{ on: filled(n) }"></i>
        </span>
        <span class="word-acts">
            <button
                v-if="word.status === 'learned'"
                type="button"
                class="icon-btn word-act"
                :title="$t('I\'ve forgotten it — practise it again')"
                :aria-label="$t('I\'ve forgotten it — practise it again')"
                @click.stop="relearn"
            >
                ↺
            </button>
            <template v-else>
                <button
                    type="button"
                    class="icon-btn word-act"
                    :title="$t('I already know it')"
                    :aria-label="$t('I already know it')"
                    @click.stop="known"
                >
                    ✓
                </button>
                <button
                    type="button"
                    class="icon-btn word-act"
                    :title="$t('Learn it now')"
                    :aria-label="$t('Learn it now')"
                    @click.stop="$emit('learn')"
                >
                    🧠
                </button>
            </template>
            <SpeakButton :text="word.en" />
        </span>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { learnWord, markKnown } from '@/api/progress';
import ChipTag from '@/components/general/ChipTag.vue';
import LevelChip from '@/components/general/LevelChip.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { WordsAnswer } from '@/types';
import { STATUS } from './status';
import type { WordListRow } from './types';

/**
 * One row of a word list: the word, its status, how well it is known (four dots) and the
 * quick actions. A click on the row opens the details ("open"), 🧠 the mini lesson ("learn");
 * ✓ and ↺ save at once and report the new state ("changed").
 */
export default defineComponent({
    name: 'WordRow',

    components: { ChipTag, LevelChip, SpeakButton },

    props: {
        word: { type: Object as PropType<WordListRow>, required: true },
    },

    emits: ['open', 'learn', 'changed'],

    computed: {
        status(): { label: string; kind: string } {
            return STATUS[this.word.status];
        },
    },

    methods: {
        /** The n-th dot (from 1) is lit once the word has passed that many boxes. */
        filled(n: number): boolean {
            return (this.word.box ?? -1) >= n;
        },

        known(): Promise<void> {
            return this.save(markKnown);
        },

        relearn(): Promise<void> {
            return this.save(learnWord);
        },

        /** The row changes only once the server has saved the change. */
        async save(
            change: (word: string) => Promise<WordsAnswer>,
        ): Promise<void> {
            try {
                this.$emit('changed', (await change(this.word.en)).words);
            } catch {
                // send() has told the learner; the row stays as it was.
            }
        },
    },
});
</script>
