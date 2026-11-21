<template>
    <Head :title="$t('Pairs')" />
    <PageHeader
        :title="$t('🧩 Pairs')"
        :sub="$t('Flip two cards: an English word and its translation.')"
        :back="games().url"
    />
    <div :key="round">
        <div class="muted center">{{ $t('Moves: :n', { n: moves }) }}</div>
        <div class="mem-grid">
            <button
                v-for="(card, i) in cards"
                :key="i"
                class="mem"
                :class="{ open: card.open, found: card.found }"
                @click="flip(card)"
            >
                <span class="mem-back">?</span>
                <span class="mem-front" :class="{ en: card.en }">
                    <small v-if="card.en">{{ card.emoji }}</small>
                    {{ card.text }}
                </span>
            </button>
        </div>
        <GameResult
            v-if="finished"
            :title="
                record
                    ? $t('New record: :n moves! 🏆', { n: moves })
                    : $t('Done in :n moves!', { n: moves })
            "
            line="+10 XP ⚡"
            :again="$t('🔁 New game')"
            @again="start"
        />
    </div>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { addXp, gameRecord } from '@/api/progress';
import PageHeader from '@/components/layout/PageHeader.vue';
import GameResult from '@/components/modules/games/GameResult.vue';
import type { GameWord } from '@/components/modules/games/types';
import { games } from '@/routes';
import { confetti } from '@/utils/confetti';
import { sample, shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';
import { speak, stopSpeak } from '@/utils/speech';

interface MemoryCard {
    /** The word both cards of a pair point to. */
    pair: number;
    text: string;
    en: boolean;
    emoji: string;
    open: boolean;
    found: boolean;
}

const PAIRS = 8;
const XP = 10;
// How long two cards that do not match stay face up.
const SHOW_MISS_MS = 900;

/** Pairs: flip two cards, an English word and its Romanian translation; the fewer moves, the better. */
export default defineComponent({
    name: 'GamesMemory',

    components: { GameResult, Head, PageHeader },

    props: {
        /** The words the learner has met. */
        words: { type: Array as PropType<GameWord[]>, required: true },
    },

    data() {
        return {
            // False once the page is left: an answer arriving later changes nothing.
            alive: true,
            round: 0,
            cards: [] as MemoryCard[],
            open: [] as MemoryCard[],
            found: 0,
            moves: 0,
            locked: false,
            finished: false,
            record: false,
            missTimer: undefined as ReturnType<typeof setTimeout> | undefined,
            endConfetti: null as null | (() => void),
        };
    },

    created() {
        this.start();
    },

    beforeUnmount() {
        this.alive = false;
        clearTimeout(this.missTimer);
        this.endConfetti?.();
        stopSpeak();
    },

    methods: {
        games,

        start(): void {
            const words = sample(this.words, PAIRS);
            clearTimeout(this.missTimer);
            this.cards = shuffle(
                words.flatMap((word, pair): MemoryCard[] => [
                    {
                        pair,
                        text: word.en,
                        en: true,
                        emoji: word.emoji,
                        open: false,
                        found: false,
                    },
                    {
                        pair,
                        text: word.ro,
                        en: false,
                        emoji: '',
                        open: false,
                        found: false,
                    },
                ]),
            );
            this.open = [];
            this.found = 0;
            this.moves = 0;
            this.locked = false;
            this.finished = false;
            this.record = false;
            this.round++;
        },

        flip(card: MemoryCard): void {
            if (this.locked || card.open || card.found) {
                return;
            }

            card.open = true;
            sfx('flip');

            if (card.en) {
                speak(card.text);
            }

            this.open.push(card);

            if (this.open.length < 2) {
                return;
            }

            this.moves++;
            const [a, b] = this.open;
            this.open = [];

            if (a.pair === b.pair && a.en !== b.en) {
                a.found = true;
                b.found = true;
                sfx('ok');

                if (++this.found === this.cards.length / 2) {
                    void this.finish();
                }
            } else {
                this.locked = true;
                this.missTimer = setTimeout(() => {
                    a.open = false;
                    b.open = false;
                    this.locked = false;
                }, SHOW_MISS_MS);
            }
        },

        /** Keeps the record (fewer moves) and adds the XP, then celebrates. */
        async finish(): Promise<void> {
            const round = this.round;
            const answer = await gameRecord('memory', this.moves).catch(
                () => null,
            );
            void addXp(XP).catch(() => null);

            if (!this.alive || round !== this.round) {
                return;
            }

            this.record = answer?.record ?? false;
            this.finished = true;
            this.endConfetti = confetti();
            sfx('win');
        },
    },
});
</script>
