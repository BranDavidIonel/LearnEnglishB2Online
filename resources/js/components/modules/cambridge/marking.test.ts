import { describe, expect, it } from 'vite-plus/test';
import fceTest from '../../../../content/cambridge/fce/test1.json';
import petTest from '../../../../content/cambridge/pet/test2.json';
import type {
    CambridgePart,
    CambridgeTest,
    FillExercise,
    McExercise,
} from '@/types';
import {
    answerKey,
    isLettered,
    isRight,
    markPaper,
    questionText,
} from './marking';
import type { PaperAnswers } from './marking';

const mc: McExercise = {
    type: 'mc',
    q: '**(1)** ___',
    options: ['make', 'do', 'take', 'have'],
    answer: 2,
};

const fill: FillExercise = {
    type: 'fill',
    q: 'I last saw him years ago. (FOR)\nI ___ years.',
    answers: ["haven't seen him for", 'have not seen him for'],
};

/** The right answer of every question of a paper. */
function rightAnswers(parts: CambridgePart[]): PaperAnswers {
    const answers: PaperAnswers = {};

    parts.forEach((part, pi) =>
        part.questions.forEach((question, qi) => {
            answers[answerKey(pi, qi)] =
                question.type === 'mc' ? question.answer : question.answers[0];
        }),
    );

    return answers;
}

describe('isRight', () => {
    it('wants the right option of a multiple choice question', () => {
        expect(isRight(mc, 2)).toBe(true);
        expect(isRight(mc, 1)).toBe(false);
        expect(isRight(mc, undefined)).toBe(false);
    });

    it('accepts every listed answer, whatever the case, punctuation or contraction', () => {
        expect(isRight(fill, 'have not seen him for')).toBe(true);
        expect(isRight(fill, "Haven't seen him for.")).toBe(true);
        expect(isRight(fill, 'haven’t seen him for')).toBe(true);
    });

    it('forgives no typing mistake and no empty answer', () => {
        expect(isRight(fill, 'havent seen hi for')).toBe(false);
        expect(isRight(fill, '')).toBe(false);
        expect(isRight(fill, undefined)).toBe(false);
    });
});

describe('questionText', () => {
    it('drops the number the content writes in front of a question', () => {
        expect(questionText(mc)).toBe('___');
        expect(questionText({ ...mc, q: '(16) ___' })).toBe('___');
        expect(questionText({ ...mc, q: '3 What does Anna want?' })).toBe(
            'What does Anna want?',
        );
        expect(questionText({ ...mc, q: 'What does Anna want?' })).toBe(
            'What does Anna want?',
        );
    });
});

describe('isLettered', () => {
    it('sees options that carry their own letter', () => {
        expect(isLettered(['A', 'B', 'C'])).toBe(true);
        expect(isLettered(['A The first', 'B. The second'])).toBe(true);
        expect(isLettered(['Anna', 'Bob'])).toBe(false);
        expect(isLettered(['B', 'A'])).toBe(false);
    });
});

describe('markPaper', () => {
    it('gives full marks for the right answers of every part type', () => {
        for (const test of [fceTest, petTest] as CambridgeTest[]) {
            for (const paper of [test.reading, test.listening]) {
                const result = markPaper(
                    paper.parts,
                    rightAnswers(paper.parts),
                );

                expect(result.score).toBe(1);
                expect(result.correct).toBe(result.total);
                expect(result.parts).toEqual(
                    paper.parts.map((part) => part.questions.length),
                );
            }
        }
    });

    it('counts right answers per part and over the paper', () => {
        const parts = (fceTest as CambridgeTest).reading.parts;
        const answers = rightAnswers(parts);
        delete answers[answerKey(0, 0)];
        answers[answerKey(1, 0)] = 'zzz';

        const result = markPaper(parts, answers);

        expect(result.total).toBe(52);
        expect(result.correct).toBe(50);
        expect(result.score).toBe(50 / 52);
        expect(result.parts.slice(0, 3)).toEqual([7, 7, 8]);
    });
});
