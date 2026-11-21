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
    | Interface languages, the first one is the default. The Romanian texts live in
    | lang/ro.json (shared with the front end) and resources/js/i18n/ro/*.json.
    */
    'locales' => ['en', 'ro'],

];
