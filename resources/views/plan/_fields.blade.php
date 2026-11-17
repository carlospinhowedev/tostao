@php
    $p = fn ($key, $default = '') => old($key, $item?->{$key} ?? $default);
    $uid = $item?->id ?? 'new';
    $months = (array) old('months', $item?->months ?? []);
    $ownerValue = $item ? ($item->owner_id ?? 'shared') : 'shared';
@endphp
<input type="hidden" name="active" value="0">
<div class="grid gap-3 sm:grid-cols-2">
    <x-field :label="__('Name')" for="name-{{ $uid }}" class="sm:col-span-2"><input id="name-{{ $uid }}" name="name" class="input" required maxlength="100" value="{{ $p('name') }}"></x-field>
    <x-field :label="__('Type')" for="kind-{{ $uid }}">
        <select id="kind-{{ $uid }}" name="kind" class="input" x-model="kind">
            <option value="expense">{{ __('Expense') }}</option>
            <option value="income">{{ __('Income') }}</option>
            <option value="saving">{{ __('Savings') }}</option>
        </select>
    </x-field>
    <x-field :label="__('Amount')" for="amount-{{ $uid }}"><input id="amount-{{ $uid }}" name="amount" class="input" required inputmode="decimal" placeholder="0,00" value="{{ $item ? \App\Support\Money::input($item->amount_cents) : old('amount') }}"></x-field>
    <x-field :label="__('Whose')" for="owner-{{ $uid }}"><select id="owner-{{ $uid }}" name="owner" class="input"><x-owner-options :members="$members" :selected="old('owner', $ownerValue)" :shared="__('Shared (the household)')" /></select></x-field>
    <x-field :label="__('How predictable')" for="nature-{{ $uid }}" x-show="kind === 'expense'">
        <select id="nature-{{ $uid }}" name="nature" class="input">
            @foreach (\App\Models\PlanItem::NATURES as $n)<option value="{{ $n }}" @selected($p('nature', 'fixed') === $n)>{{ __(\App\Models\PlanItem::NATURE_LABELS[$n]) }}</option>@endforeach
        </select>
    </x-field>
    <x-field :label="__('Group')" for="group-{{ $uid }}">
        <input id="group-{{ $uid }}" name="group_name" class="input" maxlength="60" list="plan-groups" placeholder="{{ __('Pet, Subscriptions…') }}" value="{{ $p('group_name') }}">
    </x-field>
    <x-field :label="__('Linked category')" for="cat-{{ $uid }}" x-show="kind === 'expense'" class="sm:col-span-2">
        <select id="cat-{{ $uid }}" name="category_id" class="input">
            <option value="">—</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) $p('category_id', 0) === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <p class="muted mt-1 text-xs">{{ __('Real spending in this category is compared with the plan.') }}</p>
    </x-field>
    <fieldset class="sm:col-span-2">
        <legend class="label">{{ __('Months') }} <span class="muted font-normal">({{ __('none ticked = every month') }})</span></legend>
        <div class="mt-1 grid grid-cols-6 gap-1 text-xs">
            @foreach (range(1, 12) as $m)
                <label class="flex items-center gap-1"><input type="checkbox" name="months[]" value="{{ $m }}" @checked(in_array($m, array_map('intval', $months), true)) class="rounded border-line text-accent focus:ring-accent">{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->locale(app()->getLocale())->translatedFormat('M') }}</label>
            @endforeach
        </div>
    </fieldset>
    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="active" value="1" @checked($item ? $item->active : true) class="rounded border-line text-accent focus:ring-accent"> {{ __('Active') }}</label>
</div>
@once
    <datalist id="plan-groups">@foreach ($groups as $g)<option value="{{ $g }}">@endforeach</datalist>
@endonce
