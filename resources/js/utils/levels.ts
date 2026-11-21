// The four levels as the interface shows them: the same ids and colours as App\Enums\Level.
import type { LevelId } from '@/types';

export interface LevelInfo {
    id: LevelId;
    /** "A1": the name on chips, pills and rings. */
    name: string;
    /** English title, a translation key: $t(level.title). */
    title: string;
    color: string;
}

export const LEVEL_INFO: Record<LevelId, LevelInfo> = {
    a1: { id: 'a1', name: 'A1', title: 'Beginner', color: '#22c55e' },
    a2: { id: 'a2', name: 'A2', title: 'Elementary', color: '#38bdf8' },
    b1: { id: 'b1', name: 'B1', title: 'Intermediate', color: '#a78bfa' },
    b2: { id: 'b2', name: 'B2', title: 'Upper intermediate', color: '#fb923c' },
};

/** The levels in course order. */
export const LEVELS: LevelInfo[] = Object.values(LEVEL_INFO);

/** The level with this id, or null for an unknown id. */
export function levelInfo(id: string): LevelInfo | null {
    return LEVELS.find((level) => level.id === id) ?? null;
}
