# Architecture

English Levels is a single-user web app for learning English from A1 to B2 (for a Romanian speaker).
It is a rewrite of a local, vanilla-JS app into Laravel 13 + Inertia 3 + Vue 3 (TypeScript) + Tailwind CSS 4.
This file is the map of the project: where things live, which rules they follow and how a new page or
feature is added. Read it before changing anything. The details are in `docs/backend.md` (database,
services, progress API), `docs/frontend-ui.md` (style sheet, layout, generic components) and
`docs/frontend-engine.md` (answer checking, speech, the exercise runner).

## 1. Principles

1. **Content is files, progress is database.** The course (units, word banks, tests, speaking,
   Cambridge mocks, irregular verbs) is JSON under `resources/content/`, in the format described in
   `docs/content/`. It is read through `App\Services\Content\ContentRepository`, never directly.
   Everything the learner does is stored in database tables, per user.
2. **The rules live in PHP.** XP, the day streak, unlocking of units and levels, pass marks, spaced
   repetition, exam review, placement, Cambridge readiness, badges: all of it is in
   `app/Services/Progress/`, covered by Pest tests. The browser never decides what a result is worth.
3. **The browser presents.** It draws the pages, checks typed answers against the answers that come
   with the content, speaks (speech synthesis), listens (speech recognition) and records. When
   something worth remembering happens it tells the server through the progress API.
4. **One look.** The style sheet of the original app is kept class for class, with its four colour
   themes (`dark`, `slate`, `light`, `sepia`), so every page looks like the original one.
5. **Two interface languages**, English (default) and Romanian. The lesson content itself
   (explanations, translations) is always Romanian: it is what the learner learns from.
6. **Nothing personal in git.** No credentials and no progress file in the repository; the admin
   account comes from `.env` through the seeder, progress comes in through `progress:import`.

## 2. Folder map

```
app/
  Console/Commands/      content:validate, progress:import, progress:export
  Enums/                 Level, UnitStep, Theme, WordStatus …  (ids, labels, colours)
  Http/
    Controllers/         one thin controller per area; Api/ holds the JSON progress API
    Middleware/          HandleInertiaRequests (shared props, UTF-8 JSON), SetLocale, SecurityHeaders
    Requests/            validation of every write: Api/ (progress API), Account/, LoginRequest
    Resources/           the shapes sent to the pages: shared ones here, Home/ Words/ Verbs/ for one area
  Models/                the learner's data
  Services/
    Content/             ContentRepository (+ Validation/ for content:validate)
    Progress/            the rules: one small service per subject
    Course/ Home/ Words/ Verbs/ Speaking/ Cambridge/ Games/
                         what each page shows, put together from the two above (no rules of their own)
config/learning.php      admin account, content path, the numbers of the rules
resources/
  content/               the course (JSON) — a1/ a2/ b1/ b2/ cambridge/ verbs.json
  css/                   app.css + the ported style sheet, split by subject
  js/
    api/                 progress.ts — typed calls to the JSON progress API
    components/
      general/           ProgressRing, ProgressBar, ChipTag, LevelChip, EmptyState, ToastStack,
                         SessionSave + SaveAlert (a save that failed) …
      layout/            sidebar, top bar, PageHeader, LanguageSwitcher
      modals/            AppModal
      charts/            WeekChart, ProgressChart
      exercise/          ExerciseSession, one component per exercise type, ResultCard
      speech/            SpeakButton, Recorder
      content/           WordPicture, GrammarLesson, VideoCard
      modules/<area>/    what only one area uses
    i18n/                index.ts + ro/<area>.json dictionaries (ro/general.json: words of several areas)
    layouts/             AppLayout (sidebar + top bar), AuthLayout (login)
    pages/               one folder per area, one file per Inertia page
    types/               models.ts (shared props), content.ts, progress.ts — all exported from '@/types'
    utils/               learner, http, saving, toast, text, random, dates, speech, recognition, recording, sound …
routes/
  web.php                login, locale, then every routes/web/*.php inside the auth group
  web/<area>.php         the routes of one area
tests/
  Feature/               one file (or folder) per area + the progress API
  Unit/                  the rules, one file per service
docs/                    this file and the guides it names; content/ (the content format)
```

## 3. Request flow

- **Opening a page** — `GET /learn` → `routes/web/home.php` → a controller asks the Progress and
  Content services for what the page shows → `Inertia::render('Learn', [...])` → `resources/js/pages/Learn.vue`.
  Controllers stay thin: no rule is written in a controller or in a `.vue` file.
- **Doing something on a page** (a word graded, a step finished, a test result) — the page calls a
  function of `@/api/progress` (for example `gradeWord('customer', 2)`). It sends JSON to a route under
  `/api/…`, a Form Request validates it, a Progress service applies the rule, and the answer is JSON
  that always carries the fresh `learner` summary next to whatever changed. `@/utils/http` puts that
  summary into the shared learner state (`$learner`), so the top bar and the menu badges update by
  themselves. When the page shows numbers that the server derives (how many words are left, what is
  unlocked), it asks for its props again with a partial reload (`router.reload({ only: [...] })`)
  instead of recomputing them in the browser.
- **A save that fails** — `send()` retries a network failure, then tells the learner with a toast;
  a server error gets the same toast at once (a 422 is left to the caller). A session that saves
  several things (a lesson step, a test, a speaking round, a verb drill) queues them with
  `saveQueue()` and ends in `SessionSave`: the result shows once everything is saved, otherwise
  "could not be saved — try again" sends what is missing, never twice what already arrived.
- **The back button** — Inertia brings a page back with the props it had when it was left.
  `app.ts` asks the server for them again (`router.reload()`) whenever a page comes back from the
  history or from the browser's cache, so it shows the progress made since; the component, its
  scroll position and what is being typed stay.

## 4. Shared props (every page)

```ts
// resources/js/types/models.ts — exported from '@/types'
export type LevelId = 'a1' | 'a2' | 'b1' | 'b2';
export type ThemeId = 'dark' | 'slate' | 'light' | 'sepia';

export interface Learner {
    xp: number; // total XP
    xpToday: number;
    goal: number; // daily XP goal
    streak: number; // days in a row, 0 when broken
    level: { id: LevelId; name: string; color: string } | null; // the level being worked on
    due: { words: number; verbs: number; mistakes: number }; // menu badges
}

export interface Settings {
    theme: ThemeId;
    rate: number; // speech rate
    voice: string | null; // name of the speech-synthesis voice
    sound: boolean; // sound effects
}

// sharedPageProps (resources/js/types/global.d.ts), sent by HandleInertiaRequests:
//   name, locale ('en' | 'ro'), locales, auth.user, settings: Settings, learner: Learner
//   (settings and learner are null on the login page)
```

Flash messages are not props: a controller calls `Inertia::flash('success' | 'error', $text)` and the
page reads `$page.flash`. `app.blade.php` writes `data-theme` and `lang` on `<html>` from the same
values, so the first paint is already in the right theme.

## 5. Route map

Names and URLs are fixed; pages link to each other with the Wayfinder helpers of these names.

| File | Name | URL | Page component |
| --- | --- | --- | --- |
| `web.php` | `login`, `login.store`, `logout` | `GET`, `POST /login`; `POST /logout` | `auth/Login` |
| | `locale.update` | `PUT /locale` — `{locale: en\|ro}` (also for guests) | — |
| `web/home.php` | `home` | `GET /` | `Home` |
| | `learn` | `GET /learn` | `Learn` |
| | `progress` | `GET /progress` | `Progress` |
| | `grammar` | `GET /grammar/{unit?}` | `grammar/Index`, `grammar/Show` |
| | `videos` | `GET /videos` | `Videos` |
| `web/course.php` | `units.show` | `GET /units/{unit}` | `units/Show` |
| | `units.step` | `GET /units/{unit}/{step}` — `vocab`, `grammar`, `dialogue`, `practice`, `reading`, `test` | `units/Step` (both: `units/Unavailable` when the unit is not open) |
| | `exams.show` | `GET /exams/{level}` | `exams/Show` |
| | `placement` | `GET /placement` | `Placement` |
| | `mistakes` | `GET /mistakes` | `Mistakes` |
| `web/words.php` | `flashcards` | `GET /flashcards` | `words/Flashcards` |
| | `words` | `GET /words/{tab?}` — `learned`, `learning`, `new` | `words/Index` |
| | `words.sort` | `GET /words/sort/{level}` | `words/Sort` |
| | `units.bank` | `GET /units/{unit}/bank` | `words/Bank` |
| `web/verbs.php` | `verbs` | `GET /words/verbs` | `verbs/Index` |
| `web/games.php` | `games` | `GET /games/{game?}` — `memory`, `sprint`, `spell` | `games/Index`, `games/Memory` … |
| `web/settings.php` | `settings` | `GET /settings` | `Settings` |
| | `account.profile`, `account.password` | `PUT /settings/account/profile`, `…/password` | — (back to `Settings`) |
| `web/speaking.php` | `speaking` | `GET /speaking/{mode?}/{arg?}?level=` — `talk`, `role`, `repeat`, `say`, `answer`, `pairs`, `sprint`; `arg`: a scenario, a unit or a topic; `level`: the hub's level picker (else the level worked on) | `speaking/Index`, `Talk`, `Role`, `Sentences`, `Answer`, `Pairs`, `Sprint` |
| `web/cambridge.php` | `cambridge` | `GET /cambridge/{exam?}/{test?}/{paper?}` — `pet`, `fce`; `pet-test1` …; `reading`, `listening`, `writing`, `speaking` (another paper goes back to the exam) | `cambridge/Index`, `Exam`, `Paper`, `Writing`, `Speaking`, `Unavailable` |
| `web/api.php` | `api.*` | `/api/…` (JSON) | — |

Route parameters with a fixed set of values are constrained (`whereIn`, or a pattern for unit ids
and Cambridge ids), so the order in which the files are loaded never matters.

## 6. Front-end building blocks

| Import | What it gives |
| --- | --- |
| `@/api/progress` | one typed function per progress action (`completeStep`, `recordUnitTest`, `gradeWord`, `addXp` …) |
| `@/utils/http` | `send<T>(route, data?)` — JSON request with CSRF, sent one after another, retried on a network error, a toast when it is not saved; updates the learner state from the answer |
| `@/utils/saving`, `general/SessionSave` | `saveQueue(requests?)` (`add`, `flush`) and the end of a session that shows its result once saved, or `SaveAlert` with "🔁 Try again" |
| `@/utils/learner` | the learner state shared by the whole app: `$learner` and `$settings` in every component, `learnerState` / `setLearner()` / `setSettings()` elsewhere |
| `@/i18n` | `$t('English text', { name })` in templates and components, `trans()` in plain modules |
| `@/utils/toast` | `toast(message, kind?)` |
| `@/utils/text` | `md`, `gap`, `norm`, `checkText` … (the answer checking of the original app) |
| `@/utils/speech`, `recognition`, `recording`, `sound`, `confetti` | speaking, listening, recording, sound effects |
| `@/components/general/*`, `layout/PageHeader`, `modals/AppModal` | `ProgressRing`, `ProgressBar`, `ChipTag`, `LevelChip`, `EmptyState` … |
| `@/components/exercise/*` | `ExerciseSession` (a run of exercises with feedback), `ResultCard` |
| `@/components/speech/*`, `@/components/content/*` | `SpeakButton`, `Recorder`, `WordPicture`, `GrammarLesson`, `VideoCard` |

## 7. Conventions

- **PHP**: Controllers → Services → Models; Form Requests for every write; Enums instead of magic
  strings; Resources for what leaves the server. Pint (Laravel preset) and Larastan level 7 must pass.
- **Vue**: template first, then `<script lang="ts">` with `defineComponent` (Options API): `name`,
  `components`, `props` typed with `PropType`, `data`, `computed`, `methods`; a one-line comment above
  `export default` says what the component is. Types come from `@/types`; no `any`. Whatever a
  component starts (timers, listeners, speech, the microphone) it stops in `beforeUnmount`.
  A page file holds the page; anything reused or longer than a screen goes to `components/modules/<area>/`.
- **CSS**: the class names of the ported style sheet are the design system (`card`, `btn primary`,
  `pill`, `chip-tag`, `tabs`, `word-list` …). New styles use the same variables (`--bg`, `--panel`,
  `--primary`, `--muted` …) so they work in all four themes. Tailwind utilities are available for
  layout tweaks.
- **Texts**: every interface text goes through `$t()` / `trans()` with the English text as the key;
  the Romanian text goes into `resources/js/i18n/ro/<area>.json`, words of several areas into
  `general.json` (texts the backend also uses go into `lang/ro.json`). Plurals: `"1 word|:count words"`
  with `{ count }`. A key lives in one dictionary only. When Romanian says one English text in two
  ways, the second use gets a context that English never shows: `$t('Videos [shortcut]')`. Keys kept
  in TypeScript data (`STEP_INFO`, `THEMES` …) are checked by `i18n/catalogs.test.ts`. Content from
  `resources/content` is never translated.
- **Saving**: nothing on a page pretends a change was saved before the server said so: a session
  result (and its XP) waits in `SessionSave`, a row or a modal changes with the answer.
- **Tests**: every rule has a unit test, every route a feature test (guest redirected, page props,
  validation, the effect on the database). `composer test` runs Pint, Larastan and Pest;
  `npm run test` runs the front-end unit tests.
- **Comments** say why, in plain English, like the rest of the code base.

## 8. Adding a page (recipe)

1. Route in `routes/web/<area>.php` (name it; constrain parameters).
2. Controller method: read what the page needs from the Content / Progress services, return
   `Inertia::render('<area>/<Page>', [...])`. Add a Resource when the shape is reused.
3. `resources/js/pages/<area>/<Page>.vue`, typed props; link with Wayfinder helpers; texts with `$t()`.
4. Romanian texts in `resources/js/i18n/ro/<area>.json`.
5. If the page writes progress: a function in `@/api/progress`, a route in `routes/web/api.php`,
   a Form Request, and the rule in a Progress service — with its unit test.
6. Feature test in `tests/Feature/`.
