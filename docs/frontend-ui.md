# Front end: style sheet, layout and generic components

The look of the original app is kept class for class. A page is written with the original class
names (`card`, `btn primary`, `tabs` …) and gets the original look in all four themes for free.
This guide lists that vocabulary, the components that draw the repeated pieces, and the shared
front-end services (`send()`, `saveQueue()`, `$learner`, `toast()`).

## 1. Style sheet

`resources/css/app.css` imports the partials of `resources/css/styles/`, in the order of the
original file (the order matters for the cascade):

| Partial | What it styles |
| --- | --- |
| `tokens.css` | colour variables and the four themes |
| `base.css` | elements (`body`, `h1`–`h3`, `a`, `p` …) and helpers (`muted`, `row` …) |
| `layout.css` | `#app`, sidebar `.side`, top bar `.top`, `#main`, phone menu |
| `generic.css` | cards, buttons, chips, bars, rings, empty state, toasts, modal, tabs, `grid2`, `check`, `search` |
| `dashboard.css` | hero, stats, road to B2, week chart, quick links |
| `learn.css` | levels, unit cards, badges, `settings` grid |
| `unit.css` | unit page: steps, word cards, grammar, dialogue, reading, exams, placement, mistakes |
| `exercises.css` | exercise session, options, gaps, order, match, feedback, result |
| `flashcards.css` | flashcards, word list, videos, games (memory, sprint, spelling) |
| `progress-chart.css` | the progress chart |
| `cambridge.css` | Cambridge readiness, papers, timer, questions, writing, stars |
| `menu.css` | menu bottom (settings, log out), language switch, theme button |
| `settings.css` | theme picker cards |
| `word-actions.css` | actions of the "my words" rows, `card notice` |
| `word-lookup.css` | tappable words (`tap-w`) and the word window (`lookup-…`) |
| `speaking.css` | level picker, microphone panel, said words, talk, pairs |
| `word-bank.css` | word bank rows, `words-card` |
| `verbs.css` | irregular verbs |
| `auth.css` | login page (`auth`, `field`, `input`) |

Rules for new styles: use the theme variables below (never a fixed colour for text or panels), put
them in the partial of their subject, and keep the class-name style of the original (short,
lowercase, `kebab-case`, state classes such as `on`, `done`, `now`, `locked`, `right`, `wrong`).

**Tailwind** is loaded without its preflight (the original styles sit on the browser defaults) and
the ported styles are in the `components` layer, so a Tailwind utility can adjust them
(`class="card mt-0"`). Use utilities only for small layout tweaks. Two utilities are switched off
because their names are original classes: `ring` (the progress ring) and `block` (`check block`).

### Theme variables

Themes are set by `data-theme` on `<html>` (`dark` default, `slate`, `light`, `sepia`); every value
below changes with it.

| Variable | Use |
| --- | --- |
| `--bg`, `--bg2` | page background; sidebar background |
| `--panel`, `--panel2` | cards; inputs and inner boxes |
| `--border` | borders |
| `--text`, `--muted` | text; secondary text |
| `--primary`, `--primary2` | accent (buttons, active items); accent text and hover borders |
| `--ok`, `--bad`, `--warn` | right / wrong / warning |
| `--up`, `--down`, `--flatc` | chart and said-word colours |
| `--shadow`, `--radius` | card shadow; card radius (16px) |
| `--c` | local colour of a component (a level colour): `:style="{ '--c': level.color }"`; defaults to `--primary` |

### Class vocabulary

Layout and text helpers:

- `muted`, `small`, `center` (text-align), `mt` (16px top margin), `hidden`
- `row` (flex, gap 10px, centred) + `center`, `between`, `wrap`, `gap-lg`; `grow` (takes the rest)
- `grid2` (two columns, one on phones; cards inside lose their margin)
- `fade-in` (appear animation)

Cards and blocks:

- `<div class="card">…</div>`; a link card: `<Link class="card">`; with a level colour on the left:
  `class="card words-card" :style="{ '--c': color }"`
- `card hero` (big gradient card on the home page, uses `--c`), `card notice` (accent border left),
  `card tips` (warning colours), `card instr` (instructions)
- `alert warn` (centred warning box)
- `goal` (left-bordered goal line), `hint-box`, `model` (model answer), `locked-note`

Buttons:

- `<button class="btn">` plain; `btn primary`, `btn success`, `btn danger`, `btn warn`,
  `btn danger-outline`; `btn big` (`<small>` inside shows a keyboard key), `btn start` (glow),
  `btn round` (54px circle), `btn rec` (recording, pulsing red), `btn audio playing`, `btn muted-btn`
- `<button class="icon-btn">🔊</button>` (36px transparent square)
- Links that look like buttons: `<Link class="btn primary" :href="…">`

Pills, chips, tabs:

- `pill` (top bar), `pill theme-btn`
- `chip-tag` + `ok`, `now`, `warn`, `bad`, `lvl` (filled with `--c`) → use `ChipTag` / `LevelChip`
- `tabs` > `tab` (+ `on`; `<small>` for a count) — `<div class="tabs"><Link class="tab on">…</Link></div>`
- `chart-ranges` > `range` (+ `on`): small segmented switch
- `chip` (+ `used`): word tiles of the "put in order" exercise

Progress: `bar` > `bar-fill` → `ProgressBar`; `ring` → `ProgressRing`; `result-ring` (+ `fail`, `--p`)
on result cards; `step-dots` > `step-dot` (+ `done`, `now`); `dots` > `dot` (+ `done`, `now`).

Lists:

- `word-list` > `word-row` (grid: emoji, `word-en`, `word-ro`, …, `word-box` > `i.on`, `word-acts`)
- `mist-list` > `mist-row`, `gram-list` > `gram-row` (with `gram-n`)
- `steps` > `step-row` (+ `done`, `extra`; `step-icon`, `step-check`)
- `units` > `unit-card` (+ `locked`, `exam`), `badges` > `badge` (+ `ok`), `stats` > `stat` (+ `link`)
- `quick` > `quick-item`, `game-grid` > `game`, `video-grid` > `video-item`, `paper-grid` > `paper`

Forms: `select` and `.search` are styled as they are; `check` (inline checkbox + label), `check block`
(one per line); `settings` (grid of labelled controls); `field` + `input` (+ `wrong`), `field-box`
(input with an `icon-btn` inside), `field-error` (login page); exercise inputs: `gap-input`
(+ `wide`), `type-input`, `write-area`, all with `right` / `wrong`.

Exercises, cards and the rest keep the class names of the original views (`ex`, `ex-type`, `ex-q`,
`opts` > `opt`, `feedback good|bad`, `result`, `fc-stage` > `fc` > `fc-face`, `vcard` > `vcard-in`,
`pic`, `dialogue` > `line` > `bubble`, `read`, `cq`, `timer` …): read the partial of the subject
and the matching original view in `public/js/views/` of the source app.

## 2. Layout and shell

Every page gets `AppLayout` (persistent) automatically; pages under `pages/auth/` get `AuthLayout`
(`resources/js/app.ts`). A page renders only its content (it lands in `<main id="main">`):

```vue
<template>
    <Head :title="$t('Words')" />
    <PageHeader :title="$t('📖 My words')" :sub="$t('…')" :back="home().url" />
    <div class="card">…</div>
</template>
```

`AppLayout` = `SideNav` (`aside.side`: `BrandMark`, the twelve menu items, Settings, Log out) +
`TopBar` (`header.top`: menu button, `LanguageSwitcher`, theme button, level / streak / XP pills) +
`ToastStack`. The active menu item follows the URL (`/units/…`, `/exams/…` → Learn; `/words/…` →
Words; …, see `SECTIONS` in `SideNav.vue`). Badges: Flashcards = `$learner.due.words`, Words =
`$learner.due.verbs`, My mistakes = `$learner.due.mistakes`.

On every new page (path change) the layout scrolls to the top, closes the phone menu and calls
`stopSpeak()`. On every page and partial reload it puts the shared props `learner` and `settings`
into the learner state. A page brought back by the back / forward buttons (or from the browser's
cache) asks for its props again (`router.reload()` in `app.ts`): it keeps its component and scroll
and gets the progress made since. A page that copies a prop into its data (the word rows, the
Cambridge drafts) watches the prop so the fresh value reaches the screen.

## 3. Shared state and services

**Learner state** (`@/utils/learner`): in templates and the Options API, `$learner`
(`xp`, `xpToday`, `goal`, `streak`, `level`, `due`) and `$settings` (`theme`, `rate`, `voice`,
`sound`); elsewhere `learnerState.learner` / `learnerState.settings`. Never write them directly:
`setLearner(learner)`, `setSettings(settings)` (also applies the theme). The layout and `send()`
keep them fresh, so pages usually only read them.

**`send()`** (`@/utils/http`) — used by `@/api/progress`, rarely by pages directly:

```ts
const answer = await send<GradeAnswer>(gradeWord(), { word: 'customer', grade: 2 });
```

- `route`: a Wayfinder helper result (`{ url, method }`) or a URL (method: `method` argument, else POST).
- JSON in and out, `X-XSRF-TOKEN` from Laravel's cookie; requests go out one after another.
- A network failure is retried after 0.5 s, 1 s and 2 s; then the toast "Your progress could not
  be saved…" and the promise rejects.
- `419` reloads the page, `401` goes to the login page; any other error rejects with
  `HttpError` (`status`, `message`, `errors` — the validation errors of a 422). A server error
  (any status but 401, 419 and 422) shows the same toast first; a 422 is for the caller to show.
  So a caller only decides what its page does when a save fails, never how to tell the learner.
- An answer with `learner` / `settings` updates the learner state by itself.

**`read()`** (`@/utils/http`) — a GET of the API that is not a save (the word window's
`lookupWord()`): it goes out at once instead of waiting behind the saves on their way, it is tried
once, and a failure never shows "could not be saved" (the caller shows its own state); 419 and 401
are handled as for `send()`.

**Saving a session** (`@/utils/saving` + `general/SessionSave`): a lesson step, a test, a speaking
round or a verb drill saves several things, during the session or at its end. They go into a
`saveQueue()` (`saveQueue([...requests])`, `add(request)` — a request is a function, so it can be
sent again); `SessionSave` at the end flushes the queue and shows its default slot (the result)
once everything is saved. When a request fails it shows `SaveAlert` ("Your result could not be
saved … — your answers are kept", `🔁 Try again`), which sends the failed request and the ones
after it, never twice what already reached the server. Pass `:keeps-answers="false"` where the
learner gave no answers (grammar, dialogue). Give `SessionSave` a new `:key` for every session.

```vue
<SessionSave v-if="finished" :key="run" :save="saves" @saved="celebrate">
    <ResultCard :score="score" :title="title" />
</SessionSave>
```

The word pages save each change at once and change a row or the word modal only with the
server's answer; the flashcards and the quick sort end the session on the first failed save
("Not everything was saved").

**`toast(message, kind?)`** (`@/utils/toast`): the original toast at the bottom centre, 2.6 s.
`kind` is `''` or `'warn'`. Flash messages of the controllers (`Inertia::flash('success' | 'error', …)`)
are shown the same way.

**Catalogues**: `@/utils/levels` (`LEVEL_INFO`, `LEVELS`, `levelInfo(id)`: `id`, `name`, `title`
(translation key), `color`) and `@/utils/themes` (`THEMES`, `themeInfo(id)`, `nextTheme(id)`:
`id`, `name` (translation key), `swatch`).

**i18n** (`@/i18n`): `$t('English text', { name })`, `trans()` in plain modules,
`intlLocale()` → `en-GB` / `ro-RO` for `toLocaleString()` and `Intl` formatters. A key may end in a
context in brackets (`'Videos [shortcut]'`) when Romanian needs a second text for the same English;
English never shows it.

## 4. Generic components

| Component | Props | Renders |
| --- | --- | --- |
| `general/ProgressRing` | `value` (0..1), `color?`, `label?` (default `NN%`), `size?` (64) | `div.ring` |
| `general/ProgressBar` | `value` (0..1), `color?`, `target?` (0..1, goal line) | `div.bar > div.bar-fill` |
| `general/ChipTag` | `kind?` (`ok`, `warn`, `bad`, `now`, `lvl`), `color?`; default slot | `span.chip-tag` |
| `general/LevelChip` | `level` (`LevelId`) | `ChipTag kind="lvl"` in the level colour |
| `general/EmptyState` | `icon` (emoji), `title`, `text?`; slot `actions` | `div.empty` |
| `general/VocabGoal` | `stats` (`{ learned, total }`), `level` (`{ name, color }`) | `div.to-goal`: "📚 Words for B1 · 340 / 2000" + bar |
| `general/ToastStack` | — (mounted by the layouts) | `div.toasts` |
| `general/SessionSave` | `save` (`SaveQueue`), `keepsAnswers?` (true); emits `saved`; default slot | the slot once saved, else `SaveAlert` |
| `general/SaveAlert` | `busy?`, `keepsAnswers?` (true); emits `retry` | `div.alert.warn`: "Your result could not be saved…" + 🔁 Try again |
| `layout/PageHeader` | `title`, `sub?`, `back?` (URL of "← Back") | `div.page-head` |
| `layout/BrandMark` | `href?` | `.brand` logo |
| `modals/AppModal` | `open`, `wide?`, `sheet?` (near the top, a bottom sheet on phones); emits `close`; default slot | `div.modal-back > div.modal` (✕, click outside, Esc closes the top one only; stops speech) |
| `content/TapText` | `text`, `markdown?`, `tag?` (`span`), `plain?` (not tappable yet) | the text with every English word tappable (`.tap` > `span.tap-w`) |
| `charts/WeekChart` | `days` (`{ date: 'YYYY-MM-DD', xp }[]`, last 7 days, oldest first), `goal` | `div.week` |

```vue
<ProgressRing :value="0.42" :color="level.color" :size="72" />
<ChipTag kind="ok">{{ $t('done') }}</ChipTag>
<EmptyState icon="🎉" :title="$t('All done')" :text="$t('…')">
    <template #actions><Link class="btn primary" :href="home().url">{{ $t('Home') }}</Link></template>
</EmptyState>
<AppModal :open="showHelp" wide @close="showHelp = false">…</AppModal>
```

## 5. Tappable English and the word window

Every English word the learner reads or hears in a lesson can be tapped: a small window opens
for it (`modules/words/WordLookup`) with its sound (🔊, 🐢), the meaning, the form it comes from
("alerts — plural of **alert**"), the three forms of an irregular verb, the course expressions
found in the sentence, the sentence itself, "🎙 Say it" (the microphone panel and the speaking
area's single-word check) and the learner's state with the actions of the word details.

- **Making text tappable**: `<TapText :text="line.en" />` (`markdown` for the lesson markdown,
  `tag="div"` for a block, `class` goes on the tag). For HTML you build yourself (an example with
  a verb form in bold), pass the safe HTML through `tapWords()` of `@/utils/tapWords` and give
  the container the class `tap`: `<div class="en tap" v-html="tapWords(mark(…))" />`.
- **The rule**: never where a tap gives an answer away — not in tests, exams, the placement
  test or a Cambridge paper being answered, not in an exercise before it is answered, not on a
  flashcard side that is not turned yet. Where the text becomes an answer later, use
  `:plain="!shown"` (or render the TapText only once it is shown).
- **One listener, one window**: `AppLayout` listens for clicks on `.tap-w` in the capture phase,
  so the container's own action (a bubble, a card that turns) never runs. It stops speech,
  reads the word and its sentence (`tapTarget()`: the sentence around the word inside the
  closest `.tap`, a `<br>` ends a sentence), opens the window (`openWord()` of
  `@/utils/wordLookup`) and gives the focus back to the word when it closes. A tap on a word
  inside the window opens that word. Words are not in the tab order (a lesson has hundreds);
  every line keeps its 🔊 button for the keyboard.
- **While the window is open**: `wordOpen()` is true; an automatic microphone (`MicPanel.start()`)
  waits, and a dialogue being played stops. When a word's state changed, "My words" and the
  word bank get their props again when the window closes.
- **Look**: a word reads as normal text; a pointer that can hover sees a light background, a
  press lights it on touch screens, and the tapped word stays lit while its window is open.
