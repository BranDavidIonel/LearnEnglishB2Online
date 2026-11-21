// The props of the course pages (app/Services/Course): the unit pages, the level exam,
// the placement test and "My mistakes".
import type { ExerciseItem, LessonStep, LevelId, Video } from '@/types';

/** What the step head needs to know about a unit. */
export interface StepHeadUnit {
    id: string;
    emoji: string;
    title: string;
    /** Lesson steps done: { vocab: true, … }. */
    steps: Record<string, boolean>;
    /** The unit test is passed. */
    passed: boolean;
}

/** A unit with the learner's state, as the step pages and result cards use it. */
export interface UnitHead extends StepHeadUnit {
    order: number;
    levelId: LevelId;
    /** Place in the level, from 0. */
    index: number;
    /** A failed level exam sent it back: its test must be passed again. */
    review: boolean;
    /** Best unit test score, 0..1. */
    best: number;
    /** Unit tests taken. */
    tries: number;
}

/** The unit as its overview shows it. */
export interface UnitOverview extends UnitHead {
    titleRo: string;
    goalRo: string;
    /** Romanian title of the grammar lesson. */
    grammarTitle: string;
    video: Video | null;
}

/** A level as the course pages name and colour it (title in the interface language). */
export interface LevelTag {
    id: LevelId;
    name: string;
    title: string;
    color: string;
}

export interface WordCounts {
    learned: number;
    learning: number;
    new: number;
    total: number;
}

/** A question with its mistake key ("<unit>:<n>", "<unit>:r<n>", "<unit>:t<n>"). */
export interface KeyedItem extends ExerciseItem {
    unit: string;
    key: string;
}

/** A question of a unit test; `fresh` marks a new one (not from the unit's exercises). */
export interface UnitTestItem extends KeyedItem {
    fresh: boolean;
}

/** A unit of the level, as a link of a result card. */
export interface UnitLink {
    id: string;
    order: number;
    title: string;
}

export interface UnitTestData {
    items: UnitTestItem[];
    /** Questions in a unit test. */
    size: number;
    /** The unit has new questions of its own. */
    hasNew: boolean;
    /** Lesson steps not done yet. */
    missing: LessonStep[];
    /** Every unit of the level. */
    units: UnitLink[];
}

export interface ReadingStep {
    title: string;
    text: string;
    textRo: string;
    questions: KeyedItem[];
}

export type ExamPageState = 'unavailable' | 'locked' | 'review' | 'ready';

export interface ExamUnit extends UnitLink {
    emoji: string;
}

/** A level of the placement test page. */
export interface PlacementLevel extends LevelTag {
    /** The first unit, where "Start" leads (null for a level without units). */
    firstUnit: string | null;
}

/** The questions of one level of the placement test. */
export interface PlacementPart {
    level: LevelId;
    items: ExerciseItem[];
}

/** How a unit is named in "where you make the most mistakes". */
export interface MistakeUnit {
    emoji: string;
    /** "A1", as in the content. */
    level: string;
    order: number;
    grammarTitle: string;
}
