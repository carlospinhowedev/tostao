<x-app-layout :title="__('Bank connections')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Bank connections') }}</h1>
            <p class="muted">{{ __('Fetch new transactions straight from your bank instead of uploading files. Optional.') }}</p>
        </div>
    </x-slot>

    @unless ($enabled)
        <div class="card max-w-2xl space-y-2">
            <h2 class="font-semibold">{{ __('Not set up yet') }}</h2>
            <p class="muted">{{ __('Connecting a bank needs an account with an open-banking provider. Add its credentials to your .env file and this page comes alive. Importing CSV files keeps working either way.') }}</p>
            <p class="muted"><a class="underline" href="https://github.com/carlospinhowedev/tostao/blob/main/docs/BANKS.md">docs/BANKS.md</a></p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                @forelse ($connections as $c)
                    <div class="card space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $c->institution_name }}</div>
                                <div class="muted">
                                    {{ isset($providers[$c->provider]) ? $providers[$c->provider]->label() : $c->provider }}
                                    @if ($c->last_synced_at) · {{ __('Last sync') }} {{ $c->last_synced_at->diffForHumans() }} @endif
                                </div>
                            </div>
                            @if ($c->status === 'active' && ! $c->isExpired())
                                <span class="pill {{ $c->expiresSoon() ? 'text-warn' : 'text-good' }}">
                                    {{ $c->expiresSoon() ? trans_choice('Expires in :count day|Expires in :count days', $c->daysLeft(), ['count' => $c->daysLeft()]) : __('Connected') }}
                                </span>
                            @elseif ($c->status === 'expired' || $c->isExpired())
                                <span class="pill text-bad">{{ __('Expired') }}</span>
                            @else
                                <span class="pill">{{ __('Pending') }}</span>
                            @endif
                        </div>

                        @if ($c->last_error)<p class="text-sm text-bad">{{ $c->last_error }}</p>@endif

                        <ul class="space-y-1 text-sm">
                            @foreach ($c->accounts as $b)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $b->name }}</span>
                                    <span class="muted">{{ $b->account ? '→ '.$b->account->name : __('Not linked') }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="flex flex-wrap gap-2 border-t border-line pt-3">
                            @if ($c->status === 'active' && ! $c->isExpired())
                                <form method="POST" action="{{ route('banks.sync', $c) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('Sync now') }}</button></form>
                                <a href="{{ route('banks.show', $c) }}" class="btn btn-sm">{{ __('Accounts') }}</a>
                            @endif
                            @if ($c->status !== 'pending')
                                <form method="POST" action="{{ route('banks.connect') }}">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $c->provider }}">
                                    <input type="hidden" name="institution" value="{{ $c->institution_id }}">
                                    <input type="hidden" name="institution_name" value="{{ $c->institution_name }}">
                                    <button class="btn btn-sm">{{ __('Renew consent') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('banks.destroy', $c) }}" class="ml-auto">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm(@js(__('Remove this connection? Imported transactions are kept.')))">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="card"><p class="muted">{{ __('No bank connected yet.') }}</p></div>
                @endforelse
            </div>

            <div class="card h-fit space-y-3">
                <h2 class="font-semibold">{{ __('Connect a bank') }}</h2>

                @if (! $browse)
                    <form method="GET" action="{{ route('banks.index') }}" class="space-y-3">
                        <input type="hidden" name="browse" value="1">
                        @if (count($providers) > 1)
                            <x-field :label="__('Provider')" for="provider">
                                <select id="provider" name="provider" class="input">
                                    @foreach ($providers as $key => $p)<option value="{{ $key }}" @selected($provider?->key() === $key)>{{ $p->label() }}</option>@endforeach
                                </select>
                            </x-field>
                        @else
                            <input type="hidden" name="provider" value="{{ $provider?->key() }}">
                        @endif
                        <x-field :label="__('Country')" for="country">
                            <input id="country" name="country" class="input uppercase" maxlength="2" value="{{ $country }}">
                        </x-field>
                        <button class="btn btn-primary w-full">{{ __('Find my bank') }}</button>
                    </form>
                @else
                    @if ($error)<p class="text-sm text-bad">{{ $error }}</p>@endif
                    <form method="POST" action="{{ route('banks.connect') }}" class="space-y-3" x-data="{ q: '', sel: '', name: '' }">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $provider->key() }}">
                        <input type="hidden" name="institution" :value="sel">
                        <input type="hidden" name="institution_name" :value="name">
                        <x-field :label="__('Search')" for="q"><input id="q" type="search" x-model="q" class="input" placeholder="{{ __('Bank name') }}"></x-field>
                        <div class="max-h-64 space-y-1 overflow-auto" role="listbox">
                            @foreach ($institutions as $i)
                                <button type="button" role="option"
                                        x-show="@js(mb_strtolower($i->name)).includes(q.toLowerCase())"
                                        @click="sel = @js($i->id); name = @js($i->name)"
                                        :class="sel === @js($i->id) ? 'bg-accent/10 text-accent' : ''"
                                        class="nav-link block w-full text-left">{{ $i->name }}</button>
                            @endforeach
                            @if ($institutions === [] && ! $error)<p class="muted">{{ __('No banks found for this country.') }}</p>@endif
                        </div>
                        <p class="muted text-xs">{{ __('You will be sent to your bank to approve read-only access. Tostão never sees your bank password.') }}</p>
                        <button class="btn btn-primary w-full" :disabled="!sel">{{ __('Continue to the bank') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endunless
</x-app-layout>
