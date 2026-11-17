@php
    $ruleOwner = fn ($r) => $r->set_owner ? ($r->owner_id ?? 'shared') : '';
@endphp
<x-app-layout :title="__('Rules')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Rules') }}</h1>
            <p class="muted max-w-2xl">{{ __('When an imported description contains a keyword, the category and person are filled in for you. The lowest priority number wins; case is ignored.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
        <div class="card !p-0">
            @forelse ($rules as $r)
                <details class="border-b border-line last:border-0">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <span>
                            <span class="text-muted">{{ __('contains') }}</span> <code class="rounded bg-line/60 px-1.5 py-0.5 text-sm">{{ $r->pattern }}</code>
                            @if ($r->exclude_pattern)<span class="pill ml-1">{{ __('except :word', ['word' => $r->exclude_pattern]) }}</span>@endif
                            @if ($r->amount_multiple_cents)<span class="pill ml-1">{{ __('amount in multiples of :n', ['n' => \App\Support\Money::format($r->amount_multiple_cents)]) }}</span>@endif
                        </span>
                        <span class="flex items-center gap-2 text-sm">
                            @if ($r->category)<span class="pill"><span class="dot" style="background: {{ $r->category->color }}"></span>{{ $r->category->name }}</span>@endif
                            @if ($r->set_owner)<span class="pill">{{ $r->owner?->name ?? __('Shared') }}</span>@endif
                            <span class="muted">#{{ $r->priority }}</span>
                        </span>
                    </summary>
                    <form method="POST" action="{{ route('rules.update', $r) }}" class="grid gap-3 border-t border-line bg-page/50 p-4 sm:grid-cols-4">
                        @csrf @method('PUT')
                        <x-field :label="__('Keyword')"><input name="pattern" class="input" required value="{{ $r->pattern }}"></x-field>
                        <x-field :label="__('Except when it contains')"><input name="exclude_pattern" class="input" placeholder="—" value="{{ $r->exclude_pattern }}"></x-field>
                        <x-field :label="__('Only if the amount is a multiple of')"><input name="amount_multiple" class="input" inputmode="decimal" placeholder="—" value="{{ $r->amount_multiple_cents ? \App\Support\Money::input($r->amount_multiple_cents) : '' }}"></x-field>
                        <x-field :label="__('Category')"><select name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($r->category_id === $c->id)>{{ $c->name }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Who')"><select name="owner" class="input"><x-owner-options :members="$members" :selected="$ruleOwner($r)" :blank="__('Leave as is')" /></select></x-field>
                        <x-field :label="__('Priority')"><input type="number" name="priority" min="1" max="9999" class="input" value="{{ $r->priority }}"></x-field>
                        <div class="flex gap-2 sm:col-span-4">
                            <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                            <button type="submit" form="del-rule-{{ $r->id }}" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                        </div>
                    </form>
                    <form id="del-rule-{{ $r->id }}" method="POST" action="{{ route('rules.destroy', $r) }}" class="hidden">@csrf @method('DELETE')</form>
                </details>
            @empty
                <p class="muted p-6 text-center">{{ __('No rules yet. Create one on the right, for example "supermarket" → Groceries.') }}</p>
            @endforelse
        </div>

        @if ($rules->isNotEmpty())
            <form method="POST" action="{{ route('rules.apply') }}" class="flex flex-wrap items-center justify-between gap-3">
                @csrf
                <p class="muted text-sm">{{ __('Apply the rules to transactions that have no category yet. Anything already categorised is left alone.') }}</p>
                <button class="btn btn-sm">{{ __('Apply to existing transactions') }}</button>
            </form>
        @endif
        </div>

        <form method="POST" action="{{ route('rules.store') }}" class="card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">{{ __('New rule') }}</h2>
            @if ($prefill)
                <p class="rounded-lg border border-line bg-page/60 p-2 text-xs text-muted">{{ __('From the transaction: :text. Trim the keyword so it also matches next time.', ['text' => $prefill['description']]) }}</p>
            @endif
            <x-field :label="__('Description contains')" for="pattern"><input id="pattern" name="pattern" class="input" required placeholder="PINGO DOCE" value="{{ old('pattern', $prefill['pattern'] ?? '') }}"></x-field>
            <x-field :label="__('Except when it contains')" for="exclude_pattern">
                <input id="exclude_pattern" name="exclude_pattern" class="input" placeholder="LEV ATM">
                <p class="muted mt-1 text-xs">{{ __('Optional. The rule is skipped when the description also has this word.') }}</p>
            </x-field>
            <x-field :label="__('Only if the amount is a multiple of')" for="amount_multiple">
                <input id="amount_multiple" name="amount_multiple" class="input" inputmode="decimal" placeholder="5,00">
                <p class="muted mt-1 text-xs">{{ __('Optional. For example 5,00 matches 10, 20 and 40 but not 15,26.') }}</p>
            </x-field>
            <x-field :label="__('Set category')" for="category_id"><select id="category_id" name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) ($prefill['category_id'] ?? 0) === $c->id)>{{ $c->name }}</option>@endforeach</select></x-field>
            <x-field :label="__('Set who')" for="owner"><select id="owner" name="owner" class="input"><x-owner-options :members="$members" :selected="$prefill['owner'] ?? ''" :blank="__('Leave as is')" /></select></x-field>
            <x-field :label="__('Priority')" for="priority"><input id="priority" type="number" name="priority" min="1" max="9999" value="100" class="input"></x-field>
            <button class="btn btn-primary w-full">{{ __('Create rule') }}</button>
        </form>
    </div>
</x-app-layout>
