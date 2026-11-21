// What the Cambridge pages share: the papers' icons and names, the test titles, the verdict
// texts and the colours of a score (props of app/Services/Cambridge/CambridgePages).
import { trans } from '@/i18n';
import type { CambridgePaperId, Readiness } from '@/types';

/** The four papers in the order of the exam. */
export const PAPERS: CambridgePaperId[] = [
    'reading',
    'listening',
    'writing',
    'speaking',
];

export const PAPER_ICONS: Record<CambridgePaperId, string> = {
    reading: '📖',
    listening: '🎧',
    writing: '✍️',
    speaking: '🗣️',
};

const PAPER_NAMES: Record<CambridgePaperId, string> = {
    reading: 'Reading',
    listening: 'Listening',
    writing: 'Writing',
    speaking: 'Speaking',
};

/** Colours of the rings and bars, as in the original. */
export const COLORS = {
    ok: '#22c55e',
    warn: '#facc15',
    bad: '#ef4444',
    none: '#64748b',
    exam: '#a78bfa',
};

/** How a score compares with the safe target and the "close" score. */
export type ScoreKind = 'ok' | 'warn' | 'bad';

/** The paper's name as the exam calls it (FCE reading includes Use of English). */
export function paperName(examId: string, paper: CambridgePaperId): string {
    return examId === 'fce' && paper === 'reading'
        ? 'Reading & Use of English'
        : PAPER_NAMES[paper];
}

/** The tests are titled "Simulare N" in the content: "Mock test N" in the English interface. */
export function testTitle(title: string): string {
    return title.replace(/^Simulare/, trans('Mock test'));
}

export function percent(value: number): number {
    return Math.round(value * 100);
}

export function scoreKind(
    score: number,
    target: number,
    close: number,
): ScoreKind {
    if (score >= target) {
        return 'ok';
    }

    return score >= close ? 'warn' : 'bad';
}

export function scoreColor(
    score: number,
    target: number,
    close: number,
): string {
    return COLORS[scoreKind(score, target, close)];
}

export interface VerdictInfo {
    label: string;
    /** Class of the card and of the chip. */
    kind: string;
    color: string;
    text: string;
}

/** Label, colours and advice of a verdict, in the interface language. */
export function verdictInfo(verdict: Readiness): VerdictInfo {
    switch (verdict) {
        case 'ready':
            return {
                label: trans('✅ Ready'),
                kind: 'ok',
                color: COLORS.ok,
                text: trans(
                    'Your mock test results are above the safe target. You can sign up for the exam.',
                ),
            };
        case 'close':
            return {
                label: trans('🟡 Almost ready'),
                kind: 'warn',
                color: COLORS.warn,
                text: trans(
                    "You're in the pass zone, but with no margin. Work on your weaker papers and do all 4 papers.",
                ),
            };
        case 'notyet':
            return {
                label: trans('🔴 Not yet'),
                kind: 'bad',
                color: COLORS.bad,
                text: trans(
                    'For now your scores are below the pass mark. Keep going with the course and come back to the mock tests.',
                ),
            };
        default:
            return {
                label: trans('❔ Unknown'),
                kind: 'lock',
                color: COLORS.none,
                text: trans(
                    'Do at least the Reading and Listening papers of a mock test to find out where you stand.',
                ),
            };
    }
}
