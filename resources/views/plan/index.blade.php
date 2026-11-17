@php
    $natureLabel = fn ($n) => __(\App\Models\PlanItem::NATURE_LABELS[$n] ?? 'Fixed');
    $rowClass = ['fixed' => 'border-l-4 border-good bg-good/10', 'variable' => 'border-l-4 border-warn bg-warn/10', 'optional' => 'border-l-4 border-line'];
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(2000, $m, 1)->locale(app()->getLocale())->translatedFormat('M')]);
    $kindLabel = ['income' => __('Income'), 'expense' => __('Expense'), 'saving' => __('Savings')];
@endphp
<x-app-layout :title="__('Plan')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Plan') }}</h1>
            <p class="muted max-w-2xl">{{ __('The month on paper: what comes in, what is meant to go out and what is set aside. Fixed costs are green, variable but required ones amber, and optional ones plain.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('plan.index', ['month' => $previous]) }}" class="btn btn-sm" aria-label="{{ __('Previous month') }}">←</a>
            <span class="min-w-32 text-center font-medium capitalize">{{ $label }}</span>
            <a href="{{ route('plan.index', ['month' => $next]) }}" class="btn btn-sm" aria-label="{{ __('Next month') }}">→</a>
        </div>
    </x-slot>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card"><div class="muted text-sm">{{ __('Planned income') }}</div><x-money :cents="$plan['incomeTotal']" class="text-xl font-semibold" /></div>
        <div class="card"><div class="muted text-sm">{{ __('Planned spending') }}</div><x-money :cents="$plan['expenseTotal']" class="text-xl font-semibold" />
            @if ($plan['incomeTotal'] > 0)<div class="muted text-xs">{{ round($plan['expenseTotal'] / $plan['incomeTotal'] * 100, 1) }}% {{ __('of income') }}</div>@endif</div>
        <div class="card"><div class="muted text-sm">{{ __('Set aside') }}</div><x-money :cents="$plan['savingTotal']" class="text-xl font-semibold" /></div>
        <div class="card"><div class="muted text-sm">{{ __('Left over') }}</div><x-money :cents="$plan['leftAfterSavings']" class="text-xl font-semibold {{ $plan['leftAfterSavings'] < 0 ? 'text-bad' : 'text-good' }}" />
            @if ($plan['savingTotal'] > 0)<div class="muted text-xs">{{ __('before savings') }}: <x-money :cents="$plan['left']" /></div>@endif</div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($plan['owners'] as $owner)
                <div class="card !p-0 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                        <h2 class="font-semibold">{{ $owner['name'] }}</h2>
                        <span class="text-sm"><x-money :cents="$owner['total']" class="font-semibold" />@if ($owner['share'] !== null) <span class="muted">· {{ $owner['share'] }}% {{ __('of income') }}</span>@endif</span>
                    </div>
                    <table class="tbl">
                        <tbody>
                            @foreach ($owner['groups'] as $name => $group)
                                @if ($name !== '')
                                    <tr><td colspan="2" class="bg-page/60 text-xs font-semibold uppercase tracking-wide text-muted">{{ $name }} · <x-money :cents="$group['total']" /></td></tr>
                                @endif
                                @foreach ($group['items'] as $i)
                                    <tr class="{{ $rowClass[$i->nature] ?? '' }}">
                                        <td>{{ $i->name }}@if ($i->months) <span class="pill ml-1">{{ collect($i->months)->map(fn ($m) => $monthNames[$m])->implode(', ') }}</span>@endif</td>
                                        <td class="num"><x-money :cents="$i->amount_cents" /></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    @isset($owner['withShared'])
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line bg-page/60 px-4 py-2 text-sm">
                            <span class="muted">{{ __('With an equal part of what is shared') }}</span>
                            <span><x-money :cents="$owner['withShared']" class="font-semibold" />@if ($owner['withSharedShare'] !== null) <span class="muted">· {{ $owner['withSharedShare'] }}% {{ __('of income') }}</span>@endif</span>
                        </div>
                    @endisset
                    <div class="flex flex-wrap gap-3 border-t border-line px-4 py-2 text-xs text-muted">
                        @foreach (\App\Models\PlanItem::NATURES as $n)
                            @if ($owner['byNature'][$n] > 0)<span>{{ $natureLabel($n) }}: <x-money :cents="$owner['byNature'][$n]" /></span>@endif
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card"><p class="muted text-center">{{ __('Nothing planned for this month yet. Add the first line on the right.') }}</p></div>
            @endforelse

            @if ($plan['groups'])
                <div class="card">
                    <h2 class="mb-2 font-semibold">{{ __('By group') }}</h2>
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($plan['groups'] as $name => $total)<li class="flex justify-between py-1.5"><span>{{ $name }}</span><x-money :cents="$total" /></li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($plan['vsReal'])
                <div class="card">
                    <h2 class="font-semibold">{{ __('Plan against what really happened') }}</h2>
                    <p class="muted mb-3 text-sm">{{ __('Real spending in the category each line is linked to, this month.') }}</p>
                    <ul class="space-y-3">
                        @foreach ($plan['vsReal'] as $row)
                            <li>
                                <div class="flex justify-between text-sm"><span>{{ $row['category']->name }}</span><span><x-money :cents="$row['actual']" /> <span class="muted">/ <x-money :cents="$row['planned']" /></span></span></div>
                                @if ($row['ratio'] !== null)
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full {{ $row['ratio'] > 1 ? 'bg-bad' : ($row['ratio'] > 0.85 ? 'bg-warn' : 'bg-good') }}" style="width: {{ min(100, $row['ratio'] * 100) }}%"></div></div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="muted mt-3 border-t border-line pt-3 text-sm">{{ __('Real so far') }}: {{ __('spent') }} <x-money :cents="$plan['real']['spent']" /> · {{ __('income') }} <x-money :cents="$plan['real']['income']" /> · {{ __('left') }} <x-money :cents="$plan['real']['left']" /></p>
                </div>
            @endif

            @if ($plan['income']->isNotEmpty() || $plan['savings']->isNotEmpty())
                <div class="card !p-0 overflow-hidden">
                    <table class="tbl">
                        <tbody>
                            @foreach ([[__('Income'), $plan['income']], [__('Savings'), $plan['savings']]] as [$title, $list])
                                @if ($list->isNotEmpty())
                                    <tr><td colspan="3" class="bg-page/60 text-xs font-semibold uppercase tracking-wide text-muted">{{ $title }}</td></tr>
                                    @foreach ($list as $i)
                                        <tr>
                                            <td>{{ $i->name }}@if ($i->months) <span class="pill ml-1">{{ collect($i->months)->map(fn ($m) => $monthNames[$m])->implode(', ') }}</span>@endif</td>
                                            <td class="muted">{{ $i->owner?->name ?? __('Shared') }}</td>
                                            <td class="num"><x-money :cents="$i->amount_cents" /></td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <form method="POST" action="{{ route('plan.store') }}" class="card space-y-3" x-data="{ kind: 'expense' }">
                @csrf
                <h2 class="font-semibold">{{ __('Add a line') }}</h2>
                @include('plan._fields', ['item' => null])
                <button class="btn btn-primary w-full">{{ __('Add line') }}</button>
            </form>

            @if ($items->isNotEmpty())
                <div class="card !p-0">
                    <h2 class="px-4 pt-4 font-semibold">{{ __('All lines') }}</h2>
                    @foreach ($items as $i)
                        <details class="border-b border-line last:border-0 {{ $i->active ? '' : 'opacity-60' }}">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-sm">
                                <span>{{ $i->name }} <span class="muted">· {{ $kindLabel[$i->kind] }}@if ($i->nature) · {{ $natureLabel($i->nature) }}@endif</span></span>
                                <x-money :cents="$i->amount_cents" />
                            </summary>
                            <form method="POST" action="{{ route('plan.update', $i) }}" class="space-y-3 border-t border-line bg-page/50 p-4" x-data="{ kind: @js($i->kind) }">
                                @csrf @method('PUT')
                                @include('plan._fields', ['item' => $i])
                                <div class="flex gap-2">
                                    <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                                    <button type="submit" form="del-plan-{{ $i->id }}" class="btn btn-danger btn-sm" onclick="return confirm(@js(__('Delete this line?')))">{{ __('Delete') }}</button>
                                </div>
                            </form>
                            <form id="del-plan-{{ $i->id }}" method="POST" action="{{ route('plan.destroy', $i) }}" class="hidden">@csrf @method('DELETE')</form>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
