<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-currency="{{ config('tostao.currency') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='15' fill='%230f766e'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' font-weight='700' text-anchor='middle' fill='white'%3ET%3C/text%3E%3C/svg%3E">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @include('layouts.navigation')

        <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
            @if (session('status'))
                <div class="flash mb-5" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash flash-error mb-5" role="alert">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @isset($header)
                <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                    {{ $header }}
                </header>
            @endisset

            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-xs text-muted sm:px-6">
            {{ config('app.name') }} · {{ __('Self-hosted and open source. Your data stays on your machine.') }}
        </footer>
    </body>
</html>
