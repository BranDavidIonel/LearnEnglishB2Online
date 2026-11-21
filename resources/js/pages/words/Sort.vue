<template>
    <Head :title="$t('Words')" />
    <PageHeader
        :title="$t('📚 :level words', { level: level.name })"
        :sub="
            $t(
                'Check what you know quickly, :n words at a time: see each one, say whether you knew it, and the new ones go into your flashcards.',
                { n: batchSize },
            )
        "
        :back="words().url"
    />

    <div>
        <EmptyState
            v-if="!level.unlocked"
            icon="🔒"
            :title="$t('Level locked')"
            :text="$t('This level\'s words open together with its units.')"
        />

        <SortSession
            v-else-if="session"
            :key="run"
            :words="session"
            :title="$t('📚 :level words', { level: level.name })"
            @exit="session = null"
            @saved="reload"
        >
            <template #actions>
                <button
                    v-if="!stale && todo?.count"
                    type="button"
                    class="btn primary big"
                    @click="start"
                >
                    {{ $t('▶ :n more', { n: next }) }}
                </button>
                <button type="button" class="btn" @click="session = null">
                    {{ $t('Back') }}
                </button>
            </template>
        </SortSession>

        <template v-else-if="stats && todo">
            <WordStats :counts="stats" :color="level.color" />
            <p v-if="!level.done" class="muted small center">
                {{
                    $t(
                        'Only the words of the units you have already opened are included.',
                    )
                }}
            </p>
            <div class="row center wrap">
                <button
                    v-if="todo.count"
                    type="button"
                    class="btn primary big"
                    @click="start"
                >
                    {{
                        $t('▶ The next :n (:left left)', {
                            n: next,
                            left: todo.count,
                        })
                    }}
                </button>
                <ChipTag v-else kind="ok">
                    {{ $t('✓ Nothing new to go through here') }}
                </ChipTag>
                <Link class="btn" :href="words().url">
                    {{ $t('📖 All words') }}
                </Link>
            </div>
        </template>
    </div>
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import SortSession from '@/components/modules/words/SortSession.vue';
import type {
    SortTodo,
    SortWord,
    WordCounts,
} from '@/components/modules/words/types';
import WordStats from '@/components/modules/words/WordStats.vue';
import { words } from '@/routes';
import type { LevelSummary } from '@/types';

/**
 * The quick sort of a level's words (the original "sortView"): how many are learned, and the
 * next batch of the words still to learn in the units already open. After a batch the page
 * reloads its numbers and the next batch.
 */
export default defineComponent({
    name: 'WordsSortPage',

    components: {
        ChipTag,
        EmptyState,
        Head,
        Link,
        PageHeader,
        SortSession,
        WordStats,
    },

    props: {
        level: { type: Object as PropType<LevelSummary>, required: true },
        /** Null while the level is locked. */
        stats: { type: Object as PropType<WordCounts | null>, default: null },
        todo: { type: Object as PropType<SortTodo | null>, default: null },
        batchSize: { type: Number, required: true },
    },

    data() {
        return {
            session: null as SortWord[] | null,
            // A new key for every batch, so each one starts afresh.
            run: 0,
            // True from the start of a batch until the numbers after it have arrived.
            stale: false,
        };
    },

    computed: {
        next(): number {
            return Math.min(this.batchSize, this.todo?.count ?? 0);
        },
    },

    methods: {
        words,

        start(): void {
            this.session = this.todo?.batch ?? null;
            this.stale = true;
            this.run++;
        },

        reload(): void {
            router.reload({
                only: ['stats', 'todo'],
                onFinish: () => {
                    this.stale = false;
                },
            });
        },
    },
});
</script>
