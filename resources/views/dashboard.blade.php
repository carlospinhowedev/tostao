@php
    use App\Services\Reports;
    use Illuminate\Support\Carbon;

    $month = $data['month'];
    $members = $data['members'];
    $sharedColor = '#8b95a1';
    $color = fn ($key) => $key === Reports::SHARED ? $sharedColor : ($members->firstWhere('id', (int) $key)->color ?? '#64748b');
    $label = fn ($key) => $key === Reports::SHARED ? __('Shared') : ($members->firstWhere('id', (int) $key)->name ?? '?');
    $monthLabel = fn ($m) => Carbon::createFromFormat('!Y-m', $m)->locale(app()->getLocale())->translatedFormat('F Y');
    $keys = array_keys($data['people']);

    $cats = array_slice($data['byCategory'], 0, 10);
    $catChart = [
        'horizontal' => true,
        'labels' => array_map(fn ($c) => $c['name'] ?? __('Uncategorised'), $cats),
        'datasets' => array_map(fn ($k) => [
            'label' => $label($k),
            'backgroundColor' => $color($k),
            'data' => array_map(fn ($c) => $c['by'][$k], $cats),
        ], $keys),
    ];
    $trendChart = [
        'labels' => array_map(fn ($t) => Carbon::createFromFormat('!Y-m', $t['month'])->locale(app()->getLocale())->translatedFormat('M'), $data['trend']),
        'datasets' => array_map(fn ($k) => [
            'label' => $label($k),
            'backgroundColor' => $color($k),
            'data' => array_map(fn ($t) => $t['by'][$k], $data['trend']),
        ], $keys),
    ];
    $totalSpent = $data['totals']['spent'];
    $hasData = $data['totals']['spent'] + $data['totals']['income'] > 0;
@endphp
<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $month ? $monthLabel($month) : __('All time') }}</h1>
            <p class="muted">
                {{ __('Spent') }} <x-money :cents="$totalSpent" class="font-medium text-ink" />
                · {{ __('Income') }} <x-money :cents="$data['totals']['income']" class="font-medium text-ink" />
                · {{ __('Balance') }} <x-money :cents="$data['totals']['income'] - $totalSpent" :signed="true" class="font-medium" />
            </p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('dashboard') }}">
                <label class="sr-only" for="month">{{ __('Period') }}</label>
                <select id="month" name="month" class="input !w-auto" onchange="this.form.submit()">
                    @foreach ($months as $m)
                        <option value="{{ $m }}" @selected($m === $month)>{{ $monthLabel($m) }}</option>
                    @endforeach
                    <option value="all" @selected($month === null)>{{ __('All time') }}</option>
                </select>
            </form>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">{{ __('Add transaction') }}</a>
        </div>
    </x-slot>

    @unless ($hasData)
        <div class="card text-center">
            <h2 class="text-lg font-semibold">{{ __('Nothing to show yet') }}</h2>
            <p class="muted mx-auto mt-1 max-w-md">{{ __('Add your first transaction or import the CSV your bank gives you. The dashboard fills itself in.') }}</p>
            <div class="mt-4 flex justify-center gap-2">
                <a href="{{ route('imports.create') }}" class="btn btn-primary">{{ __('Import a bank file') }}</a>
                <a href="{{ route('transactions.create') }}" class="btn">{{ __('Add transaction') }}</a>
            </div>
        </div>
    @else
        {{-- Who spends what --}}
        <section class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]" aria-label="{{ __('Spending by person') }}">
            @foreach ($keys as $key)
                @php $p = $data['people'][$key]; @endphp
                <div class="card">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="dot" style="background: {{ $color($key) }}"></span>{{ $label($key) }}
                    </div>
                    <div class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"><x-money :cents="$p['spent']" /></div>
                    <div class="muted">{{ __('spent') }}@if ($totalSpent > 0) · {{ round($p['spent'] / $totalSpent * 100) }}% @endif</div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line">
                        <div class="h-full rounded-full" style="width: {{ $totalSpent > 0 ? $p['spent'] / $totalSpent * 100 : 0 }}%; background: {{ $color($key) }}"></div>
                    </div>
                    <dl class="mt-3 space-y-1 text-sm">
                        @if ($key !== Reports::SHARED || $p['income'] > 0)
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Income') }}</dt><dd><x-money :cents="$p['income']" /></dd></div>
                        @endif
                        @if ($key !== Reports::SHARED)
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Balance') }}</dt><dd><x-money :cents="$p['net']" :signed="true" /></dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Transactions') }}</dt><dd class="tabular-nums">{{ $p['count'] }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </section>

        {{-- Charts --}}
        <section class="mt-6 grid gap-4 lg:grid-cols-5">
            <div class="card lg:col-span-3">
                <h2 class="font-semibold">{{ __('Where the money goes') }}</h2>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                    @foreach ($keys as $k)<span class="inline-flex items-center gap-1.5"><span class="dot" style="background: {{ $color($k) }}"></span>{{ $label($k) }}</span>@endforeach
                </div>
                <div class="mt-3" style="height: {{ max(160, count($cats) * 34 + 30) }}px">
                    <canvas data-chart="bar" data-config='@json($catChart)' role="img" aria-label="{{ __('Spending by category and person') }}"></canvas>
                </div>
            </div>
            <div class="card lg:col-span-2">
                <h2 class="font-semibold">{{ __('Last six months') }}</h2>
                <div class="mt-3 h-64">
                    <canvas data-chart="bar" data-config='@json($trendChart)' role="img" aria-label="{{ __('Monthly spending trend') }}"></canvas>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-3">
            {{-- Budgets --}}
            <div class="card lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Budgets') }} <span class="muted font-normal">· {{ $monthLabel($month ?? now()->format('Y-m')) }}</span></h2>
                    <a href="{{ route('categories.index') }}" class="muted underline">{{ __('Set budgets') }}</a>
                </div>
                @forelse ($data['budgets'] as $b)
                    @php $over = $b['ratio'] > 1; $near = $b['ratio'] > .8; @endphp
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-2"><span class="dot" style="background: {{ $b['category']->color }}"></span>{{ $b['category']->name }}</span>
                            <span class="tabular-nums {{ $over ? 'text-bad' : 'text-muted' }}">
                                <x-money :cents="$b['spent']" /> / <x-money :cents="$b['budget']" />
                            </span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ round($b['ratio'] * 100) }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full {{ $over ? 'bg-bad' : ($near ? 'bg-warn' : 'bg-good') }}" style="width: {{ min(100, $b['ratio'] * 100) }}%"></div>
                        </div>
                        @if ($over)<p class="mt-1 text-xs text-bad">{{ __('Over budget by :amount', ['amount' => \App\Support\Money::format($b['spent'] - $b['budget'])]) }}</p>@endif
                    </div>
                @empty
                    <p class="muted mt-3">{{ __('No budgets yet. Give a category a monthly limit and it shows up here.') }}</p>
                @endforelse
            </div>

            {{-- Right column --}}
            <div class="space-y-4">
                @if (count($settle['payments']))
                    <div class="card">
                        <h2 class="font-semibold">{{ __('Settle up') }}</h2>
                        @foreach ($settle['payments'] as $pay)
                            @php $from = $members->firstWhere('id', $pay['from']); $to = $members->firstWhere('id', $pay['to']); @endphp
                            <p class="mt-2 text-sm">{{ __(':from owes :to', ['from' => $from->name, 'to' => $to->name]) }} <x-money :cents="$pay['amount_cents']" class="font-semibold" /></p>
                        @endforeach
                        <a href="{{ route('settle.index') }}" class="muted mt-2 inline-block underline">{{ __('See details') }}</a>
                    </div>
                @endif

                <div class="card">
                    <h2 class="font-semibold">{{ __('Accounts') }}</h2>
                    <ul class="mt-2 divide-y divide-line text-sm">
                        @foreach ($data['accounts'] as $a)
                            <li class="flex justify-between py-2"><span>{{ $a['account']->name }}</span><x-money :cents="$a['balance']" /></li>
                        @endforeach
                    </ul>
                </div>

                @if (count($data['topMerchants']))
                    <div class="card">
                        <h2 class="font-semibold">{{ __('Biggest expenses') }}</h2>
                        <ul class="mt-2 divide-y divide-line text-sm">
                            @foreach ($data['topMerchants'] as $m)
                                <li class="flex justify-between gap-3 py-2"><span class="truncate">{{ $m['name'] }}@if ($m['count'] > 1) <span class="text-muted">×{{ $m['count'] }}</span>@endif</span><x-money :cents="$m['total']" /></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endunless
</x-app-layout>
