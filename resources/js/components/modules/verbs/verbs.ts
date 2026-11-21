// What the irregular verbs page shares between its pieces: the verb as the page gets it, the
// numbers of a session, the labels of the patterns, and small text helpers.
import type { VerbWithState } from '@/types';
import { speak } from '@/utils/speech';
import { md } from '@/utils/text';

/** Shape of the three forms: abc go – went – gone, abb buy – bought – bought, aba come – came – come, aaa cut – cut – cut. */
export type VerbPattern = 'abc' | 'abb' | 'aba' | 'aaa';

/** A verb of the page (App\Http\Resources\Verbs\VerbItemResource). */
export interface VerbItem extends VerbWithState {
    pattern: VerbPattern;
    /** The alternatives of the past and of the participle: "learnt / learned" → ["learnt", "learned"]. */
    alts2: string[];
    alts3: string[];
    /** Every answer accepted when the form is typed ("was", "were", "was / were" …). */
    answers2: string[];
    answers3: string[];
}

/** One verb of a drill, first attempt only. */
export interface DrillResult {
    verb: VerbItem;
    ok: boolean;
}

/** New verbs per "learn" or "test me" session. */
export const BATCH = 10;
/** At most this many due verbs per review. */
export const REVIEW_MAX = 20;
/** Verbs in a free practice, offered once this many verbs are learned or being learned. */
export const FREE_PRACTICE = 10;
export const FREE_PRACTICE_MIN = 4;

/** Colour of the "learned" bar (the B1 colour, as in the original page). */
export const VERBS_COLOR = '#a78bfa';

/** Name (translation key) and example of each pattern, in the order of the pattern filter. */
export const PATTERNS: Record<VerbPattern, { name: string; example: string }> =
    {
        abc: { name: 'all different', example: 'go – went – gone' },
        abb: { name: 'past = participle', example: 'buy – bought – bought' },
        aba: { name: 'first = third', example: 'come – came – come' },
        aaa: { name: 'all three the same', example: 'cut – cut – cut' },
    };

/** "go – went – gone" */
export function forms(verb: VerbItem): string {
    return `${verb.v1} – ${verb.v2} – ${verb.v3}`;
}

/** What is read aloud for the three forms: "get. got. got or gotten." */
export function spokenForms(verb: VerbItem): string {
    return `${verb.v1}. ${verb.alts2.join(' or ')}. ${verb.alts3.join(' or ')}.`;
}

export function sayForms(verb: VerbItem): void {
    speak(spokenForms(verb));
}

const escapeRegExp = (text: string): string =>
    text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** An example sentence as safe HTML, with the verb form in bold (the first match of each alternative). */
export function mark(text: string, alts: string[]): string {
    let out = text;

    for (const alt of alts) {
        out = out.replace(
            new RegExp(`\\b(${escapeRegExp(alt)})\\b`, 'i'),
            '**$1**',
        );
    }

    return md(out);
}

/** A row of "how this verb's forms are used": the form, when (translation key), an example. */
export interface UsageRow {
    form: string;
    when: string;
    example: string;
}

/** How the three forms of this verb are used, built from its forms (go → I go · will go · did you go? …). */
export function usage(verb: VerbItem): UsageRow[] {
    const base = verb.v1;
    const past = verb.alts2[0];
    const participle = verb.alts3[0];

    // "be" has its own present and two past forms.
    if (base === 'be') {
        return [
            {
                form: 'V1',
                when: 'present / future',
                example: 'I am · you are · she is · I will be',
            },
            {
                form: 'V2',
                when: 'past simple',
                example: 'I was · we were · Were you…?',
            },
            {
                form: 'V3',
                when: 'after have / had',
                example: 'I have been · it had been',
            },
        ];
    }

    return [
        {
            form: 'V1',
            when: 'present, future, after did / can / to',
            example: `I ${base} · I will ${base} · Did you ${base}? · I didn't ${base}`,
        },
        {
            form: 'V2',
            when: 'past simple (affirmative only)',
            example: `I ${past} (yesterday)`,
        },
        {
            form: 'V3',
            when: 'after have / had / be',
            example: `I have ${participle} · I had ${participle}`,
        },
    ];
}

/** The search of the list: a form starting with the text, or a Romanian meaning containing it (text in lower case). */
export function matchesSearch(verb: VerbItem, query: string): boolean {
    if (!query) {
        return true;
    }

    const all = [verb.v1, ...verb.alts2, ...verb.alts3].map((form) =>
        form.toLowerCase(),
    );

    return (
        all.some((form) => form.startsWith(query)) ||
        (verb.ro || '').toLowerCase().includes(query)
    );
}
