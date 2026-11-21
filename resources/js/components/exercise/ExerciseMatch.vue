<template>
    <div class="match">
        <div v-for="side in sides" :key="side" class="match-col">
            <button
                v-for="tile in columns[side]"
                :key="tile.id"
                type="button"
                class="match-item"
                :class="{
                    sel: selected?.side === side && selected.id === tile.id,
                    done: done.includes(tile.id),
                    shake: shaking.includes(`${side}${tile.id}`),
                }"
                :disabled="done.includes(tile.id)"
                @click="tap(side, tile)"
            >
                {{ tile.text }}
            </button>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { MatchExercise } from '@/types';
import { shuffle } from '@/utils/random';
import { sfx } from '@/utils/sound';
import { speak } from '@/utils/speech';
import type { AnswerOutcome } from './types';

type Side = 'en' | 'ro';

/** One word of a column; `id` is the index of its pair. */
interface Tile {
    id: number;
    text: string;
}

const SHAKE_MS = 400;

/**
 * Match pairs: English words on the left, Romanian on the right, both shuffled. Tapping an English
 * word reads it aloud. Right when every pair is found with at most one wrong try.
 */
export default defineComponent({
    name: 'ExerciseMatch',

    props: {
        ex: { type: Object as PropType<MatchExercise>, required: true },
    },

    emits: ['answer'],

    data() {
        return {
            sides: ['en', 'ro'] as Side[],
            columns: {
                en: shuffle(
                    this.ex.pairs.map(([en], id) => ({ id, text: en })),
                ),
                ro: shuffle(
                    this.ex.pairs.map(([, ro], id) => ({ id, text: ro })),
                ),
            } as Record<Side, Tile[]>,
            selected: null as { side: Side; id: number } | null,
            // Ids of the pairs found.
            done: [] as number[],
            // Tiles shaking after a wrong pair: side + id.
            shaking: [] as string[],
            errors: 0,
            timers: [] as ReturnType<typeof setTimeout>[],
        };
    },

    beforeUnmount() {
        this.timers.forEach(clearTimeout);
    },

    methods: {
        tap(side: Side, tile: Tile): void {
            if (this.done.includes(tile.id)) {
                return;
            }

            if (side === 'en') {
                speak(tile.text);
            }

            // First tile of a pair, or another tile of the same column: (re)select it.
            if (!this.selected || this.selected.side === side) {
                this.selected = { side, id: tile.id };

                return;
            }

            const other = this.selected;
            this.selected = null;

            if (other.id === tile.id) {
                this.done.push(tile.id);
                sfx('flip');

                if (this.done.length === this.ex.pairs.length) {
                    const outcome: AnswerOutcome = {
                        correct: this.errors <= 1,
                        answer: null,
                    };
                    this.$emit('answer', outcome);
                }

                return;
            }

            this.errors++;
            this.shake([`${side}${tile.id}`, `${other.side}${other.id}`]);
        },

        shake(keys: string[]): void {
            this.shaking.push(...keys);
            this.timers.push(
                setTimeout(() => {
                    this.shaking = this.shaking.filter(
                        (k) => !keys.includes(k),
                    );
                }, SHAKE_MS),
            );
        },
    },
});
</script>
