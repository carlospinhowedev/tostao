<x-app-layout :title="__('Import')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Import a bank file') }}</h1>
            <p class="muted max-w-2xl">{{ __('Download the statement as CSV from your online banking and drop it here. You will check the columns and pick the rows before anything is saved.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('imports.upload') }}" enctype="multipart/form-data" class="card space-y-4 lg:col-span-2">
            @csrf
            <x-field :label="__('Which account is this statement from?')" for="account_id">
                <select id="account_id" name="account_id" class="input" required>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field :label="__('CSV or PDF file')" for="file">
                @error('file')<p class="mb-1 text-sm text-bad">{{ $message }}</p>@enderror
                <input id="file" type="file" name="file" accept=".csv,.txt,.tsv,.pdf" required class="input file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-ink">
            </x-field>
            <button class="btn btn-primary">{{ __('Continue') }}</button>
        </form>

        <aside class="card h-fit text-sm">
            <h2 class="font-semibold">{{ __('What is understood') }}</h2>
            <ul class="mt-2 list-inside list-disc space-y-1 text-muted">
                <li>{{ __('Separators ; , tab and | (detected)') }}</li>
                <li>{{ __('Dates like 31/12/2026 or 2026-12-31') }}</li>
                <li>{{ __('Amounts like -5,50 €, 1.234,56 or (12.30)') }}</li>
                <li>{{ __('One amount column, or separate debit and credit') }}</li>
                <li>{{ __('Accents in older Windows files') }}</li>
                <li>{{ __('UTF-16 files and statements with account details above the table') }}</li>
                <li>{{ __('PDF statements: Millennium bcp "Extrato combinado", checked against the closing balance') }}</li>
                <li>{{ __('Rows already imported are flagged, so importing twice is safe') }}</li>
            </ul>
        </aside>
    </div>
</x-app-layout>
