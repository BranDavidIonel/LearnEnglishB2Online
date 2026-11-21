<template>
    <div class="result fade-in">
        <div class="big-level" :style="{ '--c': level.color }">
            {{ level.name }}
        </div>
        <h2>
            {{
                mastered
                    ? $t("Wow — you've already mastered :level!", {
                          level: level.name,
                      })
                    : $t('Your starting level: :level · :title', {
                          level: level.name,
                          title: level.title,
                      })
            }}
        </h2>
        <p class="muted">{{ advice }}</p>
        <div class="place-scores">
            <template v-for="id in tested" :key="id">
                <div v-if="scores[id] !== undefined" class="place-row">
                    <strong>{{ nameOf(id) }}</strong>
                    <ProgressBar
                        :value="scores[id] ?? 0"
                        :color="colorOf(id)"
                    />
                    <span>{{ Math.round((scores[id] ?? 0) * 100) }}%</span>
                </div>
                <div v-else class="place-row muted">
                    <strong>{{ nameOf(id) }}</strong>
                    <span>
                        {{
                            $t(
                                '— not tested (the earlier questions were too hard)',
                            )
                        }}
                    </span>
                </div>
            </template>
        </div>
        <div class="row center wrap">
            <Link
                v-if="level.firstUnit"
                class="btn primary big start"
                :href="unitRoute(level.firstUnit).url"
            >
                {{ $t('▶ Start :level', { level: level.name }) }}
            </Link>
            <Link class="btn" :href="learn().url">
                {{ $t('See all lessons') }}
            </Link>
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import { learn } from '@/routes';
import { show as unitRoute } from '@/routes/units';
import type { LevelId, PlacementAnswer } from '@/types';
import type { PlacementLevel } from './types';

/** The end of the placement test: the starting level, the score of every part, where to begin. */
export default defineComponent({
    name: 'PlacementResult',

    components: { Link, ProgressBar },

    props: {
        answer: { type: Object as PropType<PlacementAnswer>, required: true },
        levels: { type: Array as PropType<PlacementLevel[]>, required: true },
        /** The levels the test has questions for, in order. */
        tested: { type: Array as PropType<LevelId[]>, required: true },
        scores: {
            type: Object as PropType<Partial<Record<LevelId, number>>>,
            required: true,
        },
        /** 0..1 */
        passMark: { type: Number, required: true },
    },

    computed: {
        level(): PlacementLevel {
            return this.levels[this.answer.start];
        },

        /** Passed even the last level tested. */
        mastered(): boolean {
            return (this.scores[this.level.id] ?? 0) >= this.passMark;
        },

        advice(): string {
            if (this.mastered) {
                return this.$t(
                    'You can use the course for review and the Cambridge practice tests to prepare.',
                );
            }

            return this.answer.start > 0
                ? this.$t(
                      'The earlier levels are unlocked (you can go through them anytime to review). We start with :level.',
                      { level: this.level.name },
                  )
                : this.$t(
                      "Perfect — we'll start with the basics and build up step by step.",
                  );
        },
    },

    methods: {
        learn,
        unitRoute,

        nameOf(id: LevelId): string {
            return this.levels.find((level) => level.id === id)?.name ?? '';
        },

        colorOf(id: LevelId): string {
            return this.levels.find((level) => level.id === id)?.color ?? '';
        },
    },
});
</script>
