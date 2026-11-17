<?php

namespace App\Banking;

/** A bank offered by a provider. `id` is opaque: only the provider understands it. */
final readonly class Institution
{
    public function __construct(public string $id, public string $name, public ?string $logo = null) {}
}
