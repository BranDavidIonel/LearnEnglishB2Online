// The shapes of the word pages (WordController, WordSortController, WordBankController,
// FlashcardController and app/Http/Resources/Words).
import type { LevelId, ReviewStatus, VocabWord, WordWithState } from '@/types';

/** The tabs of "My words" ('all' is the page without a tab in the URL; 'extra': the extra words). */
export type WordTab = 'all' | ReviewStatus | 'extra';

/** How many words are learned, being learned and still to learn. */
export interface WordCounts {
    learned: number;
    learning: number;
    new: number;
    total: number;
}

/**
 * A row of "My words" (WordRowResource): what the row, the filters and the counts need. An
 * extra word (from the glossary) has no level and may have no pronunciation or emoji.
 */
export interface WordListRow {
    en: string;
    ro: string;
    ipa: string | null;
    emoji: string | null;
    levelId: LevelId | null;
    status: ReviewStatus;
    /** 0..6; null or left out when not in the deck. */
    box?: number | null;
    /** Left out when false. */
    known?: boolean;
}

/** The rest of a word of "My words", sent after the list (WordPages::details()). */
export type WordDetails = Pick<VocabWord, 'pos' | 'ex' | 'exRo' | 'wiki'> & {
    /** The unit that teaches it first. */
    unit: string;
};

/** What the word details and the mini lesson show. */
export type ModalWord = VocabWord &
    Pick<WordWithState, 'unit' | 'levelId' | 'status' | 'box' | 'known'>;

/** "A1 · Unit 3 · Title" of a unit, by unit id. */
export interface UnitLabel {
    level: string;
    order: number;
    title: string;
}

/** A word of a quick-sort batch: the four meanings of its quiz question, the right one among them. */
export interface SortWord extends WordWithState {
    options: string[];
}

/** The words still to sort: how many, and the next batch. */
export interface SortTodo {
    count: number;
    batch: SortWord[];
}

/** What a row, the details and the quick sort report back: the words with their new state. */
export type WordStates = Pick<
    WordWithState,
    'en' | 'status' | 'box' | 'known'
>[];

/** The unit of the word-bank page: what its step head needs, and how many core words it has. */
export interface BankUnit {
    id: string;
    emoji: string;
    title: string;
    /** Lesson steps done: { vocab: true, … }. */
    steps: Record<string, boolean>;
    passed: boolean;
    /** Words of the "New words" step. */
    vocab: number;
}
