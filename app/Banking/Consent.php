<?php

namespace App\Banking;

use Carbon\CarbonInterface;

/** The result of a completed consent: a session and the accounts it grants. */
final readonly class Consent
{
    /** @param  list<RemoteAccount>  $accounts */
    public function __construct(
        public string $sessionId,
        public array $accounts,
        public ?CarbonInterface $validUntil = null,
    ) {}
}
