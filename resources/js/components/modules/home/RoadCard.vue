<template>
    <div class="card">
        <h3>{{ $t('🗺️ Your road to B2') }}</h3>
        <p class="muted">
            {{
                road.achieved
                    ? $t('Level reached: :achieved. Now working on :current.', {
                          achieved: road.achieved,
                          current: road.current,
                      })
                    : $t('Now working on level :current.', {
                          current: road.current,
                      })
            }}
        </p>
        <div class="road">
            <Link
                v-for="level in road.levels"
                :key="level.id"
                :href="learnUrl"
                class="road-step"
                :class="{ done: level.done, locked: !level.unlocked }"
                :style="{ '--c': level.color }"
            >
                <ProgressRing
                    :value="level.progress"
                    :color="level.color"
                    :label="ringLabel(level)"
                    :size="72"
                />
                <strong>{{ level.name }}</strong>
                <small class="muted">{{ level.title }}</small>
            </Link>
        </div>
        <div class="to-goals">
            <div
                v-for="goal in road.goals"
                :key="goal.level.id"
                class="to-goal"
            >
                <div class="row between">
                    <span>
                        {{
                            $t('Progress towards :level', {
                                level: goal.level.name,
                            })
                        }}
                    </span>
                    <strong>{{ Math.round(goal.progress * 100) }}%</strong>
                </div>
                <ProgressBar :value="goal.progress" :color="goal.level.color" />
            </div>
            <VocabGoal
                v-for="goal in road.goals"
                :key="`vocab-${goal.level.id}`"
                :stats="goal.vocab"
                :level="goal.level"
            />
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import ProgressRing from '@/components/general/ProgressRing.vue';
import VocabGoal from '@/components/general/VocabGoal.vue';
import type { Road } from '@/components/modules/home/types';
import { learn } from '@/routes';
import type { LevelSummary } from '@/types';

/** "Your road to B2": a ring per level, then the progress and the vocabulary towards B1 and B2. */
export default defineComponent({
    name: 'RoadCard',

    components: { Link, ProgressBar, ProgressRing, VocabGoal },

    props: {
        road: { type: Object as PropType<Road>, required: true },
    },

    computed: {
        learnUrl(): string {
            return learn().url;
        },
    },

    methods: {
        /** ✓ for a level done, its percentage while open, a lock otherwise. */
        ringLabel(level: LevelSummary): string {
            if (level.done) {
                return '✓';
            }

            return level.unlocked
                ? `${Math.round(level.progress * 100)}%`
                : '🔒';
        },
    },
});
</script>
