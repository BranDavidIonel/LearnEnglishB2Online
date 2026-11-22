// The learner's progress as the server sends it: the Resources of app/Http/Resources and the
// answers of the progress API (resources/js/api/progress.ts). See docs/backend.md.
import type { IrregularVerb, VocabWord } from './content';
import type { Learner, LevelId, Settings } from './models';

/** A lesson step of a unit (the unit test is "test", it is not a lesson step). */
export type LessonStep =
    | 'vocab'
    | 'grammar'
    | 'dialogue'
    | 'practice'
    | 'reading';
export type UnitStep = LessonStep | 'test';

/** Where a word or a verb is in the spaced repetition. */
export type ReviewStatus = 'learned' | 'learning' | 'new';

export type WordGrade = 0 | 1 | 2;
export type VerbGrade = 0 | 2;
export type GameId = 'memory' | 'sprint' | 'spell';
export type SpeakingGameId = 'pairs' | 'sprint';
export type CambridgePaperId = 'reading' | 'listening' | 'writing' | 'speaking';
export type Readiness = 'ready' | 'close' | 'notyet' | 'unknown';
export type ProgressFlag = 'keepSkippedWords';

/** A unit with the learner's state (UnitResource). */
export interface UnitSummary {
    id: string;
    order: number;
    title: string;
    titleRo: string;
    emoji: string;
    levelId: LevelId;
    /** Place in the level, from 0. */
    index: number;
    unlocked: boolean;
    /** 0..1: the lesson steps are worth 80%, a passed test 100%. */
    progress: number;
    passed: boolean;
    /** A failed level exam sent it back: its test must be passed again. */
    review: boolean;
    /** Best unit test score, 0..1. */
    best: number;
    /** Unit tests taken. */
    tries: number;
    /** Lesson steps done, in lesson order. */
    steps: LessonStep[];
}

/** The learner's exam of a level. */
export interface ExamState {
    best: number;
    passed: boolean;
    tries: number;
    /** Day of the last attempt (Y-m-d). */
    last: string | null;
}

/** A level with the learner's state (LevelResource). */
export interface LevelSummary {
    id: LevelId;
    /** "A1". */
    name: string;
    /** In the interface language: "Beginner". */
    title: string;
    description: string;
    color: string;
    index: number;
    /** How many units it has. */
    units: number;
    unlocked: boolean;
    /** Passed, or skipped by the placement test. */
    done: boolean;
    passed: boolean;
    /** Below the placement level, exam not passed: counts as known. */
    skipped: boolean;
    progress: number;
    exam: ExamState | null;
}

/** The review state the server adds to a word or a verb. */
export interface ReviewState {
    status: ReviewStatus;
    /** 0..6, null when not in the deck. */
    box: number | null;
    /** Next review (Y-m-d), null when not in the deck. */
    due: string | null;
    /** The learner said "I already know it". */
    known: boolean;
}

/** A word of the course (word index) with the learner's state (WordResource). */
export interface WordWithState extends VocabWord, ReviewState {
    /** The unit that teaches it first. */
    unit: string;
    levelId: LevelId;
    /** From a word bank rather than the unit's core words. */
    bank: boolean;
}

/**
 * An extra word (a glossary word the learner put into the flashcards) with its state, as the
 * flashcards and the word answers send it: what the glossary has, no example, no unit, no level.
 * Extra words never count in the course numbers.
 */
export interface ExtraWordWithState extends ReviewState {
    en: string;
    ro: string;
    ipa: string | null;
    pos: string | null;
    emoji: null;
    ex: null;
    exRo: null;
    unit: null;
    levelId: null;
    bank: false;
    extra: true;
}

/** How a tapped word form relates to its headword ("alerts" is the plural of "alert"). */
export type WordRelation =
    | 'plural'
    | 'third'
    | 'past'
    | 'participle'
    | 'ing'
    | 'comparative'
    | 'superlative'
    | 'possessive'
    | 'form';

/** What the word window shows for a word tapped in a lesson (WordLookupResource). */
export interface WordLookup {
    /** As tapped (curly apostrophe made straight). */
    word: string;
    found: boolean;
    source: 'course' | 'glossary' | 'name' | null;
    /** The headword the tapped form belongs to. */
    base: string | null;
    /** How `word` relates to `base`; null when they are the same word. */
    relation: WordRelation | null;
    entry: {
        en: string;
        ro: string;
        ipa: string | null;
        pos: string | null;
        /** Example sentence of the course entry. */
        ex: string | null;
        exRo: string | null;
        emoji: string | null;
        wiki: string | null;
        /** Null for glossary words and names. */
        levelId: LevelId | null;
        unit: string | null;
    } | null;
    /**
     * The learner's state of the headword; null when it cannot be learned with the word
     * functions (a name, a word found nowhere, an irregular verb that is not a course word).
     */
    state: {
        status: ReviewStatus;
        /** 0 when not in the deck. */
        box: number;
        due: string | null;
        known: boolean;
    } | null;
    /** The three forms when the headword is an irregular verb. */
    verb: {
        v1: string;
        v2: string;
        v3: string;
        ipa: string;
        ro: string;
    } | null;
    /** Expressions of the course found in the sentence that contain the tapped word. */
    phrases: { en: string; ro: string }[];
}

/** An irregular verb with the learner's state (VerbResource). */
export interface VerbWithState extends IrregularVerb, ReviewState {
    /** 1 = the most used verb. */
    rank: number;
    levelId: LevelId;
}

/** The placement result. */
export interface PlacementState {
    /** Index of the starting level. */
    start: number;
    level: LevelId | null;
    scores: Partial<Record<LevelId, number>>;
    date: string | null;
}

/** A stored Cambridge paper result. */
export interface CambridgeResult {
    score: number;
    correct?: number;
    total?: number;
    /** Right answers per part. */
    parts?: number[];
    /** Y-m-d. */
    date: string;
}

export interface CambridgeReadiness {
    /** The latest result of each paper over every test. */
    latest: Partial<Record<CambridgePaperId, CambridgeResult>>;
    /** 0..1 of the course up to the exam's level. */
    course: number;
    /** Average of the latest results, null without any. */
    overall: number | null;
    verdict: Readiness;
    /** Papers with a result (0..4). */
    done: number;
}

/** Every answer of the progress API carries the fresh learner summary. */
export interface ProgressAnswer {
    learner: Learner;
}

export interface StepAnswer extends ProgressAnswer {
    /** First time this step was done. */
    first: boolean;
    xp: number;
    unit: UnitSummary;
}

export interface UnitTestAnswer extends ProgressAnswer {
    passed: boolean;
    /** Passed for the first time. */
    first: boolean;
    xp: number;
    unit: UnitSummary;
    /** The next unit of the level (null after the last one). */
    next: UnitSummary | null;
    /** Units of the level still in review. */
    review: string[];
}

export interface ExamAnswer extends ProgressAnswer {
    /** 0..1. */
    score: number;
    passed: boolean;
    first: boolean;
    xp: number;
    /** Units with less than 60% right (sent back to review when the exam is failed). */
    weak: string[];
    byUnit: Record<string, { ok: number; n: number }>;
    level: LevelSummary;
    /** The next level (null after B2). */
    next: LevelSummary | null;
    /** The units of the level, review flags updated. */
    units: UnitSummary[];
}

export interface PlacementAnswer extends ProgressAnswer {
    start: number;
    level: LevelId;
    xp: number;
    placement: PlacementState;
    levels: LevelSummary[];
}

export interface WordsAnswer extends ProgressAnswer {
    /** The words of the request, with their new state (an extra word as ExtraWordWithState). */
    words: (WordWithState | ExtraWordWithState)[];
}

/** The answer of the word window's lookup (a read: no learner summary). */
export interface WordLookupAnswer {
    lookup: WordLookup;
}

export interface VerbsAnswer extends ProgressAnswer {
    verbs: VerbWithState[];
}

export interface XpAnswer extends ProgressAnswer {
    xp: number;
}

export interface MistakesAnswer extends ProgressAnswer {
    /** Keys that were added (not already in the list). */
    added: string[];
    /** Keys that were taken off. */
    cleared: string[];
    /** Questions left to practise. */
    mistakes: number;
}

export interface SpokenAnswer extends ProgressAnswer {
    xp: number;
    /** Sentences said in total. */
    said: number;
}

export interface ConversationAnswer extends ProgressAnswer {
    first: boolean;
    xp: number;
    /** Best score of this conversation, 0..1. */
    best: number;
}

export interface SpeakingRecordAnswer extends ProgressAnswer {
    record: boolean;
    best: Partial<Record<SpeakingGameId, number>>;
}

export interface GameRecordAnswer extends ProgressAnswer {
    record: boolean;
    records: Partial<Record<GameId, number>>;
}

export interface CambridgeAnswer extends ProgressAnswer {
    result: CambridgeResult;
    xp: number;
    readiness: CambridgeReadiness;
}

export interface SettingsAnswer extends ProgressAnswer {
    settings: Settings;
}

export interface GoalAnswer extends ProgressAnswer {
    goal: number;
}

export interface FlagsAnswer extends ProgressAnswer {
    flags: Partial<Record<ProgressFlag, boolean>>;
}

/** A Cambridge paper result as the page sends it. */
export interface CambridgeResultInput {
    score: number;
    correct?: number;
    total?: number;
    parts?: number[];
}
