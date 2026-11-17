<x-app-layout :title="__('Settle up')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Settle up') }}</h1>
            <p class="muted max-w-2xl">{{ __('Shared expenses that someone paid from their own pocket are split equally. Record a payment between you when you square up.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                @if ($members->count() < 2)
                    <p class="muted">{{ __('Add another member to split expenses.') }} <a class="underline" href="{{ route('members.index') }}">{{ __('Members') }}</a></p>
                @elseif (count($result['payments']) === 0)
                    <p class="text-lg font-semibold text-good">{{ __('You are all square.') }}</p>
                    <p class="muted">{{ __('Nobody owes anything right now.') }}</p>
                @else
                    @foreach ($result['payments'] as $p)
                        <p class="text-lg">
                            <strong>{{ $members[$p['from']]->name }}</strong> {{ __('owes') }} <strong>{{ $members[$p['to']]->name }}</strong>
                            <x-money :cents="$p['amount_cents']" class="ml-1 font-semibold text-accent" />
                        </p>
                    @endforeach
                @endif

                @if ($members->count() >= 2)
                    <dl class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-2">
                        @foreach ($result['balances'] as $id => $cents)
                            <div class="flex items-center justify-between rounded-lg bg-page px-3 py-2 text-sm">
                                <dt class="inline-flex items-center gap-2"><span class="dot" style="background: {{ $members[$id]->color }}"></span>{{ $members[$id]->name }}</dt>
                                <dd><x-money :cents="$cents" :signed="true" /></dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="muted mt-2 text-xs">{{ __('Positive: the others owe this person. Negative: this person owes the others.') }}</p>
                @endif
            </div>

            <div class="card !p-0 overflow-x-auto">
                <h2 class="px-5 pt-4 font-semibold">{{ __('Payments between you') }}</h2>
                <table class="tbl mt-2">
                    <tbody>
                        @forelse ($history as $s)
                            <tr>
                                <td class="text-muted">{{ $s->date->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                                <td>{{ $s->from->name }} → {{ $s->to->name }}@if ($s->note) <span class="text-muted">· {{ $s->note }}</span>@endif</td>
                                <td class="num"><x-money :cents="$s->amount_cents" /></td>
                                <td class="num">
                                    <form method="POST" action="{{ route('settle.destroy', $s) }}" onsubmit="return confirm('{{ __('Remove this payment?') }}')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">{{ __('Remove') }}</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-muted">{{ __('No payments recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($members->count() >= 2)
            <form method="POST" action="{{ route('settle.store') }}" class="card h-fit space-y-3">
                @csrf
                <h2 class="font-semibold">{{ __('Record a payment') }}</h2>
                <x-field :label="__('Who paid')" for="from"><select id="from" name="from_user_id" class="input">@foreach ($members as $m)<option value="{{ $m->id }}" @selected(auth()->id() === $m->id)>{{ $m->name }}</option>@endforeach</select></x-field>
                <x-field :label="__('Paid to')" for="to"><select id="to" name="to_user_id" class="input">@php $other = $members->keys()->first(fn ($id) => $id !== auth()->id()); @endphp @foreach ($members as $m)<option value="{{ $m->id }}" @selected($m->id === $other)>{{ $m->name }}</option>@endforeach</select></x-field>
                <x-field :label="__('Amount')" for="amount"><input id="amount" name="amount" class="input" required inputmode="decimal" placeholder="0,00" value="{{ count($result['payments']) ? \App\Support\Money::input($result['payments'][0]['amount_cents']) : '' }}"></x-field>
                <x-field :label="__('Date')" for="date"><input id="date" type="date" name="date" class="input" required value="{{ now()->format('Y-m-d') }}"></x-field>
                <x-field :label="__('Note')" for="note"><input id="note" name="note" class="input" maxlength="255"></x-field>
                <button class="btn btn-primary w-full">{{ __('Record payment') }}</button>
            </form>
        @endif
    </div>
</x-app-layout>
