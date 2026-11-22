// The progress API (routes/web/api.php): one function per thing worth remembering, named after
// the function of the original store. Every answer carries the fresh learner summary, which
// `send()` puts into the shared learner state by itself (top bar, menu badges). Requests go one
// after another, in the order they were made. A refused request rejects with an HttpError
// (422: `errors` by field). See docs/backend.md for what each one does on the server.
import * as cambridge from '@/routes/api/cambridge';
import * as exams from '@/routes/api/exams';
import * as flags from '@/routes/api/flags';
import * as games from '@/routes/api/games';
import * as goal from '@/routes/api/goal';
import * as mistakes from '@/routes/api/mistakes';
import * as placement from '@/routes/api/placement';
import * as progress from '@/routes/api/progress';
import * as settings from '@/routes/api/settings';
import * as speaking from '@/routes/api/speaking';
import * as units from '@/routes/api/units';
import * as verbs from '@/routes/api/verbs';
import * as words from '@/routes/api/words';
import * as xp from '@/routes/api/xp';
import type {
    CambridgeAnswer,
    CambridgePaperId,
    CambridgeResultInput,
    ConversationAnswer,
    ExamAnswer,
    FlagsAnswer,
    GameId,
    GameRecordAnswer,
    GoalAnswer,
    LessonStep,
    LevelId,
    MistakesAnswer,
    PlacementAnswer,
    ProgressAnswer,
    ProgressFlag,
    Settings,
    SettingsAnswer,
    SpeakingGameId,
    SpeakingRecordAnswer,
    SpokenAnswer,
    StepAnswer,
    UnitTestAnswer,
    VerbGrade,
    VerbsAnswer,
    WordGrade,
    WordLookupAnswer,
    WordsAnswer,
    XpAnswer,
} from '@/types';
import { read, send } from '@/utils/http';

/** One item or a list of them, always sent as a list. */
const list = (items: string | string[]): string[] =>
    Array.isArray(items) ? items : [items];

// ---------- the course ----------

/**
 * A lesson step done. The first time gives 10 XP (the practice step none: add the XP of its
 * answers with addXp); the vocabulary step puts the unit's words into the flashcard deck.
 */
export function completeStep(
    unit: string,
    step: LessonStep,
): Promise<StepAnswer> {
    return send<StepAnswer>(units.step(unit), { step });
}

/** A unit test (score 0..1): passed from 80%, 30 XP the first time, 10 after. */
export function recordUnitTest(
    unit: string,
    score: number,
): Promise<UnitTestAnswer> {
    return send<UnitTestAnswer>(units.test(unit), { score });
}

/**
 * A level exam from its answers. Passed from 80% (150 XP the first time, 20 after);
 * failed, the units under 60% go back to review.
 */
export function recordExam(
    level: LevelId,
    results: { unit: string; correct: boolean }[],
): Promise<ExamAnswer> {
    return send<ExamAnswer>(exams.store(level), {
        results: results.map((r) => ({ unit: r.unit, correct: r.correct })),
    });
}

/** The placement test: the score (0..1) of each level tested. 20 XP. */
export function recordPlacement(
    scores: Partial<Record<LevelId, number>>,
): Promise<PlacementAnswer> {
    return send<PlacementAnswer>(placement.store(), { scores });
}

// ---------- words (one word or many; the English text travels in the body, except for a lookup) ----------

/**
 * The word window: what a word tapped in a lesson is (meaning, headword, irregular verb, the
 * learner's state) and the course expressions of its sentence that hold it. A read: it goes
 * out at once, even while saves are on their way, and a failure is the window's to show.
 */
export function lookupWord(
    word: string,
    sentence?: string,
): Promise<WordLookupAnswer> {
    return read<WordLookupAnswer>(
        words.lookup({
            query: sentence === undefined ? { word } : { word, sentence },
        }),
    );
}

/** Flashcard grade: 0 = did not know, 1 = hard, 2 = knew it. */
export function gradeWord(
    word: string | string[],
    grade: WordGrade,
): Promise<WordsAnswer> {
    return send<WordsAnswer>(words.grade(), { words: list(word), grade });
}

/** "I already know this word": learned, back for a check in two months. */
export function markKnown(word: string | string[]): Promise<WordsAnswer> {
    return send<WordsAnswer>(words.known(), { words: list(word) });
}

/** "I knew it" in a quick sort: learned, back on the flashcards in a week. */
export function sortedKnown(word: string | string[]): Promise<WordsAnswer> {
    return send<WordsAnswer>(words.sorted(), { words: list(word) });
}

/** Into the flashcard deck as a word to learn (or to learn again), due today. */
export function learnWord(word: string | string[]): Promise<WordsAnswer> {
    return send<WordsAnswer>(words.learn(), { words: list(word) });
}

/** Out of the deck: "to learn" again. */
export function forgetWord(word: string | string[]): Promise<WordsAnswer> {
    return send<WordsAnswer>(words.forget(), { words: list(word) });
}

// ---------- irregular verbs (by their base form) ----------

/** A drill: 0 = wrong, 2 = both forms right. */
export function gradeVerb(
    verb: string | string[],
    grade: VerbGrade,
): Promise<VerbsAnswer> {
    return send<VerbsAnswer>(verbs.grade(), { verbs: list(verb), grade });
}

/** Both forms typed right before studying: learned, back in a week as a check. */
export function verbKnown(verb: string | string[]): Promise<VerbsAnswer> {
    return send<VerbsAnswer>(verbs.known(), { verbs: list(verb) });
}

/** Into the review queue from the start, due today. */
export function learnVerb(verb: string | string[]): Promise<VerbsAnswer> {
    return send<VerbsAnswer>(verbs.learn(), { verbs: list(verb) });
}

/** Out of the review queue. */
export function forgetVerb(verb: string | string[]): Promise<VerbsAnswer> {
    return send<VerbsAnswer>(verbs.forget(), { verbs: list(verb) });
}

// ---------- my mistakes ----------

/** Adds and takes off questions in one go ("<unit>:<n>", "<unit>:t<n>", "<unit>:r<n>"). */
export function updateMistakes(changes: {
    add?: string[];
    clear?: string[];
}): Promise<MistakesAnswer> {
    return send<MistakesAnswer>(mistakes.update(), changes);
}

/** Questions answered wrong (kept once; the list keeps the latest 200). */
export function addMistake(key: string | string[]): Promise<MistakesAnswer> {
    return updateMistakes({ add: list(key) });
}

/** Questions answered right: off the list. */
export function clearMistake(key: string | string[]): Promise<MistakesAnswer> {
    return updateMistakes({ clear: list(key) });
}

// ---------- XP, speaking, games ----------

/** XP of an activity whose amount the page works out (0..100): games, sort batches, drills, practice answers. */
export function addXp(amount: number): Promise<XpAnswer> {
    return send<XpAnswer>(xp.store(), { amount });
}

/** Sentences said well enough: `xpEach` is 2 (3 for an answer to an interview question). */
export function countSpoken(
    count = 1,
    xpEach: 2 | 3 = 2,
): Promise<SpokenAnswer> {
    return send<SpokenAnswer>(speaking.spoken(), { count, xp: xpEach });
}

/** A conversation ("a1-s01") or role-play ("role-a1-u01") result, 0..1: 15 XP the first time, 5 after. */
export function recordConversation(
    scenario: string,
    score: number,
): Promise<ConversationAnswer> {
    return send<ConversationAnswer>(speaking.conversation(), {
        scenario,
        score,
    });
}

/** The result of a speaking game, kept when it beats the record (`record: true`). */
export function speakRecord(
    game: SpeakingGameId,
    value: number,
): Promise<SpeakingRecordAnswer> {
    return send<SpeakingRecordAnswer>(speaking.record(game), { value });
}

/** The result of a word game (moves at "memory", points elsewhere), kept when it beats the record. */
export function gameRecord(
    game: GameId,
    value: number,
): Promise<GameRecordAnswer> {
    return send<GameRecordAnswer>(games.record(game), { value });
}

// ---------- Cambridge mock exams ----------

/** The result of a paper: 40 XP for Reading / Listening, 20 for Writing / Speaking. */
export function recordCambridge(
    exam: string,
    test: string,
    paper: CambridgePaperId,
    result: CambridgeResultInput,
): Promise<CambridgeAnswer> {
    return send<CambridgeAnswer>(
        cambridge.store({ exam, test, paper }),
        result,
    );
}

/** The draft of a writing task (task number from 0). */
export function saveCambridgeDraft(
    exam: string,
    test: string,
    task: number,
    text: string,
): Promise<ProgressAnswer & { saved: true }> {
    return send(cambridge.draft({ exam, test }), { task, text });
}

// ---------- settings and the whole progress ----------

/** Changes some of the settings (theme, speech rate, voice, sound). */
export function updateSettings(
    patch: Partial<Settings>,
): Promise<SettingsAnswer> {
    return send<SettingsAnswer>(settings.update(), patch);
}

/** The daily XP goal: 20, 50, 100 or 150. */
export function setGoal(value: number): Promise<GoalAnswer> {
    return send<GoalAnswer>(goal.update(), { goal: value });
}

/** An on/off flag of a page. */
export function setFlag(
    flag: ProgressFlag,
    value: boolean,
): Promise<FlagsAnswer> {
    return send<FlagsAnswer>(flags.update(), { flag, value });
}

/** Deletes ALL progress (settings back to the defaults). */
export function resetProgress(): Promise<SettingsAnswer> {
    return send<SettingsAnswer>(progress.destroy());
}

/** Replaces all progress with a progress.json of the original app (422 when it is not one). */
export async function importProgress(file: File): Promise<SettingsAnswer> {
    return send<SettingsAnswer>(progress.importMethod(), {
        content: await file.text(),
    });
}

/** The address that downloads the progress as a progress.json (for a link with `download`). */
export function exportUrl(): string {
    return progress.exportMethod.url();
}
