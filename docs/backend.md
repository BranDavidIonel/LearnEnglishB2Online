# Backend: database, progress services, progress API

How the learner's progress is stored, which service holds which rule of the original app
(`public/js/store.js`), and the JSON API the pages call through `@/api/progress`. Read
`docs/architecture.md` first. The numbers of the rules (pass marks, XP, intervals, limits) are in
`config/learning.php` (`rules` and `xp`); this file quotes their current values.

Conventions used everywhere below:

- **Days** are the learner's local calendar days (app timezone `Europe/Bucharest`), written `Y-m-d`.
  The original kept review days as day numbers (days since 1970-01-01); the database keeps dates
  and `progress:import` / `progress:export` convert (`App\Services\Progress\Calendar`).
- **Scores** are fractions: `0.8` = 80%.
- **Content ids**: units `a1-u01`, levels `a1`…`b2`, words by their English text (`"how are you?"`),
  verbs by their base form (`"be"`), Cambridge exams `pet` / `fce` and tests `pet-test1`.
- Content arrays keep the keys of the JSON files (`titleRo`, `exRo`, `testItems`…).

## Database

Every table except `users` has a `user_id` (cascade on delete), so the app could take more users.
Rows that keep an order (deck, mistakes, Cambridge tests) are read by `id`, which is the order in
which they were added. No row is made for a learner until something changes.

| Table | One row per | Holds (progress.json key) |
| --- | --- | --- |
| `users` | account | + `locale` (`en` / `ro`, null = default): the interface language, restored at login |
| `learner_profiles` | user | `xp`, `goal`, `streak_count` + `streak_last` (`streak`), `theme`, `speech_rate`, `voice`, `sound` (`settings`), `spoken` (`speak.said`), `flags` (JSON, `flags`) |
| `placements` | user | `start_index`, `scores` (JSON `{a1: 0.83}`), `taken_on` (`placement`) |
| `daily_xp` | user + day | XP earned that day (`days`) |
| `progress_snapshots` | user + day | chart point: `xp`, `words` (learned), `verbs` (learned), `b1`, `b2` (% of the road, 1 decimal); nullable for old snapshots (`history`) |
| `unit_progress` | user + unit | `steps` (JSON list of lesson steps done), `best`, `passed`, `review`, `tries` (`units`) |
| `exam_results` | user + level | `best`, `passed`, `tries`, `last_taken_on` (`exams`) |
| `word_reviews` | user + word | `box` 0–6, `due_on`, `ok`, `bad`, `known` (`words`); no row = "new" |
| `verb_reviews` | user + verb | same as words (`verbs`) |
| `mistakes` | user + key | `key` `"<unit>:<n>"`, `"<unit>:t<n>"`, `"<unit>:r<n>"`; oldest first, max 200 (`mistakes`) |
| `best_scores` | user + area + game | `area` `games` (`memory`, `sprint`, `spell`) or `speaking` (`pairs`, `sprint`), `value` (`games`, `speak.best`) |
| `speaking_scores` | user + scenario | best `score` of a conversation (`a1-s01`) or role-play (`role-a1-u01`) (`speak.scen`) |
| `cambridge_tests` | user + exam + test | `drafts` (JSON `{task: text}`) |
| `cambridge_results` | test + paper | `result` (JSON as the page sent it: `{score, correct?, total?, parts?}`), `taken_on` (`cambridge`) |

Models (`app/Models`) have the same names in the singular; `User` has a relation to each table
(`profile`, `placement`, `dailyXp`, `snapshots`, `unitProgress`, `examResults`, `wordReviews`,
`verbReviews`, `mistakes`, `bestScores`, `speakingScores`, `cambridgeTests`, `cambridgeResults`).
Scores (`best`, `b1`, `b2`, `score`, `speech_rate`) are saved with every digit (see `exact()` in
the models), so an imported file comes back unchanged.

Enums (`app/Enums`): `Level` (id, `label()` "A1", `title()`, `description()`, `color()`),
`UnitStep` (`lessons()` = the five lesson steps, plus `test`), `WordStatus` (learned / learning /
new), `Theme` (`label()`, `swatch()`, `options()` for the theme picker, names translated), `Game`,
`SpeakingGame`, `ScoreArea`, `CambridgePaper` (`isMarked()`), `Readiness`, `NextActionKind`.

Commands: `php artisan progress:import {file} {--email=}` replaces a user's progress with a
`progress.json` of the original app (default user: `config('learning.admin.email')`);
`php artisan progress:export {--email=} {--output=}` writes it back (to the screen without
`--output`). Import → export gives back the same values (also for words, units and mistakes no
longer in the content).

## Progress services

All in `app/Services/Progress`, each method takes the `User` explicitly. Read a page's data in its
controller from these services; never repeat a rule in a controller or a `.vue` file.
`LearnerState` keeps the learner's rows for the request (the original "S.p"), so asking the same
question many times costs one query.

**Content** — `App\Services\Content\ContentRepository` (singleton; derived pieces cached under a
fingerprint of the files, emptied by `cache:clear` / `optimize:clear`):

- `levels()` — the 4 levels: `{id, name, index, placement (question count), units: [outline]}`; a unit outline is `{id, order, title, titleRo, emoji, levelId, index, vocab: [en], bank: [en], exercises: count, testItems: count}`.
- `level($id)`, `unitOutline($id)` — one of the above, or null.
- `unit($id)` — the whole unit file + `bank`, `testItems`, `levelId`, `index`; `units($levelId)` — all of a level.
- `placement($levelId)` — its questions; `speaking($levelId)` — `{level, scenarios, phrases, questions}` or null.
- `words()` — word index `en => word + unit, levelId, bank?` (first occurrence wins, core words before the bank); `word($en)`; `wordLevels()` — `en => levelId` (light).
- `verbs()` — verbs + `rank`, `levelId`; `verb($v1)`.
- `cambridge()` — exams (`exam.json` + `id` + `tests`); `cambridgeExam($id)`.

**Calendar** (static): `today()`, `todayString()`, `inDays($n)`, `dayNumber($date)`, `fromDayNumber($n)`.

**XpService** — XP and the streak:

- `award($user, $n)` — adds XP to the total and today; a new day continues the streak if the last day was yesterday, else restarts it at 1; 0 does nothing.
- `total($user)`, `today($user)` — XP; `days($user)` — `{Y-m-d: xp}`.
- `streak($user)` — the stored streak while the last day with XP is today or yesterday, else 0.

**CourseProgressService** — the course (read only):

- `startIndex($user)` — placement level index (0 without a test).
- `levelPassed`, `levelSkipped` (below the placement level, exam not passed), `levelDone` (either), `levelUnlocked` (index 0, ≤ placement level, or the level before done).
- `unitUnlocked($user, $unit)` — level open and (first unit, level done, unit passed or in review, or previous unit passed).
- `unitState($user, $unit)` — `{steps, best, passed, review, tries}` (defaults when untouched).
- `unitProgress` — passed and not in review = 1, else steps done / 5 × 0.8; `levelProgress` — done = 1, else 0.9 × average of its units; `progressTo($user, $level)` — average progress of every level up to it.
- `currentLevel($user)` — first level not done (with units), else the last; `achieved($user)` — highest level id done from A1 without a gap, or null.
- `reviewUnits($user, $level)` — unit ids in review.
- `nextAction($user)` — `{kind: unit|exam|done, level, unit, step, review}`: the test of the first unit in review, else the first missing lesson step (or the test) of the first unit not passed, else the exam.
- `unitSummary($user, $unit)` / `levelSummary($user, $level)` — the arrays behind `UnitResource` / `LevelResource`.
- `examState($user, $level)` — `{best, passed, tries, last}` or null.

**UnitService**:

- `completeStep($user, $unit, UnitStep)` — marks the step; vocab adds the unit's core words to the deck (box 0, due today, existing ones untouched); 10 XP the first time, practice 0. → `{first, xp, unit}`.
- `recordUnitTest($user, $unit, $score)` — tries + 1, best kept; from 80%: passed, out of review, 30 XP first time / 10 after. → `{passed, first, xp, unit}`.

**ExamService**:

- `buildExam($level)` — 3 random exercises of each unit, no `match` type, unit after unit: `[{ex, unit, key "<unit>:<index>"}]` (shuffle on the page, like the original).
- `recordExam($user, $level, [{unit, correct}])` — score; tries, best, last day; weak units (< 60%) go to review when failed; passed (≥ 80%): every unit of the level out of review, 150 XP first time / 20 after. → `{score, passed, first, xp, weak, byUnit}`.

**PlacementService**:

- `recordPlacement($user, {level: score})` — start = first level below 75% counting from A1 (max B2); replaces the last result; 20 XP. → `{start, level, xp, placement}`.
- `placement($user)` — `{start, level, scores, date}` or null.

**WordReviewService** — words (word arrays come from the word index):

- `status($user, $en)` — new / learning / learned (box ≥ 3); `counts($user, $words = all)` — `{learned, learning, new, total}`.
- `due($user)` — words due today or earlier, still in the course, most overdue first; `dueCount($user)`.
- `grade($user, $en, 0|1|2)` — 0: box 0, bad + 1, today; 1: box ≥ 1, tomorrow, ok + 1; 2: box + 1 (max 6), today + interval (0, 1, 3, 7, 14, 30, 60 days), ok + 1.
- `markKnown` (box 6, +60 days, known), `sortedKnown` (box 3, +7 days, ok 1, known), `learn` (box 0, today), `forget` (out of the deck).
- `addUnitWords($user, $unit)`; `skippedLevelWords($user)` — words of skipped levels still new.
- `bankWords($unit)`, `unitWords($unit)` (core then bank, once each), `levelWords($level)`; `vocabTo($user, $level)` — counts of all words up to that level.
- `wordsToLearn($user, $level)` — new words of its open units, all of them; `nextWordsLevel($user)` — first level up to the current one with some. A quick-sort batch is the first `config('learning.rules.word_batch')` (20) of that list, as in the original; the page or its controller takes the slice.
- `knownWords($user, $min = 8)` — words in the deck (still in the course), or the core words of the first 3 units of the current level when fewer than `$min`.
- `withState($user, $word)` — word + `status, box, due, known` (behind `WordResource`).

**VerbReviewService** — irregular verbs:

- `alternatives($form)`, `answers($form)` (static) — `"was / were"` → `[was, were]`; accepted answers `[was, were, "was were", "was/were", "was / were"]`.
- `status`, `counts($user)` (over the verb list), `due($user)` (most overdue first, then by rank), `dueCount($user)`.
- `grade($user, $v1, 0|2)`, `markKnown` (box 3, +7 days, ok 1, known), `learn`, `forget`, `withState`.

**MistakeService**:

- `add($user, $key)` (once; oldest dropped past 200), `clear($user, $key)`, `keys($user)`.
- `items($user)` — `[{ex, unit, key}]`: `<n>` = exercise, `t<n>` = test question; reading questions and keys pointing nowhere are left out. `count($user)` — the badge.

**CambridgeService**:

- `tests($user, $exam)` — `{testId: {paper: {…result, date}, drafts?}}`; `state($user, $exam, $test)`.
- `record($user, $exam, $test, CambridgePaper, $data)` — replaces that paper's result, dated today; 40 XP Reading / Listening, 20 Writing / Speaking. → `{result, xp}`.
- `saveDraft($user, $exam, $test, $task, $text)`.
- `readiness($user, $exam)` — `{latest (per paper), course (progressTo the exam level), overall, verdict, done}`: ready = 4 papers and Reading, Listening and average ≥ 70%; close = average ≥ 60%; unknown without Reading / Listening.

**SpeakingService**: `state($user)` — `{said, scen, best}`; `countSpoken($user, $count, $xpEach)` (2 or 3 XP each);
`recordConversation($user, $scenario, $score)` — best kept, 15 XP first / 5 after → `{first, xp, best}`;
`record($user, SpeakingGame, $value)` — higher is better → `true` for a record.

**GameService**: `records($user)` — `{memory, sprint, spell}`; `record($user, Game, $value)` — memory: fewer moves; others: more; a record of 0 counts as none. Game XP is added by the page (`addXp`).

**BadgeService**: `badges($user)` — the 23 badges `[{icon, name, desc, ok}]`, names translated.

**SettingsService**: `settings($user)` — `{theme, rate, voice, sound}`; `update($user, $patch)`; `goal`, `setGoal`; `flags`, `setFlag` (`FLAGS`: `keepSkippedWords`).

**HistoryService**: `snapshot($user)` — writes today's chart point (done after every API change); `touch($user)` — makes it on the first page of a day (shared props); `history($user)` — `{Y-m-d: {xp, w, v, b1, b2}}`.

**ResetService**: `reset($user)` — deletes all progress, settings back to defaults (the language stays).

**LearnerSummary**: `for($user)` — the `learner` shared prop: `{xp, xpToday, goal, streak, level: {id, name, color}|null, due: {words, verbs, mistakes}}`.

**ProgressImporter / ProgressExporter**: `importJson($user, $text)` / `import($user, $array)` — validates
(422 / `ValidationException` when it is not a progress file) and replaces everything;
`export($user)` / `toJson($user)` — the original `progress.json` format.

Shared props (`HandleInertiaRequests`): `learner` (above) and `settings`, both null for a guest.

## Progress API

`routes/web/api.php`, names `api.*`, session login + CSRF, JSON. Call them only through
`@/api/progress` (types in `@/types`, `types/progress.ts`). **Every answer also carries `learner`**
(the fresh summary; `send()` stores it) and refreshes today's chart point. Validation errors are
standard 422 JSON (`HttpError.errors`); an unknown unit / exam / test in the URL is a 404.
Word and verb functions take one item or a list (bulk: "mark all as known", sort batches).

| `@/api/progress` | Method + URL | Answer (besides `learner`) |
| --- | --- | --- |
| `completeStep(unit, step)` | `POST /api/units/{unit}/steps` `{step}` | `{first, xp, unit: UnitSummary}` |
| `recordUnitTest(unit, score)` | `POST /api/units/{unit}/test` `{score}` | `{passed, first, xp, unit, next: UnitSummary\|null, review: unitIds}` |
| `recordExam(level, results)` | `POST /api/exams/{level}` `{results: [{unit, correct}]}` | `{score, passed, first, xp, weak, byUnit, level: LevelSummary, next: LevelSummary\|null, units: UnitSummary[]}` |
| `recordPlacement(scores)` | `POST /api/placement` `{scores: {a1: 0.8}}` | `{start, level, xp, placement, levels: LevelSummary[]}` |
| `gradeWord(words, grade)` | `POST /api/words/grade` `{words, grade: 0\|1\|2}` | `{words: WordWithState[]}` |
| `markKnown(words)` | `POST /api/words/known` `{words}` | `{words}` |
| `sortedKnown(words)` | `POST /api/words/sorted` `{words}` | `{words}` |
| `learnWord(words)` | `POST /api/words/learn` `{words}` | `{words}` |
| `forgetWord(words)` | `POST /api/words/forget` `{words}` | `{words}` |
| `gradeVerb(verbs, grade)` | `POST /api/verbs/grade` `{verbs, grade: 0\|2}` | `{verbs: VerbWithState[]}` |
| `verbKnown(verbs)` | `POST /api/verbs/known` `{verbs}` | `{verbs}` |
| `learnVerb(verbs)` | `POST /api/verbs/learn` `{verbs}` | `{verbs}` |
| `forgetVerb(verbs)` | `POST /api/verbs/forget` `{verbs}` | `{verbs}` |
| `addMistake(keys)`, `clearMistake(keys)`, `updateMistakes({add, clear})` | `POST /api/mistakes` `{add?, clear?}` | `{added, cleared, mistakes: count}` |
| `addXp(amount)` | `POST /api/xp` `{amount: 0..100}` | `{xp}` |
| `countSpoken(count = 1, xpEach = 2)` | `POST /api/speaking/spoken` `{count, xp: 2\|3}` | `{xp, said}` |
| `recordConversation(scenario, score)` | `POST /api/speaking/conversations` `{scenario, score}` | `{first, xp, best}` |
| `speakRecord(game, value)` | `POST /api/speaking/records/{pairs\|sprint}` `{value}` | `{record, best}` |
| `gameRecord(game, value)` | `POST /api/games/{memory\|sprint\|spell}/record` `{value}` | `{record, records}` |
| `recordCambridge(exam, test, paper, result)` | `POST /api/cambridge/{exam}/{test}/{paper}` `{score, correct?, total?, parts?}` | `{result, xp, readiness}` |
| `saveCambridgeDraft(exam, test, task, text)` | `PUT /api/cambridge/{exam}/{test}/drafts` `{task, text}` | `{saved: true}` |
| `updateSettings(patch)` | `PATCH /api/settings` `{theme?, rate?, voice?, sound?}` | `{settings}` |
| `setGoal(goal)` | `PUT /api/goal` `{goal: 20\|50\|100\|150}` | `{goal}` |
| `setFlag(flag, value)` | `PUT /api/flags` `{flag, value}` | `{flags}` |
| `resetProgress()` | `DELETE /api/progress` | `{settings}` (defaults) |
| `importProgress(file)` | `POST /api/progress/import` `{content: file text}` | `{settings}` |
| `exportUrl()` | `GET /api/progress/export` | download `english-levels-progress.json` |

Resources (`app/Http/Resources`, reuse them in page controllers): `UnitResource`
(`UnitSummary`), `LevelResource` (`LevelSummary`, title and description translated), `WordResource`
(`WordWithState`), `VerbResource` (`VerbWithState`); build them from
`CourseProgressService::unitSummary()` / `levelSummary()` and `WordReviewService` /
`VerbReviewService::withState()`.

What the original pages did with several calls stays several calls, sent in order by `send()`:
practice = `completeStep(unit, 'practice')` + `addXp(right × 2)` + `updateMistakes(…)`; unit test =
`updateMistakes(…)` + `recordUnitTest`; mistakes review = `clearMistake(right)` + `addXp(right × 2)`;
games = `gameRecord` + `addXp`; speaking games = `speakRecord` + `addXp`.
