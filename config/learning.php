<?php

return [

    /*
    | The single account of the app (the learner). The database seeder creates it from .env;
    | keep the real values in .env only, never in git. An empty password makes the seeder
    | create a random one and print it once.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
        'name' => env('ADMIN_NAME', 'Admin'),
    ],

    /*
    | The course: units, word banks, tests, speaking, Cambridge mocks and irregular verbs,
    | as JSON files (resources/content/a1/unit01.json, resources/content/verbs.json, …).
    | Their format is described in docs/content/.
    */
    'content_path' => resource_path('content'),

    /*
    | What is derived from the content (the word index, the course outline, …) is kept in the
    | cache, so 4 MB of JSON are not read on every request. The cache key carries a fingerprint
    | of the files, so editing a content file is picked up by itself; "php artisan cache:clear"
    | (also run by "optimize:clear") empties it. Null = the default cache store.
    */
    'content_cache' => [
        'store' => env('CONTENT_CACHE_STORE'),
        'ttl' => 60 * 60 * 24 * 7,
    ],

    /*
    | Interface languages, the first one is the default. The Romanian texts live in
    | lang/ro.json (shared with the front end) and resources/js/i18n/ro/*.json.
    */
    'locales' => ['en', 'ro'],

    /*
    | The numbers of the rules (ported from the original app, keep them in sync with
    | docs/backend.md). Scores are fractions: 0.8 = 80%.
    */
    'rules' => [
        // A unit test or a level exam is passed from 80%.
        'pass' => 0.8,
        // A level of the placement test counts as known from 75%.
        'placement_pass' => 0.75,
        // A unit with less than 60% of its questions right in a failed level exam goes back to review.
        'weak_unit' => 0.6,
        // Questions taken from each unit for a level exam ("match" exercises are left out).
        'exam_items_per_unit' => 3,
        // Share of the unit progress the five lesson steps are worth (the test gives the rest).
        'unit_steps_share' => 0.8,
        // Share of the level progress the units are worth (the exam gives the rest).
        'level_units_share' => 0.9,

        // Spaced repetition: days until the next review, by box. A word (or verb) is learned from box 3.
        'intervals' => [0, 1, 3, 7, 14, 30, 60],
        'learned_box' => 3,

        // The "my mistakes" list keeps the latest 200 questions.
        'mistakes_limit' => 200,

        // Words handed out per quick-sort batch ("learn the next 20 words").
        'word_batch' => 20,

        // Games use the words met so far; below this many, the first units of the current level.
        'known_words_min' => 8,
        'known_words_units' => 3,

        // Cambridge mocks: a safe score in a mock, and the "close" one.
        'cambridge_target' => 0.7,
        'cambridge_close' => 0.6,

        // Daily XP goal: the default and the choices of the settings page.
        'goal' => 50,
        'goals' => [20, 50, 100, 150],

        // Speech rate of the settings page (the original slider goes from 0.6 to 1.2).
        'rate' => ['default' => 0.9, 'min' => 0.5, 'max' => 1.5],
    ],

    /*
    | XP given by the rules. "activity_max" caps the generic "add XP" call that the pages
    | use for activities whose amount they compute (games, sort batches, verb drills).
    */
    'xp' => [
        'step' => 10,
        'practice_step' => 0,
        'unit_test_first' => 30,
        'unit_test_again' => 10,
        'exam_first' => 150,
        'exam_again' => 20,
        'placement' => 20,
        'cambridge_marked' => 40,
        'cambridge_rated' => 20,
        'spoken' => [2, 3],
        'conversation_first' => 15,
        'conversation_again' => 5,
        'activity_max' => 100,
    ],

];
