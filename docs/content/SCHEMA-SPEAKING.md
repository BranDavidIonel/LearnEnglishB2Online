# Speaking practice — content schema

One file per level: `data/<level>/speaking.json` (`a1`, `a2`, `b1`, `b2`). Read `tools/SCHEMA.md`
first for the learner profile and the quality bar — the same rules apply (Romanian with
diacritics for hints and translations; natural, correct, modern spoken English).

The learner will SAY these things aloud into a microphone and the app compares what it hears
with the model answers. So everything here must be **spoken** English: short, natural, the kind
of thing people really say in a call, a meeting, a shop, a hotel. No written-style sentences.

Validate with `node tools/validate-speaking.js <level>` until it prints `0 error(s)`.

```jsonc
{
  "level": "A1",

  // ---------- 1. Conversations (role-play) ----------
  // Exactly 8, ordered easy → hard. The app plays the partner (text-to-speech); the learner
  // answers aloud. About 5 of the 8 must be work situations (client, colleague, manager,
  // interviewer, support call…), the rest travel and everyday life.
  "scenarios": [
    {
      "id": "a1-s01",                       // "<level>-s<2 digits>"
      "emoji": "🤝",
      "title": "Meeting a new client",
      "titleRo": "Întâlnirea cu un client nou",
      "youRo": "Ești Andrei, programator freelance. Te întâlnești pentru prima dată cu un client, la o cafenea.",
                                            // who the learner is + the situation, 1–2 sentences, Romanian
      "partner": "Client",                  // short label of the other person (shown above their lines)
      "turns": [                            // 6–8 turns. Each turn = the partner speaks, then the learner answers.
        {
          "say": "Hello! Are you Andrei?",            // what the partner says (level-appropriate, 3–20 words)
          "sayRo": "Bună! Tu ești Andrei?",
          "hintRo": "Confirmă că ești tu și spune că îți pare bine de cunoștință.",
                                            // WHAT to answer, as an idea in Romanian (not a word-for-word translation)
          "answers": [                      // 2–4 natural model answers of this level, 2–14 words each
            "Yes, I am. Nice to meet you.",
            "Yes, I'm Andrei. Nice to meet you!"
          ],
          "keywords": ["yes", "nice to meet you"]
                                            // 1–3 lowercase words/phrases that show the answer is right.
                                            // EVERY keyword must literally appear in EVERY model answer.
        }
      ],
      "closing": "Great! Let's talk about your project."   // OPTIONAL: partner's last line after the final turn
    }
  ],

  // ---------- 2. Phrase bank ("repeat after me" / "say it in English") ----------
  // Exactly 6 topics × exactly 10 phrases. Ready-made things the learner will really say.
  // 3–12 words each, level-appropriate, every "en" unique in the file.
  // At least 4 of the 6 topics about work (calls, meetings, emails read aloud, explaining a problem,
  // prices and deadlines, small talk with clients…), the others travel / everyday life.
  "phrases": [
    {
      "topic": "Starting a call",
      "topicRo": "Începutul unui apel",
      "emoji": "📞",
      "items": [
        { "en": "Can you hear me?", "ro": "Mă auzi?" }
      ]
    }
  ],

  // ---------- 3. Open questions ("answer the question") ----------
  // Exactly 12 questions about the learner himself, like the interview part of a speaking exam
  // or small talk with a client. Ordered easy → hard. The answer is free; the app shows the sample.
  "questions": [
    {
      "q": "What do you do?",
      "qRo": "Cu ce te ocupi?",
      "starters": ["I'm a…", "I work as…"],           // 2–3 ways to begin the answer
      "sample": "I'm a freelance developer. I build CRM systems for small companies."
                                                      // a good answer at this level: A1 8–20 words, A2 15–30, B1 25–45, B2 40–70
    }
  ]
}
```

## Rules

- Stay inside the level: use only the grammar and vocabulary the level's units teach (plus lower
  levels). A1 answers are very short and simple; B2 answers use real professional language.
- The learner's character is **Andrei, a freelance developer from Cluj** who builds CRM / ERP / web
  apps for small companies (same as in the units).
- Avoid proper names, numbers, prices, dates, abbreviations and rare words in the things the learner
  must SAY (`answers`, phrase `en`): speech recognition gets them wrong. "CRM" and common tech
  words (email, website, app, bug, deadline) are fine. Names are fine in what the partner says.
- Model answers must be genuinely different ways of saying the same idea, not one answer with a
  word swapped — but all of them must contain every keyword.
- `keywords` should be the meaning-carrying part ("on friday", "send", "too expensive"), not filler.
- Scenarios must feel like one real conversation with a beginning, a middle and an end.
- Nothing may be copied from the level's unit dialogues — these are new situations.
