// The props of the help page (app/Services/Help/HelpPage, resources/content/help.json).
import type { LevelTag, ReviewCounts } from '@/components/modules/home/types';

export type HelpTab = 'sounds' | 'plan' | 'tips';

/** A course word that shows a sound: as its own entry writes it. */
export interface SoundExample {
    en: string;
    ipa: string;
    ro: string;
}

/** One pronunciation sign (or a few that read alike), explained through Romanian. */
export interface Sound {
    symbol: string;
    likeRo: string;
    textRo: string | null;
    examples: SoundExample[];
}

export interface SoundGroup {
    id: string;
    titleRo: string;
    noteRo: string | null;
    sounds: Sound[];
}

export interface SoundGuideData {
    introRo: string;
    groups: SoundGroup[];
}

/** The parts of the app a line of the plan can send the learner to. */
export type PlanLink =
    | 'flashcards'
    | 'words'
    | 'learn'
    | 'speaking'
    | 'mistakes'
    | 'cambridge'
    | 'grammar'
    | 'progress';

export interface PlanStep {
    emoji: string;
    titleRo: string;
    textRo: string;
    /** Only the daily steps say how long they take. */
    minutes?: number;
    link: PlanLink;
}

/** The vocabulary up to a level and the days left to meet its new words, one batch a day. */
export interface PlanGoal {
    level: LevelTag;
    vocab: ReviewCounts;
    days: number;
}

export interface StudyPlanData {
    introRo: string;
    daily: PlanStep[];
    shortDayRo: string;
    weekly: PlanStep[];
    milestones: PlanStep[];
    batch: number;
    goals: PlanGoal[];
}

export interface StudyTip {
    emoji: string;
    titleRo: string;
    textRo: string;
}
