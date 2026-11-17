@php
    $s = $settings;
    $delim = $s['delimiter'] === "\t" ? 'tab' : $s['delimiter'];
    $colSelect = function (string $name, ?int $current, bool $optional = false) use ($header) {
        $html = '<select name="'.$name.'" class="input">';
        if ($optional) {
            $html .= '<option value="">—</option>';
        }
        foreach ($header as $i => $h) {
            $html .= '<option value="'.$i.'"'.($current === $i ? ' selected' : '').'>'.e($h !== '' ? $h : __('Column').' '.($i + 1)).'</option>';
        }

        return new Illuminate\Support\HtmlString($html.'</select>');
    };
    $newCount = collect($preview)->where('status', 'new')->count();
    $statusLabel = ['new' => __('New'), 'duplicate' => __('Already imported'), 'maybe' => __('Possible duplicate')];
@endphp
<x-app-layout :title="__('Import')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Check and import') }}</h1>
            <p class="muted">{{ __('Account') }}: <strong class="text-ink">{{ $account->name }}</strong></p>
        </div>
    </x-slot>

    {{-- 1. Mapping (GET: refreshes the preview) --}}
    <form method="GET" action="{{ route('imports.review', $token) }}" class="card mb-6 space-y-4">
        <input type="hidden" name="account_id" value="{{ $account->id }}">
        <h2 class="font-semibold">{{ __('1. How to read the file') }}</h2>
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <x-field :label="__('Separator')"><select name="delimiter" class="input">@foreach (\App\Services\CsvImporter::DELIMITERS as $k => $l)<option value="{{ $k === "\t" ? 'tab' : $k }}" @selected($delim === ($k === "\t" ? 'tab' : $k))>{{ $l === 'tab' ? __('Tab') : $l }}</option>@endforeach</select></x-field>
            <x-field :label="__('Date format')"><select name="date_format" class="input">@foreach (\App\Support\DateParser::FORMATS as $k => $l)<option value="{{ $k }}" @selected($s['date_format'] === $k)>{{ $l }}</option>@endforeach</select></x-field>
            <x-field :label="__('Decimal separator')"><select name="decimal" class="input"><option value="auto" @selected($s['decimal'] === 'auto')>{{ __('Automatic') }}</option><option value="comma" @selected($s['decimal'] === 'comma')>{{ __('Comma (12,50)') }}</option><option value="dot" @selected($s['decimal'] === 'dot')>{{ __('Dot (12.50)') }}</option></select></x-field>
            <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="has_header" value="1" @checked($s['has_header']) class="rounded border-line text-accent focus:ring-accent"> {{ __('First row is a header') }}</label>
            <x-field :label="__('Date column')">{!! $colSelect('date', $s['map']['date']) !!}</x-field>
            <x-field :label="__('Description column')">{!! $colSelect('description', $s['map']['description']) !!}</x-field>
            <x-field :label="__('Amount column')">{!! $colSelect('amount', $s['map']['amount'], true) !!}</x-field>
            <div class="grid grid-cols-2 gap-2">
                <x-field :label="__('Debit')">{!! $colSelect('debit', $s['map']['debit'], true) !!}</x-field>
                <x-field :label="__('Credit')">{!! $colSelect('credit', $s['map']['credit'], true) !!}</x-field>
            </div>
        </div>
        <button class="btn">{{ __('Update preview') }}</button>
    </form>

    @if (count($skipped))
        <div class="flash flash-error mb-4">
            {{ trans_choice(':count row could not be read and was skipped (line :lines).|:count rows could not be read and were skipped (lines :lines).', count($skipped), ['count' => count($skipped), 'lines' => implode(', ', array_slice(array_column($skipped, 'line'), 0, 12))]) }}
            {{ __('Check the date format and the columns above.') }}
        </div>
    @endif

    {{-- 2. Review (POST: imports) --}}
    @if (count($preview) === 0)
        <div class="card muted text-center">{{ __('No rows could be read with these settings.') }}</div>
    @else
        <form method="POST" action="{{ route('imports.commit', $token) }}" x-data="{ all: true }">
            @csrf
            <input type="hidden" name="account_id" value="{{ $account->id }}">
            <input type="hidden" name="delimiter" value="{{ $delim }}">
            <input type="hidden" name="date_format" value="{{ $s['date_format'] }}">
            <input type="hidden" name="decimal" value="{{ $s['decimal'] }}">
            <input type="hidden" name="has_header" value="{{ $s['has_header'] ? 1 : 0 }}">
            @foreach ($s['map'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">{{ __('2. Pick the rows to import') }} <span class="muted font-normal">· {{ trans_choice(':count new row|:count new rows', $newCount, ['count' => $newCount]) }}</span></h2>
                <button class="btn btn-primary">{{ __('Import selected rows') }}</button>
            </div>

            <div class="card !p-0 overflow-x-auto">
                <table class="tbl">
                    <thead><tr>
                        <th><input type="checkbox" checked aria-label="{{ __('Select all') }}" class="rounded border-line text-accent focus:ring-accent" @change="document.querySelectorAll('.row-check:not(:disabled)').forEach(c => c.checked = $event.target.checked)"></th>
                        <th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Category') }}</th><th>{{ __('Who') }}</th><th>{{ __('Status') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            @php $line = $row['line']; $dup = $row['status'] === 'duplicate'; @endphp
                            <tr class="{{ $dup ? 'opacity-50' : '' }}">
                                <td><input type="checkbox" name="rows[]" value="{{ $line }}" class="row-check rounded border-line text-accent focus:ring-accent" @checked($row['status'] === 'new') aria-label="{{ __('Import this row') }}"></td>
                                <td class="whitespace-nowrap text-muted">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="max-w-xs truncate" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                                <td class="num"><x-money :cents="$row['amount_cents']" :signed="true" /></td>
                                <td><select name="category[{{ $line }}]" class="input !py-1 text-xs"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($row['category_id'] === $c->id)>{{ $c->name }}</option>@endforeach</select></td>
                                <td><select name="owner[{{ $line }}]" class="input !py-1 text-xs"><x-owner-options :members="$members" :selected="$row['set_owner'] ? ($row['owner_id'] ?? 'shared') : ($account->owner_id ?? 'shared')" /></select></td>
                                <td><span class="pill {{ $row['status'] === 'new' ? '' : 'text-warn' }}">{{ $statusLabel[$row['status']] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    @endif
</x-app-layout>
