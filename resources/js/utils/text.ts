// Text helpers of the lessons: safe markdown for v-html, and the checking of typed and spoken answers.

const ENTITIES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/** Escapes text for HTML. */
export function esc(text: unknown): string {
    return String(text).replace(/[&<>"']/g, (c) => ENTITIES[c]);
}

/** Tiny markdown: **bold**, *italic*, newlines. Returns safe HTML (the text is escaped first). */
export function md(text: string | null | undefined): string {
    return esc(text || '')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[\s(])\*([^*\n]+?)\*(?=[\s).,;:!?]|$)/g, '$1<em>$2</em>')
        .replace(/\n/g, '<br>');
}

/** Like md(), and the gap "___" of a question becomes a highlighted blank. */
export function gap(text: string | null | undefined): string {
    return md(text).replace(/_{3,}/g, '<span class="gap"></span>');
}

// Contractions are expanded before comparing, so "I'm" and "I am" are the same answer.
const CONTRACTIONS: [RegExp, string][] = [
    [/\bcan't\b/g, 'can not'],
    [/\bcannot\b/g, 'can not'],
    [/\bwon't\b/g, 'will not'],
    [/\bshan't\b/g, 'shall not'],
    [/n't\b/g, ' not'],
    [/\bi'm\b/g, 'i am'],
    [/'re\b/g, ' are'],
    [/'ve\b/g, ' have'],
    [/'ll\b/g, ' will'],
    [/\b(he|she|it|that|there|what|where|who|how|here)'s\b/g, '$1 is'],
    [/\blet's\b/g, 'let us'],
];

/**
 * Normalises an answer for comparing: lower case, typographic quotes made plain, punctuation and
 * dashes removed, spaces collapsed and (unless `expand` is false) contractions written in full.
 */
export function norm(text: unknown, expand = true): string {
    let t = String(text)
        .toLowerCase()
        .replace(/[’‘`´]/g, "'")
        .replace(/[“”"]/g, '');
    t = t
        .replace(/[.,!?;:()…]/g, ' ')
        .replace(/\s+-\s+|—|–/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (expand) {
        for (const [pattern, full] of CONTRACTIONS) {
            t = t.replace(pattern, full);
        }

        t = t.replace(/\s+/g, ' ').trim();
    }

    return t;
}

/** Levenshtein distance: the number of single-letter edits from `a` to `b`. */
export function lev(a: string, b: string): number {
    if (a === b) {
        return 0;
    }

    const m = a.length;
    const n = b.length;

    if (!m) {
        return n;
    }

    if (!n) {
        return m;
    }

    let prev = Array.from({ length: n + 1 }, (_, i) => i);

    for (let i = 1; i <= m; i++) {
        const cur = [i];

        for (let j = 1; j <= n; j++) {
            cur[j] = Math.min(
                prev[j] + 1,
                cur[j - 1] + 1,
                prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
            );
        }

        prev = cur;
    }

    return prev[n];
}

export interface CheckResult {
    ok: boolean;
    /** True when the answer was accepted with one small spelling mistake. */
    typo?: boolean;
}

/**
 * Compares typed text with the accepted answers. With `typo` one small mistake (one letter) is
 * tolerated in answers of 10 characters or more.
 */
export function checkText(
    input: string,
    answers: string[],
    typo = false,
): CheckResult {
    const a = norm(input);

    if (!a) {
        return { ok: false };
    }

    if (answers.some((answer) => norm(answer) === a)) {
        return { ok: true };
    }

    if (typo) {
        for (const answer of answers) {
            const b = norm(answer);

            if (b.length >= 10 && lev(a, b) <= 1) {
                return { ok: true, typo: true };
            }
        }
    }

    return { ok: false };
}

// ---------- scoring of spoken answers (speech recognition returns several alternatives) ----------

/** Share of the target's words found in the best alternative heard (0..1). */
export function speechScore(target: string, heardList: string[]): number {
    const targetWords = norm(target).split(' ');
    let best = 0;

    for (const heard of heardList) {
        const heardWords = norm(heard).split(' ');
        const hit =
            targetWords.filter((w) => heardWords.includes(w)).length /
            targetWords.length;
        best = Math.max(best, hit);
    }

    return best;
}

export interface SpokenWord {
    /** The word as written in the target (with its punctuation). */
    w: string;
    hit: boolean;
}

export interface SpeechDetail {
    /** Share of the target's words that were said (0..1). */
    score: number;
    /** The best alternative heard. */
    heard: string;
    words: SpokenWord[];
}

/** Word-by-word comparison, to show which words of the target were missed. */
export function speechDetail(
    target: string,
    heardList: string[],
): SpeechDetail {
    const shown = String(target).split(/\s+/).filter(Boolean);
    let best: SpeechDetail = {
        score: -1,
        heard: heardList[0] || '',
        words: shown.map((w) => ({ w, hit: false })),
    };

    for (const heard of heardList) {
        const heardWords = norm(heard).split(' ');
        const words = shown.map((w) => {
            const parts = norm(w).split(' ').filter(Boolean);

            // A token that is only punctuation ("–") counts as said.
            return {
                w,
                hit: parts.length
                    ? parts.every((p) => heardWords.includes(p))
                    : true,
            };
        });
        const counted = words.filter((x) => norm(x.w));
        const score = counted.length
            ? counted.filter((x) => x.hit).length / counted.length
            : 0;

        if (score > best.score) {
            best = { score, heard, words };
        }
    }

    return best;
}
