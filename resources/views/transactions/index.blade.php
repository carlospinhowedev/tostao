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

    <div class="card !p-0 overflow-x-auto">
        <table class="tbl">
            <thead>
                <tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th>{{ __('Category') }}</th><th>{{ __('Who') }}</th><th class="num">{{ __('Amount') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="whitespace-nowrap text-muted">{{ $t->date->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                        <td>
                            <div>{{ $t->description }}</div>
                            <div class="text-xs text-muted">{{ $t->account->name }}@if ($t->paidBy) · {{ __('paid by :name', ['name' => $t->paidBy->name]) }}@endif</div>
                        </td>
                        <td>
                            @if ($t->category)<span class="pill"><span class="dot" style="background: {{ $t->category->color }}"></span>{{ $t->category->name }}</span>
                            @else<span class="text-muted">—</span>@endif
                        </td>
                        <td>
                            <span class="pill"><span class="dot" style="background: {{ $t->owner?->color ?? '#8b95a1' }}"></span>{{ $t->owner?->name ?? __('Shared') }}</span>
                        </td>
                        <td class="num"><x-money :cents="$t->amount_cents" :signed="true" /></td>
                        <td class="num">
                            <a href="{{ route('transactions.edit', $t) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">{{ __('No transactions match.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links('pagination') }}
</x-app-layout>
