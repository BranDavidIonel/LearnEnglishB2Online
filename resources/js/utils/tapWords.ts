// Tappable English: every word of a text becomes a <span class="tap-w">, and the layout opens the
// word window when one is tapped (docs/frontend-ui.md, "Tappable English").
//
// A word is a maximal run of letters (any alphabet: "Iași", "Graça") in which single apostrophes
// (' or ’) and single hyphens may stand between two letters ("don't", "start-up"). Dots never
// join words ("p.m." is "p" and "m"). A run that touches a digit or the "___" of a gap is not a
// word ("12th", "B2"). The server applies the same rule when it checks that every lesson word
// can be looked up.

/** The marker class of a tappable word. */
export const TAP_WORD = 'tap-w';

/** The class of an element whose English is tappable (its text is where the sentence is found). */
export const TAP_TEXT = 'tap';

// In escaped HTML the straight apostrophe is "&#39;" (see esc()).
const JOINER = String.raw`(?:'|’|&#39;|-)`;

// An entity is copied as it is ("&amp;" holds no word); otherwise a run of letters and joiners.
const TOKEN = new RegExp(
    String.raw`(&(?:#\d+|#x[\da-fA-F]+|[A-Za-z]+);)|\p{L}+(?:${JOINER}\p{L}+)*`,
    'gu',
);

// A run touching one of these is not a word: digits ("12th") and the gap "___".
const NOT_BESIDE = /[\p{N}_]/u;

// Opening and closing tags; the content of a word already marked, or of a gap, is left alone.
const TAG = /(<[^>]*>)/;
const SKIP_OPEN = /^<span class="(?:tap-w|gap)"/;
const GAP_OPEN = /^<span class="gap"/;

/**
 * Wraps the English words of a text in <span class="tap-w">. `edges` are the characters just
 * before and after the text, across inline tags ("12<em>th</em>" is still not a word).
 */
function wrapText(text: string, edges: [string, string]): string {
    return text.replace(
        TOKEN,
        (match: string, entity: string | undefined, at: number) => {
            if (entity) {
                return match;
            }

            const end = at + match.length;
            const before = at > 0 ? text.charAt(at - 1) : edges[0];
            const after = end < text.length ? text.charAt(end) : edges[1];

            if (NOT_BESIDE.test(before) || NOT_BESIDE.test(after)) {
                return match;
            }

            return `<span class="${TAP_WORD}">${match}</span>`;
        },
    );
}

/**
 * The character next to the part at `from`, going in `step` direction across inline tags: a
 * gap counts as "_", a line break as a new line, the edge of the HTML as nothing.
 */
function neighbour(parts: string[], from: number, step: 1 | -1): string {
    for (let i = from + step; i >= 0 && i < parts.length; i += step) {
        const part = parts[i];
        // Going back, a gap is its closing tag right after its (empty) opening tag.
        const gap =
            step > 0
                ? GAP_OPEN.test(part)
                : part === '</span>' &&
                  parts[i - 1] === '' &&
                  GAP_OPEN.test(parts[i - 2] ?? '');

        if (gap) {
            return '_';
        }

        if (part.startsWith('<br')) {
            return '\n';
        }

        if (part && !part.startsWith('<')) {
            return step > 0 ? part.charAt(0) : part.charAt(part.length - 1);
        }
    }

    return '';
}

/**
 * Makes every English word of safe HTML (the output of esc() or md()) tappable. Tags and
 * entities stay as they are; words already marked are not marked twice.
 */
export function tapWords(html: string): string {
    const parts = html.split(TAG);
    let skipping = false;

    return parts
        .map((part, i) => {
            if (part.startsWith('<')) {
                if (SKIP_OPEN.test(part)) {
                    skipping = true;
                } else if (skipping && part === '</span>') {
                    skipping = false;
                }

                return part;
            }

            return skipping
                ? part
                : wrapText(part, [
                      neighbour(parts, i, -1),
                      neighbour(parts, i, 1),
                  ]);
        })
        .join('');
}

/** The word to look up, as written: case kept, a curly apostrophe made straight. */
export function lookupText(word: string): string {
    return word.trim().replace(/’/g, "'");
}

// The end of a sentence: . ! ? … (with closing quotes or brackets) before a space, or a new line.
const SENTENCE_END = /[.!?…]+["”’')\]]*\s+|\n+/gu;

/** The sentence of `text` that holds the character at `at`, spaces tidied. */
export function sentenceAt(text: string, at: number): string {
    let from = 0;
    let to = text.length;

    for (const end of text.matchAll(SENTENCE_END)) {
        const stop = (end.index ?? 0) + end[0].length;

        if (stop <= at) {
            from = stop;
        } else {
            to = (end.index ?? 0) + end[0].trimEnd().length;
            break;
        }
    }

    return text.slice(from, to).replace(/\s+/g, ' ').trim();
}

/** What a tap on a word gives the word window. */
export interface TapTarget {
    word: string;
    sentence: string;
}

/**
 * The word and its sentence for a tapped word element. The sentence is looked for in the
 * closest element with the class "tap" (TapText, or a container marked by hand), else in the
 * word's parent: a line break (<br>) ends a sentence too.
 */
export function tapTarget(element: Element): TapTarget | null {
    const word = lookupText(element.textContent ?? '');

    if (!word) {
        return null;
    }

    const container =
        element.closest(`.${TAP_TEXT}`) ?? element.parentElement ?? element;
    let text = '';
    let at = 0;

    const walk = (node: Node): void => {
        if (node === element) {
            at = text.length;
        }

        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent ?? '';
        } else if (node.nodeName === 'BR') {
            text += '\n';
        } else {
            node.childNodes.forEach(walk);
        }
    };

    walk(container);

    return { word, sentence: sentenceAt(text, at) || word };
}
