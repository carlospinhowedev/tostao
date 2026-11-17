@php
    use Illuminate\Support\Carbon;
    $f = request()->query();
    $monthLabel = fn ($m) => Carbon::createFromFormat('!Y-m', $m)->locale(app()->getLocale())->translatedFormat('F Y');
    $hasFilter = collect(['month', 'owner', 'category', 'account', 'type', 'q'])->contains(fn ($k) => filled($f[$k] ?? null));
@endphp
<x-app-layout :title="__('Transactions')">
    <x-slot name="header">
        <h1 class="page-title">{{ __('Transactions') }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('transactions.export', $f) }}" class="btn">{{ __('Export CSV') }}</a>
            <a href="{{ route('imports.create') }}" class="btn">{{ __('Import') }}</a>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">{{ __('Add transaction') }}</a>
        </div>
    </x-slot>

    @if ($uncategorised > 0 && ($f['category'] ?? '') !== 'none')
        <p class="mb-3 text-sm"><a class="underline" href="{{ route('transactions.index', ['category' => 'none']) }}">{{ trans_choice(':count transaction has no category yet|:count transactions have no category yet', $uncategorised, ['count' => $uncategorised]) }}</a></p>
    @endif

    <form method="GET" class="card mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-field :label="__('Search')" for="q" class="sm:col-span-3 lg:col-span-2">
            <input id="q" name="q" value="{{ $f['q'] ?? '' }}" class="input" placeholder="{{ __('Description contains…') }}">
        </x-field>
        <x-field :label="__('Period')" for="month">
            <select id="month" name="month" class="input">
                <option value="">{{ __('All time') }}</option>
                @foreach ($months as $m)<option value="{{ $m }}" @selected(($f['month'] ?? '') === $m)>{{ $monthLabel($m) }}</option>@endforeach
            </select>
        </x-field>
        <x-field :label="__('Who')" for="owner">
            <select id="owner" name="owner" class="input"><x-owner-options :members="$members" :selected="$f['owner'] ?? ''" :blank="__('Everyone')" /></select>
        </x-field>
        <x-field :label="__('Category')" for="category">
            <select id="category" name="category" class="input">
                <option value="">{{ __('All') }}</option>
                <option value="none" @selected(($f['category'] ?? '') === 'none')>{{ __('Uncategorised') }}</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) ($f['category'] ?? '') === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </x-field>
        <x-field :label="__('Type')" for="type">
            <select id="type" name="type" class="input">
                <option value="">{{ __('All') }}</option>
                <option value="expense" @selected(($f['type'] ?? '') === 'expense')>{{ __('Expenses') }}</option>
                <option value="income" @selected(($f['type'] ?? '') === 'income')>{{ __('Income') }}</option>
            </select>
        </x-field>
        <div class="flex items-end gap-2 sm:col-span-3 lg:col-span-6">
            <button class="btn btn-primary">{{ __('Filter') }}</button>
            @if ($hasFilter)<a href="{{ route('transactions.index') }}" class="btn">{{ __('Clear') }}</a>@endif
            <span class="muted ml-auto">{{ trans_choice(':count transaction|:count transactions', $transactions->total(), ['count' => $transactions->total()]) }} · {{ __('Net') }} <x-money :cents="$sum" :signed="true" class="font-medium" /></span>
        </div>
    </form>

    <div x-data="{ ids: [], all: false, page: @js($transactions->pluck('id')->map(fn ($i) => (string) $i)->all()), toggleAll() { this.ids = this.all ? [...this.page] : []; } }">
        @error('bulk')<p class="mb-3 text-sm text-bad">{{ $message }}</p>@enderror
        @error('ids')<p class="mb-3 text-sm text-bad">{{ $message }}</p>@enderror

        <form method="POST" action="{{ route('transactions.bulk') }}" :class="{ hidden: ids.length === 0 }"
              class="card sticky top-2 z-10 mb-3 flex hidden flex-wrap items-end gap-3 shadow-lg">
            @csrf
            <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <span class="text-sm font-medium"><span x-text="ids.length"></span> {{ __('selected') }}</span>
            <x-field :label="__('Category')" for="bulk-category">
                <select id="bulk-category" name="category" class="input !py-1.5">
                    <option value="">{{ __('Leave as is') }}</option>
                    <option value="none">{{ __('Uncategorised') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field :label="__('Who')" for="bulk-owner">
                <select id="bulk-owner" name="owner" class="input !py-1.5"><x-owner-options :members="$members" selected="" :blank="__('Leave as is')" /></select>
            </x-field>
            <button class="btn btn-primary">{{ __('Apply to selected') }}</button>
        </form>

        <div class="card !p-0 overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" x-model="all" @change="toggleAll()" aria-label="{{ __('Select all on this page') }}" class="rounded border-line text-accent focus:ring-accent"></th>
                        <th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th>{{ __('Category') }}</th><th>{{ __('Who') }}</th><th class="num">{{ __('Amount') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr x-data="quickRow(@js(route('transactions.quick', $t)), @js($t->category_id), @js((string) ($t->owner_id ?? 'shared')))">
                            <td><input type="checkbox" value="{{ $t->id }}" x-model="ids" aria-label="{{ __('Select') }}" class="rounded border-line text-accent focus:ring-accent"></td>
                            <td class="whitespace-nowrap text-muted">{{ $t->date->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                            <td>
                                <div>{{ $t->description }}</div>
                                <div class="text-xs text-muted">{{ $t->account->name }}@if ($t->paidBy) · {{ __('paid by :name', ['name' => $t->paidBy->name]) }}@endif</div>
                            </td>
                            <td>
                                <select x-model="category" @change="save()" aria-label="{{ __('Category') }}" class="input !w-auto !min-w-36 !py-1 text-sm">
                                    <option value="">—</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <select x-model="owner" @change="save()" aria-label="{{ __('Who') }}" class="input !w-auto !min-w-28 !py-1 text-sm">
                                        <x-owner-options :members="$members" selected="" />
                                    </select>
                                    <span class="w-4 text-xs" aria-live="polite">
                                        <span x-show="state === 'saved'" x-cloak class="text-good">✓</span>
                                        <span x-show="state === 'error'" x-cloak class="text-bad" title="{{ __('Could not save') }}">!</span>
                                    </span>
                                </div>
                            </td>
                            <td class="num"><x-money :cents="$t->amount_cents" :signed="true" /></td>
                            <td class="num">
                                <a href="{{ route('rules.index', ['from' => $t->id]) }}" class="btn btn-sm" title="{{ __('Make a rule from this transaction') }}">{{ __('Rule') }}</a>
                                <a href="{{ route('transactions.edit', $t) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-muted">{{ __('No transactions match.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $transactions->links('pagination') }}
</x-app-layout>
