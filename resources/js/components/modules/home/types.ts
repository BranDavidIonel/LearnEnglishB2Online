// The props of the home, learn, progress, grammar and videos pages (app/Services/Home,
// app/Http/Resources/Home). Content keeps the shapes of '@/types'.
import type {
    Grammar,
    LevelId,
    LevelSummary,
    UnitStep,
    UnitSummary,
    Video,
} from '@/types';

/** A level as the cards name it. */
export interface LevelTag {
    id: LevelId;
    name: string;
    color: string;
}

/** How many words (or verbs) are learned, being learned and new. */
export interface ReviewCounts {
    learned: number;
    learning: number;
    new: number;
    total: number;
}

/** The big card of the home page. */
export interface NextStep {
    /** "welcome": nothing done yet; otherwise the next action of the course. */
    kind: 'welcome' | 'unit' | 'exam' | 'done';
    level: LevelTag | null;
    unit: {
        id: string;
        order: number;
        title: string;
        titleRo: string;
        emoji: string;
    } | null;
    step: UnitStep | null;
    /** The unit was sent back to review by a failed level exam. */
    review: boolean;
}

/** "Learn 20 new words" on the home page. */
export interface WordsToLearn {
    /** The level the next words come from. */
    level: LevelTag;
    /** The level being worked on: its vocabulary is the goal. */
    current: LevelTag;
    goal: ReviewCounts;
    /** Words still to learn in the open units of `level`. */
    left: number;
    /** Words handed out per quick-sort batch. */
    batch: number;
}

/** The "road to B2" card. */
export interface Road {
    /** Name of the highest level done ("A2"), null when A1 is not done. */
    achieved: string | null;
    /** Name of the level being worked on. */
    current: string;
    levels: LevelSummary[];
    /** Progress (0..1) and vocabulary towards B1 and towards B2. */
    goals: { level: LevelTag; progress: number; vocab: ReviewCounts }[];
}

export interface HomeCounts {
    words: ReviewCounts;
    verbs: ReviewCounts;
    /** The placement test was taken. */
    placement: boolean;
}

/** A unit card of the learning path. */
export interface CourseUnit extends UnitSummary {
    /** Title of the unit's grammar. */
    grammar: string;
}

/** A level of the learning path. */
export interface LearnLevel {
    level: LevelSummary;
    units: CourseUnit[];
    /** Words of the level learned, of all its words. */
    words: { learned: number; total: number };
    /** The level is open and its open units still have words to learn. */
    canLearnWords: boolean;
    /** Units of the level waiting for review. */
    review: number;
}

/** A day of the chart history: the numbers at the end of that day. */
export interface Snapshot {
    xp: number;
    w?: number;
    v?: number;
    b1?: number;
    b2?: number;
}

export interface ChartData {
    /** XP earned per day, by "YYYY-MM-DD". */
    days: Record<string, number>;
    history: Record<string, Snapshot>;
    /** Daily XP goal. */
    goal: number;
}

export interface ProgressStats {
    units: { passed: number; total: number };
    words: ReviewCounts;
    verbs: ReviewCounts;
}

export interface Badge {
    icon: string;
    name: string;
    desc: string;
    ok: boolean;
}

/** A row of the grammar list. */
export interface GrammarRow {
    id: string;
    order: number;
    title: string;
    titleRo: string;
    /** The grammar step of the unit is done. */
    done: boolean;
}

export interface GrammarLevel {
    level: LevelId;
    units: GrammarRow[];
}

/** One unit's grammar lesson. */
export interface GrammarUnit {
    id: string;
    order: number;
    grammar: Grammar;
}

/** A unit with a video. */
export interface VideoUnit {
    id: string;
    order: number;
    title: string;
    /** The level as the unit file writes it: "A1". */
    level: string;
    /** Title of the unit's grammar. */
    grammar: string;
    video: Video;
}

export interface VideoLevel {
    level: LevelId;
    units: VideoUnit[];
}
