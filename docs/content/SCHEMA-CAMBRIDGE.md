# Cambridge mock exams — content schema

Original practice tests that follow the FORMAT of the Cambridge English exams
(never copy real exam material — write everything yourself). Read `tools/SCHEMA.md` first for
the learner profile and the quality bar; the same rules apply (Romanian for instructions and
explanations, with diacritics; natural, correct English).

Folder per exam: `data/cambridge/<exam>/` where `<exam>` is `pet` (B1 Preliminary) or `fce` (B2 First):

- `exam.json` — information about the exam
- `test1.json`, `test2.json` — two complete mock tests

Validate with `node tools/validate-cambridge.js <exam>` until it prints `0 error(s)`.

## exam.json

```jsonc
{
  "id": "pet",
  "name": "B1 Preliminary (PET)",
  "level": "B1",
  "aboutRo": "Markdown (**bold**, newlines). 120–200 words in Romanian: what the exam is, the papers and their length, how it is scored (Cambridge English Scale, pass mark), what the certificate is good for, practical tips.",
  "papersRo": [                       // one line per paper of the REAL exam
    { "name": "Reading", "info": "45 min · 6 părți · 32 de întrebări" }
  ]
}
```

## testN.json

```jsonc
{
  "id": "pet-test1",                 // "<exam>-test<N>"
  "title": "Simulare 1",
  "reading":   { "minutes": 45, "parts": [ PART, … ] },      // for FCE this is "Reading and Use of English"
  "listening": { "minutes": 30, "parts": [ PART, … ] },
  "writing":   { "minutes": 45, "tasks": [ WRITING_TASK, … ] },
  "speaking":  { "minutes": 12, "tasks": [ SPEAKING_TASK, … ] }
}
```

### PART (reading and listening)

```jsonc
{
  "title": "Part 3",
  "instructionsRo": "Citește textul și alege varianta corectă pentru fiecare întrebare.",
  "text": "OPTIONAL shared passage (markdown: **bold**, blank line between paragraphs). For cloze tasks write the gaps inside the text as **(1)** ___ , **(2)** ___ …  For matching tasks put the lettered texts here: **A** …",
  "audio": [                          // LISTENING ONLY, optional: one script shared by all questions of the part
    { "s": "Interviewer", "en": "Welcome to the show, Maria." },
    { "s": "Maria", "en": "Thanks for having me." }
  ],
  "questions": [ QUESTION, … ]
}
```

The app reads `audio` aloud with text-to-speech (a different voice per speaker), so scripts must
be plain spoken English: no stage directions, no symbols, numbers written the way they are said
when that matters. Use at most 2 speakers per script ("s" is the speaker label, e.g. "Man",
"Woman", "Tom"). Monologue = one speaker.

### QUESTION

```jsonc
// multiple choice — 3 to 8 options, shown in the given order (use this for A/B/C, A–H matching, etc.)
{
  "type": "mc",
  "q": "What does Anna want Tom to do?",          // for cloze gaps simply "(1)"
  "options": ["call the client", "send the invoice", "book a room"],
  "answer": 1,
  "explainRo": "De ce e corect (și, pe scurt, de ce celelalte sunt greșite).",
  "text": "OPTIONAL short text only for this question (a sign, a note, an email)",
  "audio": [ { "s": "Woman", "en": "…" } ]        // LISTENING: optional script only for this question
}
// typed answer — open cloze, word formation, key-word transformation, note completion
{
  "type": "fill",
  "q": "**(27)** ___",                             // must contain exactly one ___ ; for word formation: "**(17)** ___ (SUCCESS)"
                                                   // for transformations: "I last saw him years ago. (FOR)\nI ___ years."
  "answers": ["haven't seen him for", "have not seen him for"],   // ALL accepted answers
  "explainRo": "…"
}
```

Every listening question must have a script: either its own `audio` or the part's `audio`.
Every question needs `explainRo`. Exactly one correct option per `mc`; vary the position of the
correct answer.

### WRITING_TASK

```jsonc
{
  "title": "Part 1 — Email",
  "task": "The exam question in English (markdown). Include the input text (e.g. the email to answer, with the notes).",
  "taskRo": "Ce ți se cere, pe scurt, în română.",
  "minWords": 100, "maxWords": 130,
  "checklistRo": ["Am răspuns la toate cele 4 puncte din notițe", "Am folosit o formulă de salut și de încheiere", "…"],  // 5–7 items, concrete, checkable
  "model": "A model answer in English, at the level a good candidate would write (within the word limits).",
  "usefulPhrases": [ { "en": "I'm writing to let you know that…", "ro": "Îți scriu ca să te anunț că…" } ]   // 5–8
}
```

### SPEAKING_TASK

```jsonc
{
  "title": "Part 1 — Interview",
  "infoRo": "Ce se întâmplă în această parte la examenul real și cum ești evaluat (2–3 propoziții).",
  "prompts": ["Where do you live?", "What do you do?"],      // what the examiner says / asks (3–6)
  "wiki": "Open-plan office",        // OPTIONAL: exact English Wikipedia article title whose main image fits a "describe the photo" task
  "sceneRo": "OPTIONAL: for photo tasks, describe in Romanian what the imaginary photo shows (used if the picture cannot be loaded)",
  "usefulPhrases": [ { "en": "In the background, I can see…", "ro": "În fundal văd…" } ],   // 5–8
  "model": "A model answer in English (what a good candidate would say), 80–160 words."
}
```

## Required structure

### pet (B1 Preliminary) — B1 difficulty

- **reading** (45 min, 32 questions)
  - Part 1: 5 short real-world texts (signs, messages, emails — put each in the question's `text`), 3-option mc
  - Part 2: 5 people (each question describes a person and what they need) matched to 8 short texts **A–H** in the part `text` (mc with 8 options "A"…"H")
  - Part 3: one longer text (≈ 350–420 words), 5 four-option mc
  - Part 4: one text (≈ 300 words) with 5 sentences removed, written as **(16)** ___ …; mc with 8 options (the removed sentences + 3 extra)
  - Part 5: short text with 6 gaps, four-option mc cloze (vocabulary)
  - Part 6: short text with 6 gaps, open cloze (`fill`, one word each; grammar words)
- **listening** (30 min, 25 questions)
  - Part 1: 7 short recordings (each question has its own `audio`), 3-option mc
  - Part 2: 6 short dialogues (own `audio` each), 3-option mc about opinion/attitude/gist
  - Part 3: one monologue (part `audio`, ≈ 250–300 words), 6 `fill` questions completing notes (1–2 words or a number heard in the recording; list every acceptable spelling)
  - Part 4: one interview (part `audio`, ≈ 450–550 words, 2 speakers), 6 three-option mc
- **writing** (45 min): task 1 = email (about 100 words, reply to an email with 4 notes); task 2 = article (about 100 words); task 3 = story (about 100 words, first sentence given). In the real exam the candidate does the email plus EITHER the article or the story — say so in `taskRo`.
- **speaking** (12 min): 4 tasks = Part 1 interview, Part 2 describe a photo, Part 3 discussion task (choose between options for a situation), Part 4 follow-up discussion

### fce (B2 First) — B2 difficulty

- **reading** = Reading and Use of English (75 min, 52 questions)
  - Part 1: text with 8 gaps, four-option mc cloze
  - Part 2: text with 8 gaps, open cloze (`fill`, one word)
  - Part 3: text with 8 gaps, word formation (`fill`; stem word in capitals in `q`)
  - Part 4: 6 key-word transformations (`fill`; 2–5 words including the key word; list all accepted answers)
  - Part 5: long text (≈ 550–650 words), 6 four-option mc
  - Part 6: gapped text (≈ 500 words) with 6 sentences removed; mc with 7 options
  - Part 7: 10 questions matched to 4–5 short texts **A–E** in the part `text` (mc with options "A"… )
- **listening** (40 min, 30 questions)
  - Part 1: 8 short extracts (own `audio`), 3-option mc
  - Part 2: one monologue (≈ 450–550 words), 10 `fill` sentence completions (1–3 words heard in the recording)
  - Part 3: 5 short monologues (own `audio`, different speaker each), each question = mc with the same 8 options (A–H): what each speaker says about the topic
  - Part 4: one interview (≈ 550–650 words, 2 speakers), 7 three-option mc
- **writing** (80 min): task 1 = essay (140–190 words, compulsory, with two given notes + "your own idea"); task 2 = article; task 3 = review or report; (in the real exam: essay + ONE of the others — say so in `taskRo`)
- **speaking** (14 min): 4 tasks = Part 1 interview, Part 2 long turn (compare two photos — describe both imaginary photos in `sceneRo`), Part 3 collaborative task, Part 4 discussion

Topics: mix the learner's worlds — work, technology, clients, travel, everyday life, hobbies,
health, environment, education — like the real exams (which are general English, not IT English).
The two tests of one exam must not repeat texts, topics or questions.
