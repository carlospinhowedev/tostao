<?php

namespace App\Banking;

/** An account that exists at the bank. */
final readonly class RemoteAccount
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $iban = null,
        public string $currency = 'EUR',
    ) {}
}
