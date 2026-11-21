<template>
    <div class="dash">
        <NextCard :next="next" :pass-mark="passMark" />
        <WordsCard v-if="wordsToLearn" :words="wordsToLearn" />
        <div class="stats">
            <div class="card stat">
                <div class="stat-icon">🔥</div>
                <div class="stat-num">{{ $learner.streak }}</div>
                <div class="muted">
                    {{ $t('day streak', { count: $learner.streak }) }}
                </div>
            </div>
            <div class="card stat">
                <ProgressRing
                    :value="Math.min(1, $learner.xpToday / $learner.goal)"
                    color="#facc15"
                    label="⚡"
                    :size="56"
                />
                <div class="stat-num">
                    {{ $learner.xpToday }} / {{ $learner.goal }}
                </div>
                <div class="muted">{{ $t('XP today') }}</div>
            </div>
            <Link class="card stat link" :href="wordsUrl">
                <div class="stat-icon">📖</div>
                <div class="stat-num">{{ counts.words.learned }}</div>
                <div class="muted">
                    {{
                        $t('words learned of :total', {
                            total: counts.words.total,
                        })
                    }}
                </div>
            </Link>
            <Link
                class="card stat link"
                :class="{ pulse: $learner.due.words > 0 }"
                :href="flashcardsUrl"
            >
                <div class="stat-icon">🗂️</div>
                <div class="stat-num">{{ $learner.due.words }}</div>
                <div class="muted">
                    {{
                        $t(
                            'flashcard to review today|flashcards to review today',
                            { count: $learner.due.words },
                        )
                    }}
                </div>
            </Link>
        </div>
        <RoadCard :road="road" />
        <div class="grid2">
            <div class="card">
                <h3>{{ $t('📅 Last 7 days') }}</h3>
                <WeekChart :days="week" :goal="$learner.goal" />
            </div>
            <ShortcutsCard
                :verb-counts="counts.verbs"
                :mistake-count="$learner.due.mistakes"
                :placement-taken="counts.placement"
            />
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import WeekChart from '@/components/charts/WeekChart.vue';
import ProgressRing from '@/components/general/ProgressRing.vue';
import NextCard from '@/components/modules/home/NextCard.vue';
import RoadCard from '@/components/modules/home/RoadCard.vue';
import ShortcutsCard from '@/components/modules/home/ShortcutsCard.vue';
import type {
    HomeCounts,
    NextStep,
    Road,
    WordsToLearn,
} from '@/components/modules/home/types';
import WordsCard from '@/components/modules/home/WordsCard.vue';
import { flashcards, words } from '@/routes';

/** The start page: what to do next, today's numbers, the road to B2, the last seven days and the shortcuts. */
export default defineComponent({
    name: 'HomePage',

    components: {
        Link,
        NextCard,
        ProgressRing,
        RoadCard,
        ShortcutsCard,
        WeekChart,
        WordsCard,
    },

    props: {
        next: { type: Object as PropType<NextStep>, required: true },
        /** Null when no open unit has words left to learn. */
        wordsToLearn: {
            type: Object as PropType<WordsToLearn | null>,
            default: null,
        },
        road: { type: Object as PropType<Road>, required: true },
        /** XP of the last seven days, oldest first. */
        week: {
            type: Array as PropType<{ date: string; xp: number }[]>,
            required: true,
        },
        counts: { type: Object as PropType<HomeCounts>, required: true },
        /** Pass mark of a level exam, 0..1. */
        passMark: { type: Number, required: true },
    },

    computed: {
        wordsUrl(): string {
            return words().url;
        },

        flashcardsUrl(): string {
            return flashcards().url;
        },
    },
});
</script>
