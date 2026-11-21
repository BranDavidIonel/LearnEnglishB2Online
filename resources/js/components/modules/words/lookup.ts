// What the word window says about a looked-up word (WordLookup.vue).
import type { WordLookup } from '@/types';

export type LookupRelation = NonNullable<WordLookup['relation']>;

/** "alerts — plural of alert": how a tapped form comes from its headword (translation keys). */
export const RELATIONS: Record<LookupRelation, string> = {
    plural: 'plural of',
    third: 'he / she / it form of',
    past: 'past of',
    participle: 'past participle of',
    ing: '-ing form of',
    comparative: 'comparative of',
    superlative: 'superlative of',
    possessive: 'possessive of',
    form: 'a form of',
};

/** The unit number of a unit id: "b1-u04" → 4. */
export function unitOrder(unit: string): number {
    return Number(unit.slice(unit.lastIndexOf('u') + 1));
}
