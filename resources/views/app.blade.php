<!DOCTYPE html>
{{-- Theme and language come from the shared props, so the first paint is already right (the login page has no settings: dark). --}}
<html lang="{{ $page['props']['locale'] ?? str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ data_get($page, 'props.settings.theme') ?? 'dark' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'English Levels') }} · A1 → B2</title>
        </x-inertia::head>
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
