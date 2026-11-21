// The course content, exactly as it is stored in resources/content/** (see docs/content/SCHEMA*.md).
// Pages receive it in this shape. Lesson texts are Romanian (…Ro), the material to learn is English.

/** Level as written inside the content files. */
export type ContentLevel = 'A1' | 'A2' | 'B1' | 'B2';

/** A word or short phrase: a unit's `vocab` item, a word-bank item. */
export interface VocabWord {
    en: string;
    ro: string;
    /** noun | verb | adj | adv | phrase | prep | pron | conj | other */
    pos: string;
    ipa: string;
    emoji: string;
    /** English Wikipedia article whose picture illustrates the word (concrete things only). */
    wiki?: string;
    /** Example sentence and its translation. */
    ex: string;
    exRo: string;
}

/** What WordPicture needs: a vocabulary word, or any { en, emoji?, wiki? } (a Cambridge photo task). */
export type PictureSubject = Pick<VocabWord, 'en'> &
    Partial<Pick<VocabWord, 'emoji' | 'wiki'>>;

/** An English sentence with its Romanian translation. */
export interface Example {
    en: string;
    ro: string;
}

// ---------- exercises: a discriminated union on `type` ----------

/** Multiple choice: `answer` is the index of the correct option. `q` may contain a "___" gap. */
export interface McExercise {
    type: 'mc';
    q: string;
    options: string[];
    answer: number;
    explainRo?: string;
    /** Cambridge reading: a short text only for this question (a sign, a note, an email). */
    text?: string;
    /** Cambridge listening: a script only for this question. */
    audio?: ScriptLine[];
}

/** Type the missing word(s): `q` contains exactly one "___"; `answers` are all accepted answers. */
export interface FillExercise {
    type: 'fill';
    q: string;
    answers: string[];
    explainRo?: string;
}

/** Put the words in order: `words` in the correct order, `alt` other accepted orders. */
export interface OrderExercise {
    type: 'order';
    words: string[];
    alt?: string[][];
    ro: string;
    explainRo?: string;
}

/** Translate the Romanian sentence into English by typing. */
export interface TranslateExercise {
    type: 'translate';
    ro: string;
    answers: string[];
    explainRo?: string;
}

/** Dictation: the app reads `audio` aloud, the learner types it. */
export interface ListenExercise {
    type: 'listen';
    audio: string;
    answers: string[];
    ro?: string;
    explainRo?: string;
}

/** Match pairs: [english, romanian]. */
export interface MatchExercise {
    type: 'match';
    pairs: [string, string][];
    explainRo?: string;
}

export type Exercise =
    | McExercise
    | FillExercise
    | OrderExercise
    | TranslateExercise
    | ListenExercise
    | MatchExercise;

export type ExerciseType = Exercise['type'];

// ---------- unit (unitNN.json) ----------

export interface GrammarTable {
    headers: string[];
    rows: string[][];
}

export interface GrammarSection {
    headingRo: string;
    /** Markdown: **bold**, *italic*, newlines. */
    textRo: string;
    table?: GrammarTable;
    examples: Example[];
}

export interface Grammar {
    title: string;
    titleRo: string;
    sections: GrammarSection[];
    /** Typical mistakes of Romanian speakers (markdown). */
    tipsRo: string[];
}

export interface DialogueLine {
    speaker: string;
    en: string;
    ro: string;
}

export interface Dialogue {
    titleRo: string;
    lines: DialogueLine[];
}

export interface Reading {
    title: string;
    /** Markdown, paragraphs separated by a blank line. */
    text: string;
    textRo: string;
    questions: McExercise[];
}

export interface Video {
    youtubeId: string;
    title: string;
}

export interface Unit {
    /** "<level>-u<2 digits>", e.g. "a1-u01". */
    id: string;
    level: ContentLevel;
    order: number;
    title: string;
    titleRo: string;
    emoji: string;
    goalRo: string;
    vocab: VocabWord[];
    grammar: Grammar;
    dialogue: Dialogue;
    reading: Reading;
    exercises: Exercise[];
    video?: Video;
}

/** bankNN.json: the unit's extra words. */
export interface WordBank {
    unit: string;
    words: VocabWord[];
}

/** testNN.json: the new questions of a unit test ("match" is not used). */
export interface UnitTestItems {
    unit: string;
    questions: Exclude<Exercise, MatchExercise>[];
}

/** placement.json: 12 multiple-choice questions of one level. */
export interface Placement {
    level: ContentLevel;
    questions: McExercise[];
}

// ---------- speaking (speaking.json) ----------

export interface SpeakingTurn {
    /** What the partner says, and its translation. */
    say: string;
    sayRo: string;
    /** What to answer, as an idea in Romanian. */
    hintRo: string;
    /** Model answers. */
    answers: string[];
    /** Words or phrases every model answer contains. */
    keywords: string[];
}

export interface SpeakingScenario {
    /** "<level>-s<2 digits>". */
    id: string;
    emoji: string;
    title: string;
    titleRo: string;
    youRo: string;
    partner: string;
    turns: SpeakingTurn[];
    closing?: string;
}

export interface PhraseTopic {
    topic: string;
    topicRo: string;
    emoji: string;
    items: Example[];
}

export interface SpeakingQuestion {
    q: string;
    qRo: string;
    starters: string[];
    sample: string;
}

export interface Speaking {
    level: ContentLevel;
    scenarios: SpeakingScenario[];
    phrases: PhraseTopic[];
    questions: SpeakingQuestion[];
}

// ---------- irregular verbs (verbs.json) ----------

export interface IrregularVerb {
    v1: string;
    /** Alternatives are written with " / ": "learnt / learned". */
    v2: string;
    v3: string;
    level: ContentLevel;
    ro: string;
    ipa: string;
    ex2: string;
    ex2Ro: string;
    ex3: string;
    ex3Ro: string;
}

// ---------- Cambridge mock exams (cambridge/<exam>/) ----------

/** One line of a script read aloud, "s" is the speaker label. */
export interface ScriptLine {
    s: string;
    en: string;
}

/** Cambridge questions are multiple choice or typed answers. */
export type CambridgeQuestion = McExercise | FillExercise;

export interface CambridgePart {
    title: string;
    instructionsRo: string;
    /** Shared passage (markdown). */
    text?: string;
    /** Listening: one script shared by all the questions of the part. */
    audio?: ScriptLine[];
    questions: CambridgeQuestion[];
}

export interface WritingTask {
    title: string;
    task: string;
    taskRo: string;
    minWords: number;
    maxWords: number;
    checklistRo: string[];
    model: string;
    usefulPhrases: Example[];
}

export interface SpeakingTask {
    title: string;
    infoRo: string;
    prompts: string[];
    /** Wikipedia article whose picture fits a "describe the photo" task. */
    wiki?: string;
    sceneRo?: string;
    usefulPhrases: Example[];
    model: string;
}

export interface CambridgeTest {
    /** "<exam>-test<N>". */
    id: string;
    title: string;
    reading: { minutes: number; parts: CambridgePart[] };
    listening: { minutes: number; parts: CambridgePart[] };
    writing: { minutes: number; tasks: WritingTask[] };
    speaking: { minutes: number; tasks: SpeakingTask[] };
}

export interface CambridgeExam {
    /** "pet" or "fce". */
    id: string;
    name: string;
    level: ContentLevel;
    aboutRo: string;
    papersRo: { name: string; info: string }[];
}

// ---------- the exercise runner (components/exercise/ExerciseSession.vue) ----------

/** One question of a session; `unit` and `key` are passed back untouched in the results. */
export interface ExerciseItem {
    ex: Exercise;
    unit?: string;
    key?: string;
}

/** First-attempt result of one question, in the order the questions were answered. */
export interface ExerciseResult {
    ex: Exercise;
    unit?: string;
    key?: string;
    correct: boolean;
}

export type SessionMode = 'practice' | 'test';
