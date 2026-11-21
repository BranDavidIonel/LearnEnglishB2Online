// Marking of a Reading or Listening paper, as the original paper runner did it.
import type {
    CambridgePart,
    CambridgeQuestion,
    CambridgeResultInput,
} from '@/types';
import { checkText } from '@/utils/text';
import type { PaperAnswer } from './types';

/** Answers of a paper by "<part>:<question>" (both from 0). */
export type PaperAnswers = Record<string, PaperAnswer>;

export function answerKey(part: number, question: number): string {
    return `${part}:${question}`;
}

/** A chosen option must be the right one; a typed answer one of the accepted answers (no typo allowed). */
export function isRight(
    question: CambridgeQuestion,
    answer: PaperAnswer | undefined,
): boolean {
    if (question.type === 'mc') {
        return answer === question.answer;
    }

    return (
        answer !== undefined && checkText(String(answer), question.answers).ok
    );
}

/** The question without the number the content writes in front of it ("**(27)**", "3"). */
export function questionText(question: CambridgeQuestion): string {
    return question.q.replace(/^\s*\*{0,2}\(?\d+\)?\*{0,2}\s*/, '');
}

/** Options that already carry their own letter ("A", "B The rest is…") are shown as written. */
export function isLettered(options: string[]): boolean {
    return options.every((option, i) =>
        new RegExp(`^${String.fromCharCode(65 + i)}([\\s.):]|$)`).test(
            option.trim(),
        ),
    );
}

/** Right answers of one part. */
export function rightInPart(
    part: CambridgePart,
    index: number,
    answers: PaperAnswers,
): number {
    return part.questions.filter((question, qi) =>
        isRight(question, answers[answerKey(index, qi)]),
    ).length;
}

/** The result that is stored: {score, correct, total, parts: right answers per part}. */
export function markPaper(
    parts: CambridgePart[],
    answers: PaperAnswers,
): Required<CambridgeResultInput> {
    const perPart = parts.map((part, pi) => rightInPart(part, pi, answers));
    const correct = perPart.reduce((sum, n) => sum + n, 0);
    const total = parts.reduce((sum, part) => sum + part.questions.length, 0);

    return { score: correct / total, correct, total, parts: perPart };
}
