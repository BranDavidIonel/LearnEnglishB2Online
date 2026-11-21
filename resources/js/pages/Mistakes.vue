<template>
    <Head :title="$t('My mistakes')" />

    <PageHeader
        :title="$t('🩹 My mistakes')"
        :sub="
            $t(
                'Questions you got wrong collect here. When you answer them correctly, they disappear.',
            )
        "
        :back="home().url"
    />
    <div>
        <ExerciseSession
            v-if="phase === 'session'"
            :items="practice"
            mode="practice"
            :title="$t('Reviewing your mistakes')"
            @finish="finish"
            @exit="phase = 'list'"
        />

        <SessionSave v-else-if="phase === 'result' && save" :save="save">
            <ResultCard
                :score="fixed / practice.length"
                :title="
                    $t('1 mistake fixed ✓|:count mistakes fixed ✓', {
                        count: fixed,
                    })
                "
                :lines="[
                    $t('+:xp XP ⚡ · :left left.', {
                        xp: fixed * xpPerAnswer,
                        left,
                    }),
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="backToList"
                    >
                        {{ $t('Continue') }}
                    </button>
                    <Link class="btn" :href="home().url">{{ $t('Home') }}</Link>
                </template>
            </ResultCard>
        </SessionSave>

        <EmptyState
            v-else-if="!items.length"
            icon="🎉"
            :title="$t('No mistakes to review')"
            :text="
                $t(
                    'When you make mistakes in exercises or tests, the questions show up here so you can practise them.',
                )
            "
        >
            <template #actions>
                <Link class="btn primary" :href="learn().url">
                    {{ $t('Back to lessons') }}
                </Link>
            </template>
        </EmptyState>

        <template v-else>
            <EmptyState
                icon="🩹"
                :title="
                    $t('1 question to review|:count questions to review', {
                        count: items.length,
                    })
                "
                :text="$t('We practise up to 15 at a time, with explanations.')"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="start"
                    >
                        {{ $t('▶ Practise') }}
                    </button>
                </template>
            </EmptyState>
            <div class="card">
                <h3>{{ $t('Where you make the most mistakes') }}</h3>
                <div class="mist-list">
                    <Link
                        v-for="row in byUnit"
                        :key="row.id"
                        class="mist-row"
                        :href="unitRoute(row.id).url"
                    >
                        <span>{{ row.label }}</span>
                        <ChipTag kind="warn">{{ row.count }}</ChipTag>
                    </Link>
                </div>
            </div>
        </template>
    </div>
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { addXp, clearMistake } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import ChipTag from '@/components/general/ChipTag.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import type { ResumableSave } from '@/components/modules/units/resumableSave';
import { resumableSave } from '@/components/modules/units/resumableSave';
import SessionSave from '@/components/modules/units/SessionSave.vue';
import type { KeyedItem, MistakeUnit } from '@/components/modules/units/types';
import { home, learn } from '@/routes';
import { show as unitRoute } from '@/routes/units';
import type { ExerciseResult } from '@/types';
import { sample } from '@/utils/random';

// Questions practised in one go, and the XP of each one fixed.
const SESSION_SIZE = 15;
const XP_PER_ANSWER = 2;

/**
 * "My mistakes": the questions answered wrong in exercises and tests, practised again up to
 * 15 at a time. A question answered right leaves the list.
 */
export default defineComponent({
    name: 'MistakesPage',

    components: {
        Head,
        Link,
        PageHeader,
        EmptyState,
        ExerciseSession,
        ResultCard,
        ChipTag,
        SessionSave,
    },

    props: {
        items: { type: Array as PropType<KeyedItem[]>, required: true },
        /** How the units of the questions are named, by unit id. */
        units: {
            type: Object as PropType<Record<string, MistakeUnit>>,
            required: true,
        },
    },

    data() {
        return {
            phase: 'list' as 'list' | 'session' | 'result',
            practice: [] as KeyedItem[],
            fixed: 0,
            left: 0,
            xpPerAnswer: XP_PER_ANSWER,
            save: null as ResumableSave | null,
        };
    },

    computed: {
        /** The units with the most questions to review first. */
        byUnit(): { id: string; label: string; count: number }[] {
            const counts = new Map<string, number>();

            for (const item of this.items) {
                counts.set(item.unit, (counts.get(item.unit) ?? 0) + 1);
            }

            return [...counts.entries()]
                .sort((a, b) => b[1] - a[1])
                .map(([id, count]) => {
                    const unit = this.units[id];

                    return {
                        id,
                        count,
                        label: `${unit.emoji} ${unit.level} · U${unit.order} · ${unit.grammarTitle}`,
                    };
                });
        },
    },

    methods: {
        home,
        learn,
        unitRoute,

        start(): void {
            this.practice = sample(
                this.items,
                Math.min(SESSION_SIZE, this.items.length),
            );
            this.phase = 'session';
        },

        /** The questions answered right leave the list, and give XP. */
        finish(results: ExerciseResult[]): void {
            const right = results.flatMap((result) =>
                result.correct && result.key ? [result.key] : [],
            );
            this.fixed = right.length;
            this.left = this.items.length;
            this.save = markRaw(
                resumableSave(
                    right.length
                        ? [
                              async () => {
                                  this.left = (
                                      await clearMistake(right)
                                  ).mistakes;
                              },
                              () => addXp(right.length * XP_PER_ANSWER),
                          ]
                        : [],
                ),
            );
            this.phase = 'result';
        },

        /** Back to the list, as it is now on the server. */
        backToList(): void {
            router.reload({
                only: ['items', 'units'],
                onSuccess: () => {
                    this.phase = 'list';
                },
            });
        },
    },
});
</script>
