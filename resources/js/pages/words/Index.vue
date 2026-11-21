<template>
    <Head :title="$t('Words')" />
    <PageHeader
        :title="$t('📖 My words')"
        :sub="
            $t(
                'A word becomes “learned” after you know it :n times in a row on the flashcards — or when you say you already know it.',
                { n: learnedBox },
            )
        "
        :back="home().url"
    />

    <div class="card">
        <div class="row between">
            <span>{{ $t('Vocabulary learned') }}</span>
            <strong>{{ counts.learned }} / {{ counts.total }}</strong>
        </div>
        <ProgressBar
            :value="counts.total ? counts.learned / counts.total : 0"
            color="#22c55e"
        />
    </div>

    <div>
        <div v-if="skipped" class="card notice">
            <div>
                <strong>{{
                    $t('You skipped levels in the placement test.')
                }}</strong>
                {{
                    $t(
                        '1 word from the earlier levels is still “to learn”. The honest way is a quick check, :batch at a time: see each word, say whether you knew it, and the new ones go into your flashcards. Or mark them all as known (you can change any of them later).|:count words from the earlier levels are still “to learn”. The honest way is a quick check, :batch at a time: see each word, say whether you knew it, and the new ones go into your flashcards. Or mark them all as known (you can change any of them later).',
                        { count: skipped.words.length, batch: batchSize },
                    )
                }}
            </div>
            <div class="row wrap">
                <Link class="btn primary" :href="sort(skipped.level).url">
                    {{ $t("🔎 Check them quickly: know / don't know") }}
                </Link>
                <button type="button" class="btn success" @click="knowSkipped">
                    {{ $t('✓ Yes, I know them all') }}
                </button>
                <button type="button" class="btn" @click="keepSkipped">
                    {{ $t('No, I want to go through them') }}
                </button>
            </div>
        </div>
    </div>

    <WordTabs :counts="counts" :verbs="verbs" :active="tab" />

    <div class="row wrap filters">
        <input
            class="search"
            type="search"
            :placeholder="$t('🔍 Search in English or Romanian…')"
            @input="search"
        />
        <select v-model="level">
            <option value="all">{{ $t('All levels') }}</option>
            <option v-for="item in levels" :key="item.id" :value="item.id">
                {{ item.name }}
            </option>
        </select>
        <span class="muted">{{
            $t('1 word|:count words', { count: shown.length })
        }}</span>
        <span>
            <button
                v-if="notLearned.length"
                type="button"
                class="btn"
                @click="knowShown"
            >
                {{
                    $t('✓ I know all of these (:n)', {
                        n: notLearned.length,
                    })
                }}
            </button>
        </span>
    </div>
    <p class="muted small">
        {{
            $t(
                '✓ = I already know it · 🧠 = learn it now · ↺ = practise it again · click a row for details',
            )
        }}
    </p>

    <div class="word-list">
        <WordRow
            v-for="row in shown.slice(0, limit)"
            :key="row.en"
            :word="row"
            @open="open(row, false)"
            @learn="open(row, true)"
            @changed="changed"
        />
        <p v-if="!shown.length" class="muted center">
            {{ $t('No words match these filters.') }}
        </p>
    </div>
    <div class="row center mt">
        <button
            v-if="shown.length > limit"
            type="button"
            class="btn"
            @click="limit += page"
        >
            {{ $t('Show more (:n left)', { n: shown.length - limit }) }}
        </button>
    </div>

    <WordModal
        v-if="modalWord"
        :key="modalWord.en"
        :word="modalWord"
        :unit="modalUnit"
        :choices="choices"
        :lesson="selected?.lesson ?? false"
        @changed="changed"
        @close="selected = null"
    />
</template>

<script lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { markKnown, setFlag } from '@/api/progress';
import ProgressBar from '@/components/general/ProgressBar.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import {
    applyStates,
    countRows,
    lessonChoices,
} from '@/components/modules/words/rows';
import { reportNotSaved } from '@/components/modules/words/saving';
import type {
    ModalWord,
    UnitLabel,
    WordCounts,
    WordDetails,
    WordListRow,
    WordStates,
    WordTab,
} from '@/components/modules/words/types';
import WordModal from '@/components/modules/words/WordModal.vue';
import WordRow from '@/components/modules/words/WordRow.vue';
import WordTabs from '@/components/modules/words/WordTabs.vue';
import { home } from '@/routes';
import { sort } from '@/routes/words';
import type { LevelId } from '@/types';
import type { LevelInfo } from '@/utils/levels';
import { LEVELS } from '@/utils/levels';
import { toast } from '@/utils/toast';

/** Rows drawn at first, and added by each "Show more". */
const PAGE = 300;

/** The skipped-level banner: the words still "to learn" and where their quick check starts. */
interface Skipped {
    words: string[];
    level: LevelId;
}

/**
 * "My words" (the original "wordsView"): every word of the course with its status, filtered by
 * tab, level and search right in the page, 300 rows at a time. Each row and the word details
 * save a change at once; the rows and the counts follow the answer, and the skipped-level
 * banner is reloaded from the server.
 */
export default defineComponent({
    name: 'WordsIndexPage',

    components: {
        Head,
        Link,
        PageHeader,
        ProgressBar,
        WordModal,
        WordRow,
        WordTabs,
    },

    props: {
        tab: { type: String as PropType<WordTab>, required: true },
        words: { type: Array as PropType<WordListRow[]>, required: true },
        /** Deferred: arrives just after the page. */
        details: {
            type: Object as PropType<Record<string, WordDetails> | undefined>,
            default: undefined,
        },
        /** Deferred, with the details. */
        units: {
            type: Object as PropType<Record<string, UnitLabel> | undefined>,
            default: undefined,
        },
        skipped: { type: Object as PropType<Skipped | null>, default: null },
        verbs: { type: Number, required: true },
        learnedBox: { type: Number, required: true },
        batchSize: { type: Number, required: true },
    },

    data() {
        return {
            rows: this.words.map((word) => ({ ...word })),
            level: 'all' as LevelId | 'all',
            query: '',
            limit: PAGE,
            page: PAGE,
            levels: LEVELS as LevelInfo[],
            selected: null as { en: string; lesson: boolean } | null,
            choices: [] as string[],
        };
    },

    computed: {
        counts(): WordCounts {
            return countRows(this.rows);
        },

        shown(): WordListRow[] {
            return this.rows.filter(
                (row) =>
                    (this.tab === 'all' || row.status === this.tab) &&
                    (this.level === 'all' || row.levelId === this.level) &&
                    (!this.query ||
                        row.en.toLowerCase().includes(this.query) ||
                        row.ro.toLowerCase().includes(this.query)),
            );
        },

        notLearned(): WordListRow[] {
            return this.shown.filter((row) => row.status !== 'learned');
        },

        /** The word of the open modal, once its details have arrived. */
        modalWord(): ModalWord | null {
            const row = this.selected
                ? this.rows.find((item) => item.en === this.selected?.en)
                : undefined;
            const details = row ? this.details?.[row.en] : undefined;

            return row && details
                ? {
                      ...row,
                      ...details,
                      box: row.box ?? null,
                      known: row.known ?? false,
                  }
                : null;
        },

        modalUnit(): (UnitLabel & { id: string }) | null {
            const id = this.modalWord?.unit;
            const label = id ? this.units?.[id] : undefined;

            return id && label ? { id, ...label } : null;
        },
    },

    watch: {
        // A full reload of the page brings the list again.
        words(words: WordListRow[]): void {
            this.rows = words.map((word) => ({ ...word }));
        },

        tab(): void {
            this.limit = PAGE;
        },

        level(): void {
            this.limit = PAGE;
        },
    },

    methods: {
        home,
        sort,

        search(event: Event): void {
            this.query = (event.target as HTMLInputElement).value
                .trim()
                .toLowerCase();
            this.limit = PAGE;
        },

        open(row: WordListRow, lesson: boolean): void {
            this.choices = lessonChoices(row, this.rows);
            this.selected = { en: row.en, lesson };
        },

        /** A row, the details or a bulk action saved something: show the new states. */
        changed(words: WordStates): void {
            applyStates(this.rows, words);
            router.reload({ only: ['skipped'] });
        },

        async knowShown(): Promise<void> {
            const words = this.notLearned.map((row) => row.en);

            if (
                !window.confirm(
                    this.$t(
                        'Mark the 1 word shown in this list as known?|Mark the :count words shown in this list as known?',
                        { count: words.length },
                    ),
                )
            ) {
                return;
            }

            await this.saveKnown(words);
        },

        async knowSkipped(): Promise<void> {
            if (!this.skipped) {
                return;
            }

            const count = this.skipped.words.length;

            if (await this.saveKnown(this.skipped.words)) {
                toast(
                    this.$t(
                        '1 word marked as known ✓|:count words marked as known ✓',
                        { count },
                    ),
                );
            }
        },

        async keepSkipped(): Promise<void> {
            try {
                await setFlag('keepSkippedWords', true);
            } catch (error) {
                reportNotSaved(error);

                return;
            }

            router.reload({ only: ['skipped'] });
        },

        /** Marks words known; false when it could not be saved (the rows stay as they were). */
        async saveKnown(words: string[]): Promise<boolean> {
            try {
                this.changed((await markKnown(words)).words);
            } catch (error) {
                reportNotSaved(error);

                return false;
            }

            return true;
        },
    },
});
</script>
