<template>
    <section
        class="level"
        :class="{ locked: !level.unlocked }"
        :style="{ '--c': level.color }"
    >
        <div class="level-head">
            <ProgressRing
                :value="level.progress"
                :color="level.color"
                :label="level.name"
                :size="76"
            />
            <div class="grow">
                <h2>
                    {{ level.name }} · {{ level.title }}
                    <ChipTag v-if="level.passed" kind="ok">
                        {{ $t('✓ Finished') }}
                    </ChipTag>
                    <ChipTag v-else-if="level.skipped" kind="ok">
                        {{ $t('✓ Credited by the placement test') }}
                    </ChipTag>
                    <ChipTag v-else-if="level.unlocked" kind="now">
                        {{ $t('In progress') }}
                    </ChipTag>
                    <ChipTag v-else kind="lock">{{ $t('🔒 Locked') }}</ChipTag>
                </h2>
                <p class="muted">{{ level.description }}</p>
                <ProgressBar :value="level.progress" :color="level.color" />
                <p v-if="item.words.total" class="small level-words">
                    📚
                    {{
                        $t(':level words: :learned / :total learned', {
                            level: level.name,
                            learned: item.words.learned,
                            total: item.words.total,
                        })
                    }}
                    <template v-if="item.canLearnWords">
                        ·
                        <Link :href="sortUrl">
                            {{ $t('learn them :n at a time →', { n: batch }) }}
                        </Link>
                    </template>
                </p>
            </div>
        </div>

        <p v-if="!item.units.length" class="muted">
            {{ $t('Lessons for this level are not available yet.') }}
        </p>
        <div v-else-if="!level.unlocked && previous" class="locked-note">
            {{
                $t(
                    '🔒 Pass the :level level exam to unlock this level — or take the',
                    { level: previous },
                )
            }}
            <Link :href="placementUrl">{{ $t('placement test') }}</Link>
            {{ $t('if you already know more.') }}
        </div>

        <div class="units">
            <component
                :is="unit.unlocked ? 'Link' : 'div'"
                v-for="unit in item.units"
                :key="unit.id"
                class="unit-card"
                :class="{ locked: !unit.unlocked }"
                :href="unit.unlocked ? unitUrl(unit.id) : undefined"
                :title="
                    unit.unlocked
                        ? undefined
                        : $t('Finish the previous unit first')
                "
            >
                <div class="unit-top">
                    <span class="unit-emoji">{{ unit.emoji }}</span>
                    <span class="unit-badge">{{ unitBadge(unit) }}</span>
                </div>
                <div class="unit-n">{{ $t('Unit :n', { n: unit.order }) }}</div>
                <div class="unit-title">{{ unit.title }}</div>
                <div class="unit-ro">{{ unit.titleRo }}</div>
                <div class="unit-gram">📐 {{ unit.grammar }}</div>
                <div v-if="unit.review" class="unit-warn">
                    {{ $t('Needs review') }}
                </div>
                <ProgressBar :value="unit.progress" :color="level.color" />
            </component>

            <component
                :is="level.unlocked ? 'Link' : 'div'"
                v-if="item.units.length"
                class="unit-card exam"
                :class="{ locked: !level.unlocked }"
                :href="level.unlocked ? examUrl : undefined"
            >
                <div class="unit-top">
                    <span class="unit-emoji">🎓</span>
                    <span class="unit-badge">{{
                        level.passed ? '🏆' : ''
                    }}</span>
                </div>
                <div class="unit-n">{{ $t('Level exam') }}</div>
                <div class="unit-title">
                    {{ $t(':level level exam', { level: level.name }) }}
                </div>
                <div class="unit-ro">
                    {{
                        item.review
                            ? $t(
                                  'First review 1 unit|First review :count units',
                                  { count: item.review },
                              )
                            : $t('30 questions · at least :pct%', {
                                  pct: Math.round(passMark * 100),
                              })
                    }}
                </div>
                <div v-if="level.exam" class="unit-gram">
                    {{
                        $t('Best score: :pct%', {
                            pct: Math.round(level.exam.best * 100),
                        })
                    }}
                </div>
            </component>
        </div>
    </section>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import ProgressRing from '@/components/general/ProgressRing.vue';
import type { CourseUnit, LearnLevel } from '@/components/modules/home/types';
import { placement } from '@/routes';
import exams from '@/routes/exams';
import units from '@/routes/units';
import wordRoutes from '@/routes/words';
import type { LevelSummary } from '@/types';

/** One level of the learning path: its head (ring, state, words), then its unit cards and its exam. */
export default defineComponent({
    name: 'LevelSection',

    components: { ChipTag, Link, ProgressBar, ProgressRing },

    props: {
        item: { type: Object as PropType<LearnLevel>, required: true },
        /** Name of the level before it ("A2"), for the lock note; null for the first level. */
        previous: { type: String as PropType<string | null>, default: null },
        /** Words handed out per quick-sort batch. */
        batch: { type: Number, required: true },
        /** Pass mark of the level exam, 0..1. */
        passMark: { type: Number, required: true },
    },

    computed: {
        level(): LevelSummary {
            return this.item.level;
        },

        sortUrl(): string {
            return wordRoutes.sort(this.level.id).url;
        },

        examUrl(): string {
            return exams.show(this.level.id).url;
        },

        placementUrl(): string {
            return placement().url;
        },
    },

    methods: {
        unitUrl(id: string): string {
            return units.show(id).url;
        },

        /** ⚠️ in review, ⭐ passed, 🔒 locked, nothing while open. */
        unitBadge(unit: CourseUnit): string {
            if (unit.review) {
                return '⚠️';
            }

            if (unit.passed) {
                return '⭐';
            }

            return unit.unlocked ? '' : '🔒';
        },
    },
});
</script>
