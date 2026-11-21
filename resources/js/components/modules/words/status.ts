// How a word's status is shown: the label (a translation key) and the kind of its chip.
import type { ReviewStatus } from '@/types';

export const STATUS: Record<ReviewStatus, { label: string; kind: string }> = {
    learned: { label: '✅ Learned', kind: 'ok' },
    learning: { label: '🌱 Learning', kind: 'now' },
    new: { label: '🆕 To learn', kind: 'lock' },
};
