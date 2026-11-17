<x-app-layout :title="$connection->institution_name">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $connection->institution_name }}</h1>
            <p class="muted">{{ __('Choose where each bank account goes in Tostão.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('banks.link', $connection) }}" class="card mx-auto max-w-3xl space-y-5">
        @csrf

        @foreach ($connection->accounts as $b)
            <div class="space-y-3 border-b border-line pb-5 last:border-0 last:pb-0" x-data="{ target: @js($b->account_id ? (string) $b->account_id : 'new') }">
                <div>
                    <div class="font-medium">{{ $b->name }}</div>
                    @if ($b->iban)<div class="muted text-xs">{{ $b->iban }}</div>@endif
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field :label="__('Goes into')" for="acc-{{ $b->id }}">
                        <select id="acc-{{ $b->id }}" name="links[{{ $b->id }}][account]" class="input" x-model="target">
                            <option value="new">{{ __('A new account') }}</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                            <option value="skip">{{ __('Do not import') }}</option>
                        </select>
                    </x-field>
                    <x-field :label="__('New account name')" for="name-{{ $b->id }}" x-show="target === 'new'" x-cloak>
                        <input id="name-{{ $b->id }}" name="links[{{ $b->id }}][name]" class="input" value="{{ $b->name }}" maxlength="100">
                    </x-field>
                    <x-field :label="__('Import from')" for="from-{{ $b->id }}" x-show="target !== 'skip'" x-cloak>
                        <input id="from-{{ $b->id }}" type="date" name="links[{{ $b->id }}][from]" class="input" value="{{ $b->sync_from?->format('Y-m-d') }}">
                        <p class="muted mt-1 text-xs">{{ __('Leave empty to continue after the last transaction already in the account.') }}</p>
                    </x-field>
                </div>
            </div>
        @endforeach

        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Save and sync') }}</button>
            <a href="{{ route('banks.index') }}" class="btn">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
