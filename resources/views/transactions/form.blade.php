@php
    $editing = $transaction->exists;
    $type = old('type', $editing ? ($transaction->amount_cents < 0 ? 'expense' : 'income') : 'expense');
    $owner = old('owner', $editing ? ($transaction->owner_id ?? 'shared') : (auth()->id()));
    $shared = (string) $owner === 'shared';
@endphp
<x-app-layout :title="$editing ? __('Edit transaction') : __('Add transaction')">
    <x-slot name="header">
        <h1 class="page-title">{{ $editing ? __('Edit transaction') : __('Add transaction') }}</h1>
    </x-slot>

    <form method="POST" action="{{ $editing ? route('transactions.update', $transaction) : route('transactions.store') }}"
          class="card mx-auto max-w-2xl space-y-4" x-data="{ type: @js($type), owner: @js((string) $owner) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-1 rounded-lg border border-line p-1" role="radiogroup" aria-label="{{ __('Type') }}">
            <label class="cursor-pointer rounded-md px-3 py-1.5 text-center text-sm font-medium" :class="type === 'expense' ? 'bg-bad/15 text-bad' : 'text-muted'">
                <input type="radio" name="type" value="expense" x-model="type" class="sr-only">{{ __('Expense') }}
            </label>
            <label class="cursor-pointer rounded-md px-3 py-1.5 text-center text-sm font-medium" :class="type === 'income' ? 'bg-good/15 text-good' : 'text-muted'">
                <input type="radio" name="type" value="income" x-model="type" class="sr-only">{{ __('Income') }}
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Description')" for="description" class="sm:col-span-2">
                <input id="description" name="description" class="input" required maxlength="255" value="{{ old('description', $transaction->description) }}" autofocus>
            </x-field>
            <x-field :label="__('Amount')" for="amount">
                <input id="amount" name="amount" class="input" required inputmode="decimal" placeholder="0,00"
                       value="{{ old('amount', $editing ? \App\Support\Money::input($transaction->amount_cents) : '') }}">
            </x-field>
            <x-field :label="__('Date')" for="date">
                <input id="date" type="date" name="date" class="input" required value="{{ old('date', $transaction->date?->format('Y-m-d')) }}">
            </x-field>
            <x-field :label="__('Account')" for="account_id">
                <select id="account_id" name="account_id" class="input" required>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id', $transaction->account_id) === $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field :label="__('Category')" for="category_id">
                <select id="category_id" name="category_id" class="input">
                    <option value="">{{ __('Uncategorised') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $transaction->category_id) === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field :label="__('Who is it for?')" for="owner">
                <select id="owner" name="owner" class="input" x-model="owner"><x-owner-options :members="$members" :selected="$owner" :shared="__('Shared (the household)')" /></select>
            </x-field>
            <x-field :label="__('Who paid?')" for="paid_by_id" x-show="owner === 'shared' && type === 'expense'" x-cloak>
                <select id="paid_by_id" name="paid_by_id" class="input">
                    <option value="">{{ __('The joint account') }}</option>
                    @foreach ($members as $m)<option value="{{ $m->id }}" @selected((int) old('paid_by_id', $transaction->paid_by_id) === $m->id)>{{ $m->name }}</option>@endforeach
                </select>
                <p class="muted mt-1 text-xs">{{ __('If a person paid from their own pocket, Settle up works out what the others owe them.') }}</p>
            </x-field>
            <x-field :label="__('Notes')" for="notes" class="sm:col-span-2">
                <textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $transaction->notes) }}</textarea>
            </x-field>
        </div>

        <div class="flex items-center justify-between border-t border-line pt-4">
            <div class="flex gap-2">
                <button class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('transactions.index') }}" class="btn">{{ __('Cancel') }}</a>
            </div>
            @if ($editing)
                <button type="submit" form="delete-form" class="btn btn-danger" onclick="return confirm('{{ __('Delete this transaction?') }}')">{{ __('Delete') }}</button>
            @endif
        </div>
    </form>

    @if ($editing)
        <form id="delete-form" method="POST" action="{{ route('transactions.destroy', $transaction) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif
</x-app-layout>
