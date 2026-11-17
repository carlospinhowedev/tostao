<x-app-layout :title="__('Members')">
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Members') }}</h1>
            <p class="muted max-w-2xl">{{ __('Everyone who lives in this household. Each person has their own login and colour. There is no public sign-up: members are added here.') }}</p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card !p-0 lg:col-span-2">
            <ul class="divide-y divide-line">
                @foreach ($members as $m)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="dot !h-4 !w-4" style="background: {{ $m->color }}"></span>
                            <div><div class="font-medium">{{ $m->name }} @if ($m->is(auth()->user()))<span class="pill ml-1">{{ __('You') }}</span>@endif</div><div class="muted">{{ $m->email }}</div></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('members.update', $m) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <input type="color" name="color" value="{{ $m->color }}" class="h-8 w-10 rounded border border-line bg-surface p-0.5" aria-label="{{ __('Colour') }}" onchange="this.form.submit()">
                            </form>
                            @unless ($m->is(auth()->user()))
                                <form method="POST" action="{{ route('members.destroy', $m) }}" onsubmit="return confirm('{{ __('Remove this member?') }}')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">{{ __('Remove') }}</button></form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <form method="POST" action="{{ route('members.store') }}" class="card h-fit space-y-3">
            @csrf
            <h2 class="font-semibold">{{ __('Add a member') }}</h2>
            <x-field :label="__('Name')" for="name"><input id="name" name="name" class="input" required></x-field>
            <x-field :label="__('Email')" for="email"><input id="email" type="email" name="email" class="input" required autocomplete="off"></x-field>
            <x-field :label="__('Password')" for="password"><input id="password" type="password" name="password" class="input" required autocomplete="new-password"></x-field>
            <x-field :label="__('Colour')" for="color"><input id="color" type="color" name="color" value="{{ config('tostao.member_colors')[$members->count() % count(config('tostao.member_colors'))] }}" class="h-9 w-full rounded-lg border border-line bg-surface p-1"></x-field>
            <button class="btn btn-primary w-full">{{ __('Add member') }}</button>
        </form>
    </div>
</x-app-layout>
