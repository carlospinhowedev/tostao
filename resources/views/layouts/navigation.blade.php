@php
    $links = [
        ['dashboard', __('Dashboard'), 'dashboard'],
        ['transactions.index', __('Transactions'), 'transactions.*'],
        ['imports.create', __('Import'), 'imports.*'],
        ['settle.index', __('Settle up'), 'settle.*'],
    ];
    $more = [
        ['accounts.index', __('Accounts'), 'accounts.*'],
        ['categories.index', __('Categories and budgets'), 'categories.*'],
        ['rules.index', __('Rules'), 'rules.*'],
        ['recurring.index', __('Recurring'), 'recurring.*'],
        ['members.index', __('Members'), 'members.*'],
    ];
@endphp
<nav x-data="{ open: false }" class="border-b border-line bg-surface">
    <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="mr-3 flex items-center gap-2 font-semibold tracking-tight">
                <x-application-logo class="h-7 w-7" />
                <span>{{ config('app.name') }}</span>
            </a>
            <div class="hidden items-center gap-1 md:flex">
                @foreach ($links as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'nav-link-active' : '' }}">{{ $label }}</a>
                @endforeach
                <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false">
                    <button type="button" class="nav-link inline-flex items-center gap-1 {{ collect($more)->contains(fn ($m) => request()->routeIs($m[2])) ? 'nav-link-active' : '' }}" @click="menu = !menu" :aria-expanded="menu">
                        {{ __('More') }}
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="menu" x-cloak x-transition.opacity class="absolute left-0 z-20 mt-1 w-56 rounded-xl border border-line bg-surface p-1 shadow-lg">
                        @foreach ($more as [$route, $label, $pattern])
                            <a href="{{ route($route) }}" class="nav-link block {{ request()->routeIs($pattern) ? 'nav-link-active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('locale') }}" class="hidden sm:block">
                @csrf
                <label class="sr-only" for="locale">{{ __('Language') }}</label>
                <select id="locale" name="locale" class="input !w-auto !py-1 text-xs" onchange="this.form.submit()">
                    @foreach (config('tostao.locales') as $code => $name)
                        <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('profile.edit') }}" class="nav-link hidden items-center gap-2 sm:flex">
                <span class="dot" style="background: {{ Auth::user()->color }}"></span>{{ Auth::user()->name }}
            </a>
            <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                @csrf
                <button class="nav-link">{{ __('Log out') }}</button>
            </form>
            <button type="button" class="btn btn-sm md:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('Menu') }}">☰</button>
        </div>
    </div>

    <div x-show="open" x-cloak class="border-t border-line px-4 py-3 md:hidden">
        <div class="grid gap-1">
            @foreach (array_merge($links, $more) as [$route, $label, $pattern])
                <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'nav-link-active' : '' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('profile.edit') }}" class="nav-link">{{ __('Profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link w-full text-left">{{ __('Log out') }}</button></form>
        </div>
    </div>
</nav>
