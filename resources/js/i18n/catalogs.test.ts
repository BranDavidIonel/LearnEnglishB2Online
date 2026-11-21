// The translation keys kept in TypeScript data ($t(info.name)) are out of reach of
// tests/Unit/TranslationsTest.php, which reads the $t('…') calls of the code: they are checked here.
import { describe, expect, it } from 'vite-plus/test';
import { STEP_INFO } from '@/components/modules/units/steps';
import { PATTERNS, usage } from '@/components/modules/verbs/verbs';
import type { VerbItem } from '@/components/modules/verbs/verbs';
import { RELATIONS } from '@/components/modules/words/lookup';
import { STATUS } from '@/components/modules/words/status';
import { LEVELS } from '@/utils/levels';
import { THEMES } from '@/utils/themes';
import { trans } from './index';

const verb = (v1: string): VerbItem =>
    ({
        v1,
        v2: 'x',
        v3: 'x',
        alts2: ['x'],
        alts3: ['x'],
    }) as VerbItem;

const keys: string[] = [
    ...Object.values(STEP_INFO).flatMap((step) => [step.name, step.desc]),
    ...LEVELS.map((level) => level.title),
    ...THEMES.map((theme) => theme.name),
    ...Object.values(STATUS).map((status) => status.label),
    ...Object.values(RELATIONS),
    ...Object.values(PATTERNS).map((pattern) => pattern.name),
    ...[verb('be'), verb('go')].flatMap((item) =>
        usage(item).map((row) => row.when),
    ),
];

describe('translation keys kept in data', () => {
    it.each(keys)('has a Romanian text for "%s"', (key) => {
        expect(trans(key, {}, 'ro')).not.toBe(key);
    });
});

describe('trans', () => {
    it('never shows the context of a key in English', () => {
        expect(trans('Videos [shortcut]', {}, 'en')).toBe('Videos');
        expect(trans('Videos [shortcut]', {}, 'ro')).toBe('Videouri');
        expect(trans('Videos', {}, 'ro')).toBe('Video');
    });
});
