# Front-end engine

Answer checking, speech, listening, recording, sound effects and the exercise runner. Every lesson
page is built from these pieces. They port `public/js/util.js` and `public/js/exercise.js` of the
original app one to one: same rules, same numbers, same markup and classes (so the ported style
sheet styles them).

The engine never talks to the server. Components emit what happened (`finish` with the results,
`exit` …); the page decides what to save through `@/api/progress`.

## 1. Content types — `@/types` (`resources/js/types/content.ts`)

| Type | What |
| --- | --- |
| `Unit` | `unitNN.json`: `vocab: VocabWord[]`, `grammar: Grammar`, `dialogue: Dialogue`, `reading: Reading`, `exercises: Exercise[]`, `video?: Video` |
| `VocabWord` | `{ en, ro, pos, ipa, emoji, wiki?, ex, exRo }` (unit vocab and word bank) |
| `Grammar` / `GrammarSection` / `GrammarTable` | `{ title, titleRo, sections, tipsRo }` / `{ headingRo, textRo, table?, examples }` / `{ headers, rows }` |
| `Dialogue` / `DialogueLine`, `Reading`, `Video`, `Example` | `{ titleRo, lines }` / `{ speaker, en, ro }`, `{ title, text, textRo, questions: McExercise[] }`, `{ youtubeId, title }`, `{ en, ro }` |
| `Exercise` | union on `type`: `McExercise` (`mc`), `FillExercise` (`fill`), `OrderExercise` (`order`, `alt?`), `TranslateExercise` (`translate`), `ListenExercise` (`listen`), `MatchExercise` (`match`); `ExerciseType` = `Exercise['type']` |
| `WordBank`, `UnitTestItems`, `Placement` | `bankNN.json`, `testNN.json`, `placement.json` |
| `Speaking` | `speaking.json`: `SpeakingScenario` (with `SpeakingTurn`), `PhraseTopic`, `SpeakingQuestion` |
| `IrregularVerb` | an item of `verbs.json` |
| `CambridgeExam`, `CambridgeTest` | `exam.json`, `testN.json`: `CambridgePart`, `CambridgeQuestion` (= `McExercise \| FillExercise`), `ScriptLine` `{ s, en }`, `WritingTask`, `SpeakingTask` |
| `ExerciseItem`, `ExerciseResult`, `SessionMode` | what `ExerciseSession` takes and gives back (section 4) |
| `PictureSubject` | `{ en, emoji?, wiki? }`, what `WordPicture` needs |

## 2. Pure helpers

`@/utils/text`

| Function | |
| --- | --- |
| `esc(text)` | escapes `& < > " '` |
| `md(text)` | tiny markdown (`**bold**`, `*italic*`, newlines → `<br>`) on escaped text: safe for `v-html` |
| `gap(text)` | `md()` plus `___` → `<span class="gap"></span>` (questions with a gap) |
| `norm(text, expand = true)` | lower case, plain quotes, no punctuation/dashes, one space; contractions written in full (`I'm` → `i am`, `can't` → `can not`) unless `expand` is false |
| `lev(a, b)` | Levenshtein distance |
| `checkText(input, answers, typo = false)` | `{ ok, typo? }`. Compares `norm()`ed texts; empty input is never right; with `typo` one edit is forgiven when the answer is 10+ characters (translate / listen use it, fill does not) |
| `speechScore(target, heard[])` | 0..1, share of the target's words in the best alternative heard |
| `speechDetail(target, heard[])` | `{ score, heard, words: [{ w, hit }] }` word by word, for showing what was missed |

`@/utils/random`: `shuffle(list)` (copy), `sample(list, n)`, `pick(list)`.
`@/utils/dates`: `today(d?)` → `'YYYY-MM-DD'` (local).

The unit tests are next to the code (`resources/js/**/*.test.ts`); run them with `npm run test`
(`vp test`). They run in Node; a test that needs a DOM (components, recognition) starts with
`// @vitest-environment happy-dom` and mounts with `@vue/test-utils`
(see `components/exercise/ExerciseSession.test.ts`).

## 3. Browser helpers

All of them read the settings at call time from `learnerState.settings` (`@/utils/learner`):
the voice and rate for speech, `sound` for the effects.

| Import | |
| --- | --- |
| `speak(text, { slow?, onend? })` — `@/utils/speech` | reads English aloud; cancels anything being read; `___` and `**` are skipped; texts over 180 characters are read sentence by sentence; `slow` = 60 % of the rate |
| `stopSpeak()` | stops speech and any script. **The layout calls it on every page change.** |
| `speakScript(lines: ScriptLine[], onend?)` | a script `{ s, en }[]`, one voice (or pitch) per speaker, 280 ms between lines; `onend` only if it was not stopped |
| `englishVoices()` | the English voices (settings page) |
| `canListen()` — `@/utils/recognition` | the browser has speech recognition |
| `listen(onstate?)` | one sentence. Returns `{ done: Promise<string[]>, stop() }`. `onstate(kind, value)`: `'ready'` (microphone name), `'level'` (0..1), `'interim'` (text so far), `'info'` (language pack download message), `'retry'` (switched to on-device recognition: speak again). `done` rejects with a `MicError` `{ code, text }` (`text` is translated); 15 s limit; microphone, meter and recogniser are released when `done` settles. `stop()` = "I've finished speaking"; before the recogniser has started it cancels (`code: 'aborted'`). Write the callback with both parameters, `(kind, value) => …`: TypeScript then knows `value` is a number for `'level'` and a string otherwise |
| `listenStream(onText, onstate?)` | keeps listening (games). `{ stop(), reset() }`; `onText(text)` = everything since the last `reset()`. `onstate`: `'ready'`, `'info'`, `'error'` (a `MicError`, listening has ended) |
| `MIC_FATAL`, `isMicError(e)`, `micError(code)` | codes after which retrying is useless (offer `Recorder` instead); type guard; the error/explanation for a code |
| `speechEngine()` | `'cloud'` or `'local'` — when the online service is unreachable both functions switch to Chrome's on-device recognition and remember it in this browser (`localStorage['el-stt']`) |
| `canRecord()`, `startRecording(onDone)` — `@/utils/recording` | used by `Recorder`; `startRecording` resolves to `{ stop(), cancel() }` and hands over an object URL that the owner must revoke |
| `sfx('ok' \| 'bad' \| 'win' \| 'flip')` — `@/utils/sound` | sound effects, silent when `settings.sound` is off |
| `confetti(ms = 2200)` — `@/utils/confetti` | confetti over the page; removes itself; returns a function that ends it early |
| `wikiImage(title)` — `@/utils/wiki` | thumbnail URL of an English Wikipedia article or `null`; cached in `localStorage['el-img']` |

### Cleaning up (the rule)

Whatever a page starts, it stops in `beforeUnmount`:

```ts
data() {
    return { listening: null as Listening | null, stream: null as ListeningStream | null };
},
beforeUnmount() {
    this.listening?.stop();   // listen()
    this.stream?.stop();      // listenStream()
    stopSpeak();              // when the page started speech or a script itself
},
```

After an `await` on `listening.done`, check that the component is still mounted (keep an `alive`
flag set to false in `beforeUnmount`) before touching its data. The components below clean up after
themselves.

## 4. Components

### `ExerciseSession` — `@/components/exercise/ExerciseSession.vue`

The original `runSession()`: one exercise at a time, Check / feedback / Continue, progress bar,
✕ (asks for confirmation), Enter to check and to continue, 1–9 for multiple choice.

| Prop | |
| --- | --- |
| `items: ExerciseItem[]` | `{ ex, unit?, key? }`; read once at mount |
| `mode: 'practice' \| 'test'` | practice (default): explanation after each answer, wrong ones asked again at the end ("Retry"); test: right / wrong only |
| `title?: string` | shown under the bar |

| Event | |
| --- | --- |
| `finish(results: ExerciseResult[])` | `{ ex, unit, key, correct }` per item, first attempt only, in answer order (as the original `onFinish`) |
| `exit()` | the learner confirmed leaving |

Speech and the keyboard listener stop on finish, exit and unmount. After `finish` the session
draws nothing more: show a `ResultCard` instead. To run it again, give it a new `:key`.

```vue
<ExerciseSession v-if="!results" :key="run" :items="items" mode="practice"
    :title="$t('Quick check: do you remember the words?')"
    @finish="results = $event" @exit="backToUnit" />
<ResultCard v-else :score="score" :title="title" :lines="lines">
    <template #actions><button class="btn" @click="results = null; run++">…</button></template>
</ResultCard>
```

The six exercise types are `ExerciseChoice` (mc), `ExerciseFill`, `ExerciseOrder`,
`ExerciseTranslate`, `ExerciseListen`, `ExerciseMatch`, with `ExerciseFeedback`; the session picks
them, pages do not use them directly (the contract between them is in `components/exercise/types.ts`).

### `ResultCard` — `@/components/exercise/ResultCard.vue`

Props `score` (0..1), `title`, `passed?` (false paints the ring red), `passMark?` (0..1, "Pass mark: 80%"),
`lines?: string[]` (markdown paragraphs). Default slot: custom content before the lines (e.g. the
units of an exam). Slot `actions`: the buttons.

### Speech and content

| Component | Props | |
| --- | --- | --- |
| `SpeakButton` (`speech/`) | `text` | 🔊 `icon-btn speak`; extra classes on the tag |
| `Recorder` (`speech/`) | `label?` | record yourself / listen back; microphone, recording and audio URL released on unmount; a blocked microphone shows a toast |
| `WordPicture` (`content/`) | `word: PictureSubject` | `.pic` with the emoji, the Wikipedia photo once loaded (`has-img`); pass `class="big"` / `"photo"` |
| `GrammarLesson` (`content/`) | `grammar: Grammar` | sections, tables, spoken examples, common mistakes |
| `VideoCard` (`content/`) | `video: Video` | thumbnail first; the `youtube-nocookie` player only after a click |

### What became events

The original helpers never wrote progress themselves; the pages did (`addMistake`, `addXp`,
`completeStep` in `onFinish`). It stays that way: the page reads the `finish` results and calls
`@/api/progress`. Celebrations (`confetti()`, `sfx('win')`) are also the page's call, as in the
original views.

## 5. Interface texts

The Romanian texts of the engine are in `resources/js/i18n/ro/engine.json`. The microphone help
names the page's own address (`location.host`) instead of the original's fixed `localhost:4747`.
