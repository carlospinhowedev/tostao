<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="/" class="mb-6 flex items-center gap-3 text-2xl font-semibold tracking-tight">
                <x-application-logo class="h-10 w-10" />
                {{ config('app.name') }}
            </a>
            <div class="card w-full max-w-md">
                {{ $slot }}
            </div>
            <p class="mt-6 max-w-md text-center text-xs text-muted">{{ __('Household finances for people who share a home. Self-hosted: your data stays on your machine.') }}</p>
        </div>
    </body>
</html>
