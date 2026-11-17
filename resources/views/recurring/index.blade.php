@php
    $freq = ['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'yearly' => __('Yearly')];
@endphp
<x-app-layout :title="__('Recurring')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Recurring transactions') }}</h1>
            <p class="muted max-w-2xl">{{ __('Rent, subscriptions, salary. They are added automatically when they fall due, even for days nobody opened the app.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @forelse ($items as $r)
                @php $type = $r->amount_cents < 0 ? 'expense' : 'income'; $owner = $r->owner_id ?? 'shared'; @endphp
                <details class="card !p-0 {{ $r->active ? '' : 'opacity-60' }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-medium">{{ $r->description }}</div>
                            <div class="muted">{{ $freq[$r->frequency] }} · {{ $r->owner?->name ?? __('Shared') }} · {{ $r->active ? __('next :date', ['date' => $r->next_date->locale(app()->getLocale())->translatedFormat('d M Y')]) : __('ended') }}</div>
                        </div>
                        <x-money :cents="$r->amount_cents" :signed="true" class="font-semibold" />
                    </summary>
                    <form method="POST" action="{{ route('recurring.update', $r) }}" class="grid gap-3 border-t border-line bg-page/50 p-4 sm:grid-cols-3">
                        @csrf @method('PUT')
                        <x-field :label="__('Description')" class="sm:col-span-2"><input name="description" class="input" required value="{{ $r->description }}"></x-field>
                        <x-field :label="__('Amount')"><input name="amount" class="input" required inputmode="decimal" value="{{ \App\Support\Money::input($r->amount_cents) }}"></x-field>
                        <x-field :label="__('Type')"><select name="type" class="input"><option value="expense" @selected($type === 'expense')>{{ __('Expense') }}</option><option value="income" @selected($type === 'income')>{{ __('Income') }}</option></select></x-field>
                        <x-field :label="__('Account')"><select name="account_id" class="input">@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($r->account_id === $a->id)>{{ $a->name }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Category')"><select name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($r->category_id === $c->id)>{{ $c->name }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Who')"><select name="owner" class="input"><x-owner-options :members="$members" :selected="$owner" /></select></x-field>
                        <x-field :label="__('Paid by')"><select name="paid_by_id" class="input"><option value="">{{ __('The joint account') }}</option>@foreach ($members as $m)<option value="{{ $m->id }}" @selected($r->paid_by_id === $m->id)>{{ $m->name }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Repeats')"><select name="frequency" class="input">@foreach ($freq as $k => $l)<option value="{{ $k }}" @selected($r->frequency === $k)>{{ $l }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Next date')"><input type="date" name="next_date" class="input" required value="{{ $r->next_date->format('Y-m-d') }}"></x-field>
                        <x-field :label="__('Ends on')"><input type="date" name="ends_on" class="input" value="{{ $r->ends_on?->format('Y-m-d') }}"></x-field>
                        <label class="flex items-center gap-2 self-end text-sm"><input type="checkbox" name="active" value="1" @checked($r->active) class="rounded border-line text-accent focus:ring-accent"> {{ __('Active') }}</label>
                        <div class="flex gap-2 sm:col-span-3">
                            <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                            <button type="submit" form="del-rec-{{ $r->id }}" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                        </div>
                    </form>
                    <form id="del-rec-{{ $r->id }}" method="POST" action="{{ route('recurring.destroy', $r) }}" class="hidden">@csrf @method('DELETE')</form>
                </details>
            @empty
                <div class="card muted text-center">{{ __('Nothing recurring yet.') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('recurring.store') }}" class="card h-fit space-y-3" x-data="{ owner: 'shared' }">
            @csrf
            <h2 class="font-semibold">{{ __('New recurring transaction') }}</h2>
            <x-field :label="__('Description')" for="r-description"><input id="r-description" name="description" class="input" required placeholder="{{ __('Rent') }}"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Amount')" for="r-amount"><input id="r-amount" name="amount" class="input" required inputmode="decimal" placeholder="0,00"></x-field>
                <x-field :label="__('Type')" for="r-type"><select id="r-type" name="type" class="input"><option value="expense">{{ __('Expense') }}</option><option value="income">{{ __('Income') }}</option></select></x-field>
            </div>
            <x-field :label="__('Account')" for="r-account"><select id="r-account" name="account_id" class="input">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></x-field>
            <x-field :label="__('Category')" for="r-category"><select id="r-category" name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></x-field>
            <x-field :label="__('Who')" for="r-owner"><select id="r-owner" name="owner" class="input" x-model="owner"><x-owner-options :members="$members" selected="shared" /></select></x-field>
            <x-field :label="__('Paid by')" for="r-paid" x-show="owner === 'shared'" x-cloak><select id="r-paid" name="paid_by_id" class="input"><option value="">{{ __('The joint account') }}</option>@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Repeats')" for="r-frequency"><select id="r-frequency" name="frequency" class="input" ><option value="monthly">{{ __('Monthly') }}</option><option value="weekly">{{ __('Weekly') }}</option><option value="yearly">{{ __('Yearly') }}</option></select></x-field>
                <x-field :label="__('First date')" for="r-next"><input id="r-next" type="date" name="next_date" class="input" required value="{{ now()->format('Y-m-d') }}"></x-field>
            </div>
            <x-field :label="__('Ends on (optional)')" for="r-ends"><input id="r-ends" type="date" name="ends_on" class="input"></x-field>
            <button class="btn btn-primary w-full">{{ __('Create') }}</button>
        </form>
    </div>
</x-app-layout>
