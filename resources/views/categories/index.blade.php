@php $kinds = ['expense' => __('Expense'), 'income' => __('Income'), 'transfer' => __('Transfer')]; @endphp
<x-app-layout :title="__('Categories and budgets')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Categories and budgets') }}</h1>
            <p class="muted">{{ __('Transfers between your own accounts never count as spending or income. Give an expense category a monthly limit to track it on the dashboard.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card !p-0 lg:col-span-2">
            <ul class="divide-y divide-line">
                @foreach ($categories as $c)
                    <li>
                        <details>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                <span class="inline-flex items-center gap-2"><span class="dot" style="background: {{ $c->color }}"></span>{{ $c->name }}
                                    <span class="pill">{{ $kinds[$c->kind] }}</span></span>
                                <span class="muted">
                                    @if ($c->monthly_budget_cents)<x-money :cents="$c->monthly_budget_cents" />{{ __('/month') }} · @endif
                                    {{ trans_choice(':count transaction|:count transactions', $c->transactions_count, ['count' => $c->transactions_count]) }}
                                </span>
                            </summary>
                            <form method="POST" action="{{ route('categories.update', $c) }}" class="grid gap-3 border-t border-line bg-page/50 p-4 sm:grid-cols-4">
                                @csrf @method('PUT')
                                <x-field :label="__('Name')" class="sm:col-span-2"><input name="name" class="input" required value="{{ $c->name }}"></x-field>
                                <x-field :label="__('Kind')"><select name="kind" class="input">@foreach ($kinds as $k => $l)<option value="{{ $k }}" @selected($c->kind === $k)>{{ $l }}</option>@endforeach</select></x-field>
                                <x-field :label="__('Colour')"><input type="color" name="color" value="{{ $c->color }}" class="h-9 w-full rounded-lg border border-line bg-surface p-1"></x-field>
                                <x-field :label="__('Monthly budget')" class="sm:col-span-2"><input name="monthly_budget" class="input" inputmode="decimal" placeholder="{{ __('No limit') }}" value="{{ $c->monthly_budget_cents ? \App\Support\Money::input($c->monthly_budget_cents) : '' }}"></x-field>
                                <div class="flex items-end gap-2 sm:col-span-2">
                                    <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                                    <button type="submit" form="del-cat-{{ $c->id }}" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('Delete this category? Its transactions become uncategorised.') }}')">{{ __('Delete') }}</button>
                                </div>
                            </form>
                            <form id="del-cat-{{ $c->id }}" method="POST" action="{{ route('categories.destroy', $c) }}" class="hidden">@csrf @method('DELETE')</form>
                        </details>
                    </li>
                @endforeach
            </ul>
        </div>

        <form method="POST" action="{{ route('categories.store') }}" class="card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">{{ __('New category') }}</h2>
            <x-field :label="__('Name')" for="name"><input id="name" name="name" class="input" required></x-field>
            <x-field :label="__('Kind')" for="kind"><select id="kind" name="kind" class="input">@foreach ($kinds as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-field>
            <x-field :label="__('Colour')" for="color"><input id="color" type="color" name="color" value="#0f766e" class="h-9 w-full rounded-lg border border-line bg-surface p-1"></x-field>
            <x-field :label="__('Monthly budget')" for="monthly_budget"><input id="monthly_budget" name="monthly_budget" class="input" inputmode="decimal" placeholder="{{ __('No limit') }}"></x-field>
            <button class="btn btn-primary w-full">{{ __('Create category') }}</button>
        </form>
    </div>
</x-app-layout>
