{{-- Options for "who is this for": the household ("shared") or one member. --}}
@props(['members', 'selected' => 'shared', 'shared' => null, 'blank' => null])

@if ($blank !== null)
    <option value="">{{ $blank }}</option>
@endif
<option value="shared" @selected((string) $selected === 'shared')>{{ $shared ?? __('Shared') }}</option>
@foreach ($members as $member)
    <option value="{{ $member->id }}" @selected((string) $selected === (string) $member->id)>{{ $member->name }}</option>
@endforeach
