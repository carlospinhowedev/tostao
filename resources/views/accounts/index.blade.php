<x-app-layout :title="__('Accounts')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Accounts') }}</h1>
            <p class="muted">{{ __('Bank accounts, cards and cash. A joint account belongs to the household.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @foreach ($accounts as $a)
                <details class="card !p-0 {{ $a->archived ? 'opacity-60' : '' }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-medium">{{ $a->name }} @if ($a->archived)<span class="pill ml-1">{{ __('Archived') }}</span>@endif</div>
                            <div class="muted">{{ __($a->type === 'bank' ? 'Bank account' : ($a->type === 'card' ? 'Card' : ($a->type === 'cash' ? 'Cash' : 'Savings'))) }} · {{ $a->owner?->name ?? __('Shared') }}</div>
                        </div>
                        <x-money :cents="$a->balanceCents()" class="text-lg font-semibold" />
                    </summary>
                    <form method="POST" action="{{ route('accounts.update', $a) }}" class="grid gap-3 border-t border-line p-4 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <x-field :label="__('Name')"><input name="name" class="input" required value="{{ $a->name }}"></x-field>
                        <x-field :label="__('Type')">
                            <select name="type" class="input">
                                @foreach (\App\Models\Account::TYPES as $t)<option value="{{ $t }}" @selected($a->type === $t)>{{ __(['bank' => 'Bank account', 'card' => 'Card', 'cash' => 'Cash', 'savings' => 'Savings'][$t]) }}</option>@endforeach
                            </select>
                        </x-field>
                        <x-field :label="__('Opening balance')"><input name="opening_balance" class="input" inputmode="decimal" value="{{ $a->opening_balance_cents < 0 ? '-' : '' }}{{ \App\Support\Money::input($a->opening_balance_cents) }}"></x-field>
                        <x-field :label="__('Belongs to')"><select name="owner" class="input"><x-owner-options :members="$members" :selected="$a->owner_id ?? 'shared'" :shared="__('Shared (joint account)')" /></select></x-field>
                        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="archived" value="1" @checked($a->archived) class="rounded border-line text-accent focus:ring-accent"> {{ __('Archived (hidden from forms, history is kept)') }}</label>
                        <div class="flex gap-2 sm:col-span-2">
                            <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                            <button type="submit" form="del-account-{{ $a->id }}" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('Delete this account?') }}')">{{ __('Delete') }}</button>
                        </div>
                    </form>
                    <form id="del-account-{{ $a->id }}" method="POST" action="{{ route('accounts.destroy', $a) }}" class="hidden">@csrf @method('DELETE')</form>
                </details>
            @endforeach
        </div>

        <form method="POST" action="{{ route('accounts.store') }}" class="card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">{{ __('New account') }}</h2>
            <x-field :label="__('Name')" for="name"><input id="name" name="name" class="input" required placeholder="{{ __('Joint account') }}"></x-field>
            <x-field :label="__('Type')" for="type">
                <select id="type" name="type" class="input">
                    @foreach (\App\Models\Account::TYPES as $t)<option value="{{ $t }}">{{ __(['bank' => 'Bank account', 'card' => 'Card', 'cash' => 'Cash', 'savings' => 'Savings'][$t]) }}</option>@endforeach
                </select>
            </x-field>
            <x-field :label="__('Opening balance')" for="opening_balance"><input id="opening_balance" name="opening_balance" class="input" inputmode="decimal" placeholder="0,00"></x-field>
            <x-field :label="__('Belongs to')" for="owner"><select id="owner" name="owner" class="input"><x-owner-options :members="$members" selected="shared" :shared="__('Shared (joint account)')" /></select></x-field>
            <button class="btn btn-primary w-full">{{ __('Create account') }}</button>
        </form>
    </div>
</x-app-layout>
