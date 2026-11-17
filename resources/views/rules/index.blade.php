@php
    $ruleOwner = fn ($r) => $r->set_owner ? ($r->owner_id ?? 'shared') : '';
@endphp
<x-app-layout :title="__('Rules')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Rules') }}</h1>
            <p class="muted max-w-2xl">{{ __('When an imported description contains a keyword, the category and person are filled in for you. The lowest priority number wins; case is ignored.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card !p-0 lg:col-span-2">
            @forelse ($rules as $r)
                <details class="border-b border-line last:border-0">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <span><span class="text-muted">{{ __('contains') }}</span> <code class="rounded bg-line/60 px-1.5 py-0.5 text-sm">{{ $r->pattern }}</code></span>
                        <span class="flex items-center gap-2 text-sm">
                            @if ($r->category)<span class="pill"><span class="dot" style="background: {{ $r->category->color }}"></span>{{ $r->category->name }}</span>@endif
                            @if ($r->set_owner)<span class="pill">{{ $r->owner?->name ?? __('Shared') }}</span>@endif
                            <span class="muted">#{{ $r->priority }}</span>
                        </span>
                    </summary>
                    <form method="POST" action="{{ route('rules.update', $r) }}" class="grid gap-3 border-t border-line bg-page/50 p-4 sm:grid-cols-4">
                        @csrf @method('PUT')
                        <x-field :label="__('Keyword')"><input name="pattern" class="input" required value="{{ $r->pattern }}"></x-field>
                        <x-field :label="__('Category')"><select name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($r->category_id === $c->id)>{{ $c->name }}</option>@endforeach</select></x-field>
                        <x-field :label="__('Who')"><select name="owner" class="input"><x-owner-options :members="$members" :selected="$ruleOwner($r)" :blank="__('Leave as is')" /></select></x-field>
                        <x-field :label="__('Priority')"><input type="number" name="priority" min="1" max="9999" class="input" value="{{ $r->priority }}"></x-field>
                        <div class="flex gap-2 sm:col-span-4">
                            <button class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                            <button type="submit" form="del-rule-{{ $r->id }}" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                        </div>
                    </form>
                    <form id="del-rule-{{ $r->id }}" method="POST" action="{{ route('rules.destroy', $r) }}" class="hidden">@csrf @method('DELETE')</form>
                </details>
            @empty
                <p class="muted p-6 text-center">{{ __('No rules yet. Create one on the right, for example "supermarket" → Groceries.') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('rules.store') }}" class="card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">{{ __('New rule') }}</h2>
            <x-field :label="__('Description contains')" for="pattern"><input id="pattern" name="pattern" class="input" required placeholder="PINGO DOCE"></x-field>
            <x-field :label="__('Set category')" for="category_id"><select id="category_id" name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></x-field>
            <x-field :label="__('Set who')" for="owner"><select id="owner" name="owner" class="input"><x-owner-options :members="$members" selected="" :blank="__('Leave as is')" /></select></x-field>
            <x-field :label="__('Priority')" for="priority"><input id="priority" type="number" name="priority" min="1" max="9999" value="100" class="input"></x-field>
            <button class="btn btn-primary w-full">{{ __('Create rule') }}</button>
        </form>
    </div>
</x-app-layout>
