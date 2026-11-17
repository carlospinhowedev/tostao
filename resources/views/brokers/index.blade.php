<x-app-layout :title="__('Investments')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Investments') }}</h1>
            <p class="muted max-w-2xl">{{ __('Savings and brokerage accounts. Tostão tracks what they are worth and the money you put in or take out. Individual trades are not tracked, and buying shares is never counted as spending.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @error('broker')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            @error('account_id')<p class="text-sm text-bad">{{ $message }}</p>@enderror

            @foreach ($investments as $a)
                @php($valuation = $a->latestValuation())
                <div class="card space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $a->name }}</div>
                            <div class="muted">{{ $a->owner?->name ?? __('Shared') }}@if ($valuation) · {{ __('Value on :date', ['date' => $valuation->date->locale(app()->getLocale())->translatedFormat('d M Y')]) }} ({{ $valuation->source === 'manual' ? __('typed in') : $valuation->source }})@endif</div>
                        </div>
                        <div class="text-right">
                            @if ($valuation)<x-money :cents="$valuation->value_cents" class="text-lg font-semibold" />@else<span class="muted">{{ __('No value yet') }}</span>@endif
                            <div class="muted text-xs">{{ __('Put in so far') }}: <x-money :cents="(int) $a->transactions()->sum('amount_cents')" /></div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('valuations.store', $a) }}" class="flex flex-wrap items-end gap-2 border-t border-line pt-3">
                        @csrf
                        <x-field :label="__('Value today')" for="value-{{ $a->id }}"><input id="value-{{ $a->id }}" name="value" class="input !w-36" inputmode="decimal" required placeholder="0,00"></x-field>
                        <button class="btn btn-sm">{{ __('Save value') }}</button>
                    </form>
                </div>
            @endforeach

            @if ($investments->isEmpty())
                <div class="card"><p class="muted">{{ __('No investment account yet. Create one in Accounts and choose the type Investments.') }}</p></div>
            @endif
        </div>

        <div class="space-y-4">
            @forelse ($providers as $key => $p)
                @php($link = $links[$key] ?? null)
                <div class="card space-y-3">
                    <h2 class="font-semibold">{{ $p->label() }}</h2>
                    @if ($link)
                        <p class="text-sm">{{ __('Feeds') }} <strong>{{ $link->account->name }}</strong>@if ($link->last_synced_at) · {{ __('Last sync') }} {{ $link->last_synced_at->diffForHumans() }}@endif</p>
                        @if ($link->last_error)<p class="text-sm text-bad">{{ $link->last_error }}</p>@endif
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('brokers.sync', $key) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('Sync now') }}</button></form>
                            <form method="POST" action="{{ route('brokers.destroy', $key) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" onclick="return confirm(@js(__('Remove this link? Imported transactions are kept.')))">{{ __('Remove') }}</button></form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('brokers.link', $key) }}" class="space-y-3">
                            @csrf
                            <x-field :label="__('Account')" for="acc-{{ $key }}">
                                <select id="acc-{{ $key }}" name="account_id" class="input" required>
                                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                                </select>
                            </x-field>
                            <x-field :label="__('Import deposits from')" for="from-{{ $key }}"><input id="from-{{ $key }}" type="date" name="sync_from" class="input"></x-field>
                            <button class="btn btn-primary w-full" @disabled($accounts->isEmpty())>{{ __('Link and sync') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="card space-y-2">
                    <h2 class="font-semibold">{{ __('Connect a broker') }}</h2>
                    <p class="muted text-sm">{{ __('Trading 212 has an API. Create a key in its app and add it to your .env file; the steps are in the guide. For other brokers, type the value in by hand.') }}</p>
                    <p class="muted text-sm"><a class="underline" href="https://github.com/carlospinhowedev/tostao/blob/main/docs/INVESTMENTS.md">docs/INVESTMENTS.md</a></p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
