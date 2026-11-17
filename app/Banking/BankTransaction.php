<?php

namespace App\Banking;

/** A booked movement, already normalised: negative amount = money out. */
final readonly class BankTransaction
{
    public function __construct(
        public string $date,        // Y-m-d
        public int $amountCents,
        public string $description,
        public ?string $externalId = null,
    ) {}
}
