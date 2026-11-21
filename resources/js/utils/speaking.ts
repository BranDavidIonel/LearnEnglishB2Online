// The rules of the speaking practice that run in the browser: which level the hub shows on this
// device, how a spoken answer is judged, the letter hints of the voice sprint and the
// sound-alike word pairs.

import type { Dialogue, SpeakingScenario } from '@/types';
import { speak } from '@/utils/speech';
import type { SpokenWord } from '@/utils/text';
import { norm, speechDetail } from '@/utils/text';

// ---------- the level shown (a preference of this device) ----------

const LEVEL_KEY = 'el-speak-level';

/** The level last chosen in the level picker on this device, or null. */
export function storedLevel(): string | null {
    try {
        return localStorage.getItem(LEVEL_KEY);
    } catch {
        return null;
    }
}

/** Remembers the level chosen in the level picker on this device. */
export function rememberLevel(id: string): void {
    try {
        localStorage.setItem(LEVEL_KEY, id);
    } catch {
        // Private mode: the choice lasts until the page is left.
    }
}

/** The level to show: the one chosen on this device while it is open, else the fallback. */
export function pickLevel(
    stored: string | null,
    open: readonly string[],
    fallback: string,
): string {
    return stored && open.includes(stored) ? stored : fallback;
}

// ---------- judging what was said ----------

/** True when the phrase was said as whole words somewhere in the text. */
export function hasPhrase(text: string, phrase: string): boolean {
    return ` ${norm(text)} `.includes(` ${norm(phrase)} `);
}

/** Hyphens and spacing do not matter: easy-going = easygoing = easy going. */
const flat = (text: string): string =>
    norm(text).replace(/-/g, ' ').replace(/\s+/g, ' ').trim();

/** True when a word or short phrase was said. */
export function saidWord(text: string, target: string): boolean {
    const t = flat(target);
    const x = flat(text);

    if (!t || !x) {
        return false;
    }

    if (` ${x} `.includes(` ${t} `)) {
        return true;
    }

    return t.includes(' ') && x.split(' ').includes(t.replace(/ /g, ''));
}

/** The number of words in a spoken text. */
export function wordCount(text: string): number {
    const trimmed = text.trim();

    return trimmed ? trimmed.split(/\s+/).length : 0;
}

/** A word of the voice sprint. */
export interface SprintWord {
    en: string;
    ro: string;
    emoji: string;
}

/** First letter of each word plus blanks: "easy-going" → "e _ _ _ - g _ _ _ _". */
export function letterHint(en: string): string {
    const letter = /[a-z]/i;

    return en
        .split('')
        .map((ch, i, all) => {
            if (letter.test(ch)) {
                return i === 0 || !letter.test(all[i - 1]) ? ch : '_';
            }

            return ch === ' ' ? '  ' : ch;
        })
        .join(' ');
}

// ---------- conversations and role-plays ----------

/** A line the app says. */
export interface PartnerTurn {
    who: 'partner';
    en: string;
    ro?: string;
}

/** A line the learner says. */
export interface LearnerTurn {
    who: 'me';
    /** What to say: an idea in Romanian, or the translation of the line. */
    hint: string;
    /** Model answers, best first. */
    answers: string[];
    /** Words every model answer contains: saying them all is enough. */
    keywords: string[];
    /** The line is shown in English (role-play, not in hard mode). */
    show?: boolean;
}

export type ConversationTurn = PartnerTurn | LearnerTurn;

/** A conversation as the conversation runner plays it. */
export interface Conversation {
    /** The id its result is stored under: a scenario id or "role-<unit>". */
    id: string;
    title: string;
    partner: string;
    me: string;
    intro?: string;
    turns: ConversationTurn[];
}

/** One of the conversations of the speaking content: partner, learner, partner … and a closing line. */
export function scenarioConversation(
    scenario: SpeakingScenario,
    me: string,
): Conversation {
    const turns: ConversationTurn[] = scenario.turns.flatMap(
        (t): ConversationTurn[] => [
            { who: 'partner', en: t.say, ro: t.sayRo },
            {
                who: 'me',
                hint: t.hintRo,
                answers: t.answers,
                keywords: t.keywords,
            },
        ],
    );

    if (scenario.closing) {
        turns.push({ who: 'partner', en: scenario.closing });
    }

    return {
        id: scenario.id,
        title: `${scenario.emoji} ${scenario.title}`,
        partner: scenario.partner,
        me,
        intro: scenario.youRo,
        turns,
    };
}

/** The dialogue of a unit, with the learner playing one of its characters. */
export function roleConversation(
    unitId: string,
    dialogue: Dialogue,
    me: string,
    hard: boolean,
    intro: string,
): Conversation {
    const speakers = dialogueSpeakers(dialogue.lines);

    return {
        id: `role-${unitId}`,
        title: `🎭 ${dialogue.titleRo}`,
        partner: speakers.find((s) => s !== me) || '…',
        me,
        intro,
        turns: dialogue.lines.map((l): ConversationTurn =>
            l.speaker === me
                ? {
                      who: 'me',
                      hint: l.ro,
                      answers: [l.en],
                      keywords: [],
                      show: !hard,
                  }
                : { who: 'partner', en: l.en, ro: l.ro },
        ),
    };
}

/** The characters of a dialogue, in the order they first speak. */
export function dialogueSpeakers(lines: { speaker: string }[]): string[] {
    return [...new Set(lines.map((l) => l.speaker))];
}

export interface TurnVerdict {
    pass: boolean;
    /** What to show as said: the alternative with every keyword, else the closest one. */
    heard: string;
    /** The closest model answer, word by word. */
    words: SpokenWord[];
    answer: string;
}

/**
 * Judges a learner's line: close enough to one of the model answers (70% of its words when the
 * line is shown, 60% when it is not), or every keyword said in one of the alternatives.
 */
export function judgeTurn(turn: LearnerTurn, heard: string[]): TurnVerdict {
    let best = { score: -1, heard: '', words: [] as SpokenWord[], answer: '' };

    for (const answer of turn.answers) {
        const detail = speechDetail(answer, heard);

        if (detail.score > best.score) {
            best = { ...detail, answer };
        }
    }

    const withKeywords = turn.keywords.length
        ? heard.find((x) => turn.keywords.every((k) => hasPhrase(x, k)))
        : undefined;

    return {
        pass: best.score >= (turn.show ? 0.7 : 0.6) || !!withKeywords,
        heard: withKeywords || best.heard,
        words: best.words,
        answer: best.answer,
    };
}

/** The score of a conversation: the average of the learner's lines (0 without any). */
export function averageScore(scores: number[]): number {
    return scores.length
        ? scores.reduce((a, b) => a + b, 0) / scores.length
        : 0;
}

// ---------- repeat after me / say it in English ----------

/** Every word of the sentence as said: used when the learner checks themself. */
export function allSaid(sentence: string): SpokenWord[] {
    return sentence.split(/\s+/).map((w) => ({ w, hit: true }));
}

/** Pass mark of a sentence: 80% of its words when repeating it, 70% when saying it from Romanian. */
export function sentencePassMark(hidden: boolean): number {
    return hidden ? 0.7 : 0.8;
}

// ---------- sound-alike words ----------

/** Pairs of words that sound alike and are hard to tell apart for Romanian speakers. */
export const SOUND_ALIKE_PAIRS: readonly [string, string][] = [
    ['ship', 'sheep'],
    ['live', 'leave'],
    ['sit', 'seat'],
    ['fill', 'feel'],
    ['chip', 'cheap'],
    ['rich', 'reach'],
    ['bad', 'bed'],
    ['man', 'men'],
    ['pan', 'pen'],
    ['sad', 'said'],
    ['cut', 'cat'],
    ['cup', 'cap'],
    ['fun', 'fan'],
    ['not', 'note'],
    ['cot', 'coat'],
    ['work', 'walk'],
    ['word', 'ward'],
    ['bird', 'board'],
    ['think', 'sink'],
    ['thin', 'tin'],
    ['three', 'tree'],
    ['thought', 'taught'],
    ['mouth', 'mouse'],
    ['vest', 'west'],
    ['vine', 'wine'],
    ['bag', 'back'],
    ['bed', 'bet'],
    ['price', 'prize'],
    ['hair', 'air'],
    ['heat', 'eat'],
];

// ---------- saving ----------

/**
 * The saves of a round: sentences said count as soon as they are said, the result at the end.
 * Requests go out one after another; when one fails (no network) it and everything after it
 * wait, and flush() sends them again — never twice what already reached the server.
 */
export interface SaveQueue {
    /** Sends the request now, or keeps it for flush() while an earlier one is failing. */
    add(request: () => Promise<unknown>): void;
    /** Sends whatever is still waiting; false when a request failed again. */
    flush(): Promise<boolean>;
}

export function saveQueue(): SaveQueue {
    const waiting: (() => Promise<unknown>)[] = [];
    let failed = false;
    let chain: Promise<boolean> = Promise.resolve(true);

    const drain = async (): Promise<boolean> => {
        while (waiting.length) {
            try {
                await waiting[0]();
            } catch {
                failed = true;

                return false;
            }

            waiting.shift();
        }

        return true;
    };

    return {
        add(request) {
            waiting.push(request);

            // After a failure the learner is told once, at the end of the round, not for every sentence.
            if (!failed) {
                chain = chain.then(drain);
            }
        },
        flush() {
            failed = false;
            chain = chain.then(drain);

            return chain;
        },
    };
}

// ---------- speech ----------

/** Says a text and resolves when it has been said (or after a safety timeout, if speech never ends). */
export function sayAsync(text: string): Promise<void> {
    return new Promise((resolve) => {
        if (typeof window === 'undefined' || !window.speechSynthesis) {
            resolve();

            return;
        }

        let done = false;
        const finish = (): void => {
            if (!done) {
                done = true;
                resolve();
            }
        };

        speak(text, { onend: finish });
        setTimeout(finish, 2000 + text.length * 95);
    });
}
