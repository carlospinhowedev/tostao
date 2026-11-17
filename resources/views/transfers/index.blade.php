<x-app-layout :title="__('Transfers between accounts')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Transfers between accounts') }}</h1>
            <p class="muted max-w-2xl">{{ __('Money that only moved from one of your accounts to another is not spending or income. These pairs have the same amount, in different accounts, within :days days. Tick the ones that are really transfers.', ['days' => $window]) }}</p>
        </div>
    </x-slot>

    @error('pairs')<p class="mb-3 text-sm text-bad">{{ $message }}</p>@enderror

    @if (! $hasCategory)
        <div class="card"><p class="muted">{{ __('There is no category of the type Transfer. Create one in Categories first.') }}</p></div>
    @elseif ($pairs === [])
        <div class="card"><p class="muted text-center">{{ __('No transfers to review. Ambiguous cases are left out on purpose.') }}</p></div>
    @else
        <form method="POST" action="{{ route('transfers.confirm') }}" class="card !p-0" x-data="{ all: true }">
            @csrf
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" x-model="all" @change="document.querySelectorAll('.pair').forEach(c => c.checked = all)" aria-label="{{ __('Select all') }}" class="rounded border-line text-accent focus:ring-accent"></th>
                        <th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pairs as $p)
                        <tr>
                            <td><input type="checkbox" name="pairs[]" value="{{ $p['out']->id }}:{{ $p['in']->id }}" checked class="pair rounded border-line text-accent focus:ring-accent" aria-label="{{ __('Select') }}"></td>
                            <td>
                                <div>{{ $p['out']->account->name }} <span class="muted text-xs">{{ $p['out']->date->format('d/m/Y') }}</span></div>
                                <div class="muted text-xs">{{ $p['out']->description }}</div>
                            </td>
                            <td>
                                <div>{{ $p['in']->account->name }} <span class="muted text-xs">{{ $p['in']->date->format('d/m/Y') }}</span></div>
                                <div class="muted text-xs">{{ $p['in']->description }}</div>
                            </td>
                            <td class="num"><x-money :cents="$p['in']->amount_cents" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t border-line p-4">
                <button class="btn btn-primary">{{ __('Mark as transfers') }}</button>
            </div>
        </form>
    @endif
</x-app-layout>
