// How ExerciseSession talks to the component of one exercise type.
//
// A type component receives the exercise (prop `ex`) and draws its body. It emits:
//   - 'ready' (boolean): whether the Check button can be used (types that are checked with it);
//   - 'answer' (AnswerOutcome): once, when the learner has answered.
// It may offer methods the session calls through a template ref: check() for the Check button and
// the Enter key, onKey(event) for its own keyboard shortcuts.

export interface AnswerOutcome {
    correct: boolean;
    /** The right answer, shown when the learner got it wrong (or as the correct spelling). */
    answer: string | null;
    /** Shown instead of the praise of a right answer (e.g. "small spelling mistake"). */
    note?: string | null;
}

export interface ExerciseControls {
    check?: () => void;
    onKey?: (event: KeyboardEvent) => void;
}
