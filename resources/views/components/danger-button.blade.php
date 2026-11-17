<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger border-bad/40']) }}>
    {{ $slot }}
</button>
