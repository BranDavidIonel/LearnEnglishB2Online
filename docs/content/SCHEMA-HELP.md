# Help content (`resources/content/help.json`)

One file for the help page (`/help`): the pronunciation signs explained for a Romanian speaker,
a daily plan and advice for outside the app. Like the lessons, everything the learner reads here
is **Romanian** (fields ending in `Ro`) and is never translated; `**bold**` is the only markup.
The page is put together by `App\Services\Help\HelpPage`.

```jsonc
{
  "sounds": {
    "introRo": "What the signs under a word are and how to use this page.",
    "groups": [                      // in the order the page shows them
      {
        "id": "short",               // unique: marks | short | long | diphthongs | same | different
        "titleRo": "Vocale scurte",
        "noteRo": "One line about the whole group.",          // OPTIONAL
        "sounds": [
          {
            "symbol": "æ",           // the sign as the transcriptions write it; several signs that
                                     // read alike share a row, separated by spaces: "p t k"
            "likeRo": "între „a” și „e”",                       // what it sounds like, in a few words
            "textRo": "How to make the sound, the usual Romanian mistake, a pair of words it tells apart.", // OPTIONAL
            "examples": ["cat", "bag", "black"]                // 0–3 headwords of the course
          }
        ]
      }
    ]
  },

  "plan": {
    "introRo": "How much time a day and why the order matters.",
    "daily": [                       // what to do every day, in order
      { "emoji": "🗂️", "titleRo": "…", "textRo": "…", "minutes": 10, "link": "flashcards" }
    ],
    "shortDayRo": "What to keep on a day with no time.",
    "weekly": [ { "emoji": "✅", "titleRo": "…", "textRo": "…", "link": "learn" } ],
    "milestones": [ { "emoji": "🏁", "titleRo": "…", "textRo": "…", "link": "cambridge" } ]
  },

  "tips": [                          // what helps besides the app
    { "emoji": "🎧", "titleRo": "…", "textRo": "…" }
  ]
}
```

## Rules

- **Every sign is explained.** Every character that a transcription of the course uses (unit
  words, word banks, irregular verbs, glossary) must appear in some `symbol`, the helper signs
  included (`/ /`, `ˈ`, `ˌ`, `.`, `ː`, `,`, `–`). A new sign in the course needs a row here.
- **Examples are course words.** An example is only named: the page takes its transcription and
  meaning from the word's own entry (a course word, else a glossary word), so they are written
  once. The transcription of an example must contain the `symbol` of its row (rows with several
  signs, or with `…`, are not checked). Choose words where the sign stands on its own — for `e`,
  not a word with `eɪ` — because the page shows every place of the sign in bold.
- **`link`** is the part of the app a line of the plan opens: `flashcards`, `words`, `learn`,
  `speaking`, `mistakes`, `cambridge`, `grammar` or `progress`. Every line of the plan has one.
- **`minutes`** (daily steps only) are whole numbers; the page adds them up.
- The numbers the plan mentions in its texts (20 words a batch, 80% to pass, 70% in a mock) are
  the rules of `config/learning.php`: change both together. The page itself shows the learner's
  vocabulary towards B1 and B2 and the days left at one batch of new words a day.

`tests/Feature/Help/HelpPageTest.php` checks the first three rules; `content:validate` does not
read this file.
