# English Levels · A1 → B2

A single-user web app for learning English from A1 to B2: lessons with words, grammar explained in Romanian, exercises and tests, speaking practice, flashcards and Cambridge mock exams. It is the Laravel version of a local Node.js app; the course content lives in `resources/content/` and its format is described in `docs/content/`. Work in progress.

Built with Laravel 13, Inertia 3, Vue 3 + TypeScript, Tailwind CSS 4 and Vite Plus.

## Run it locally

You need PHP 8.4 or newer, Composer and Node.js. No database server is needed: local development uses SQLite.

```bash
composer setup        # install, .env, key, SQLite database, migrations, front-end build
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` (an empty password makes the seeder print a random one), then:

```bash
php artisan db:seed   # creates (or updates) the account you log in with
composer dev          # http://127.0.0.1:8000
```

Checks: `composer test` (Pint, Larastan, Pest) and `composer ci:check` (also the front-end lint and types).
