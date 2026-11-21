// The props of the Cambridge pages (app/Services/Cambridge/CambridgePages).
import type {
    CambridgePaperId,
    CambridgeResult,
    ContentLevel,
    Readiness,
} from '@/types';

/** An exam as every Cambridge page names it. */
export interface ExamTag {
    /** "pet" or "fce". */
    id: string;
    name: string;
    level: ContentLevel;
}

/** An exam of the list, with the verdict on the learner. */
export interface ExamSummary extends ExamTag {
    /** Number of mock tests. */
    tests: number;
    /** Average of the latest results, null without any. */
    overall: number | null;
    verdict: Readiness;
}

/** The exam page's exam: what the real exam is like. */
export interface ExamDetails extends ExamTag {
    aboutRo: string;
    papersRo: { name: string; info: string }[];
}

export interface TestTag {
    id: string;
    /** "Simulare N". */
    title: string;
}

/** A paper of a mock test on the exam page. */
export interface PaperSize {
    id: CambridgePaperId;
    minutes: number;
    /** Questions (Reading, Listening) or tasks (Writing, Speaking). */
    count: number;
}

/** A mock test on the exam page, with the learner's latest result of each paper. */
export interface TestSummary extends TestTag {
    papers: PaperSize[];
    results: Partial<Record<CambridgePaperId, CambridgeResult>>;
}

/** A Reading or Listening answer: the option chosen or the text typed. */
export type PaperAnswer = number | string;
