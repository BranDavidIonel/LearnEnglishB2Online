<template>
    <Head :title="$t('Word bank')" />
    <UnitStepHead :unit="unit" :color="level.color" :current="null" />

    <div class="step-body">
        <EmptyState
            v-if="!rows.length"
            icon="📚"
            :title="$t('The word bank is not available yet')"
        />

        <SortSession
            v-else-if="session"
            :key="run"
            :words="session"
            :title="$t('📚 :title · word bank', { title: unit.title })"
            @exit="session = null"
            @saved="reload"
        >
            <template #actions>
                <button
                    v-if="!stale && todo.count"
                    type="button"
                    class="btn primary big"
                    @click="start"
                >
                    {{ $t('▶ :n more', { n: next }) }}
                </button>
                <button type="button" class="btn" @click="session = null">
                    {{ $t('Word list') }}
                </button>
                <Link class="btn" :href="show(unit.id).url">
                    {{ $t('Back to unit') }}
                </Link>
            </template>
        </SortSession>

        <template v-else>
            <h2 class="center">{{ $t('📚 Word bank') }}</h2>
            <p class="muted center">
                {{
                    $t(
                        '1 word in common use at :level level, on top of the :core in “New words”. Go through them :batch at a time: say whether you knew each one, and the new ones go into your flashcards.|:count words in common use at :level level, on top of the :core in “New words”. Go through them :batch at a time: say whether you knew each one, and the new ones go into your flashcards.',
                        {
                            count: rows.length,
                            level: level.name,
                            core: unit.vocab,
                            batch: batchSize,
                        },
                    )
                }}
            </p>
            <WordStats :counts="counts" :color="level.color" />
            <div class="row center wrap">
                <button
                    v-if="todo.count"
                    type="button"
                    class="btn primary big"
                    @click="start"
                >
                    {{ $t('▶ Learn the next :n', { n: next }) }}
                </button>
                <ChipTag v-else kind="ok">
                    {{ $t('✓ You have been through them all') }}
                </ChipTag>
                <Link class="btn" :href="flashcards().url">
                    {{ $t('🗂️ Flashcards') }}
                </Link>
            </div>
            <div class="word-list mt">
                <WordRow
                    v-for="row in rows"
                    :key="row.en"
                    :word="row"
                    @open="open(row, false)"
                    @learn="open(row, true)"
                    @changed="changed"
                />
            </div>
        </template>
    </div>

    <WordModal
        v-if="selected"
        :key="selected.word.en"
        :word="selected.word"
        :unit="modalUnit"
        :choices="choices[selected.word.en] ?? []"
        :lesson="selected.lesson"
        @changed="changed"
        @close="selected = null"
    />
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import UnitStepHead from '@/components/modules/units/UnitStepHead.vue';
import { applyStates, countRows } from '@/components/modules/words/rows';
import SortSession from '@/components/modules/words/SortSession.vue';
import type {
    BankUnit,
    SortTodo,
    SortWord,
    UnitLabel,
    WordCounts,
    WordStates,
} from '@/components/modules/words/types';
import WordModal from '@/components/modules/words/WordModal.vue';
import WordRow from '@/components/modules/words/WordRow.vue';
import WordStats from '@/components/modules/words/WordStats.vue';
import { flashcards } from '@/routes';
import { show } from '@/routes/units';
import type { LevelId, WordWithState } from '@/types';

/**
 * The word bank of a unit (the original "bankStep"): the unit's extra words with their status,
 * sorted 20 at a time like the quick sort of a level. Rows and details save at once; after a
 * batch the page reloads the words and the next batch.
 */
export default defineComponent({
    name: 'WordsBankPage',

    components: {
        ChipTag,
        EmptyState,
        Head,
        Link,
        SortSession,
        UnitStepHead,
        WordModal,
        WordRow,
        WordStats,
    },

    props: {
        unit: { type: Object as PropType<BankUnit>, required: true },
        level: {
            type: Object as PropType<{
                id: LevelId;
                name: string;
                color: string;
            }>,
            required: true,
        },
        words: { type: Array as PropType<WordWithState[]>, required: true },
        /** Three wrong meanings per word, for the mini lesson. */
        choices: {
            type: Object as PropType<Record<string, string[]>>,
            required: true,
        },
        /** "From: …" of the units that teach the words. */
        units: {
            type: Object as PropType<Record<string, UnitLabel>>,
            required: true,
        },
        todo: { type: Object as PropType<SortTodo>, required: true },
        batchSize: { type: Number, required: true },
    },

    data() {
        return {
            rows: this.words.map((word) => ({ ...word })),
            session: null as SortWord[] | null,
            // A new key for every batch, so each one starts afresh.
            run: 0,
            // True from the start of a batch until the numbers after it have arrived.
            stale: false,
            selected: null as { word: WordWithState; lesson: boolean } | null,
        };
    },

    computed: {
        counts(): WordCounts {
            return countRows(this.rows);
        },

        next(): number {
            return Math.min(this.batchSize, this.todo.count);
        },

        modalUnit(): (UnitLabel & { id: string }) | null {
            const id = this.selected?.word.unit;
            const label = id ? this.units[id] : undefined;

            return id && label ? { id, ...label } : null;
        },
    },

    watch: {
        words(words: WordWithState[]): void {
            this.rows = words.map((word) => ({ ...word }));
        },
    },

    methods: {
        flashcards,
        show,

        start(): void {
            this.session = this.todo.batch;
            this.stale = true;
            this.run++;
        },

        reload(): void {
            router.reload({
                only: ['words', 'todo'],
                onFinish: () => {
                    this.stale = false;
                },
            });
        },

        open(row: WordWithState, lesson: boolean): void {
            this.selected = { word: row, lesson };
        },

        /** A row or the details saved something: show the new states, and fetch the next batch again. */
        changed(words: WordStates): void {
            applyStates(this.rows, words);
            router.reload({ only: ['todo'] });
        },
    },
});
</script>
