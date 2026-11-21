// The six parts of a unit as the interface shows them, in the order the learner does them.
// `name` and `desc` are translation keys: $t(info.name), $t(info.desc, { pct }).
import type { UnitStep } from '@/types';

export interface StepInfo {
    icon: string;
    name: string;
    desc: string;
}

export const UNIT_STEPS: UnitStep[] = [
    'vocab',
    'grammar',
    'dialogue',
    'practice',
    'reading',
    'test',
];

export const STEP_INFO: Record<UnitStep, StepInfo> = {
    vocab: {
        icon: '🗂️',
        name: 'New words',
        desc: 'See, hear and remember the words of the unit',
    },
    grammar: {
        icon: '📐',
        name: 'Grammar',
        desc: 'The rule explained simply, with examples',
    },
    dialogue: {
        icon: '💬',
        name: 'Dialogue',
        desc: 'Listen to a real conversation and repeat it',
    },
    practice: {
        icon: '✏️',
        name: 'Exercises',
        desc: 'Practise with instant feedback',
    },
    reading: {
        icon: '📖',
        name: 'Reading',
        desc: 'A short text and comprehension questions',
    },
    test: {
        icon: '🎯',
        name: 'Unit test',
        desc: 'At least :pct% to move on',
    },
};

/** The part that comes after this one (null after the test). */
export function nextStep(step: UnitStep): UnitStep | null {
    return UNIT_STEPS[UNIT_STEPS.indexOf(step) + 1] ?? null;
}
